<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BrandDetectionService
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_CYAN = "\033[1;96m";
    private const COLOR_WHITE = "\033[1;97m";

    private $outputCallback = null;
    private ?array $brandsCache = null;
    private bool $isDatabaseAvailable = true;
    private string $brandConnection = 'mysql';

    // تنظیمات حساسیت
    private const EXACT_MATCH_THRESHOLD = 0.7;
    private const PARTIAL_MATCH_THRESHOLD = 0.6;
    private const FUZZY_MATCH_THRESHOLD = 0.9;
    private const MIN_WORD_LENGTH = 3;

    // کش برای نتایج محاسبات پرهزینه
    private array $normalizedTextCache = [];
    private array $similarityCache = [];
    private array $wordExtractionCache = [];
    private ?array $stopWordsCache = null;

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    /**
     * شناسایی برند از متن عنوان محصول
     */
    public function detectBrandFromText(string $text): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        try {
            if (!$this->checkDatabaseAvailability()) {
                $this->log("⚠️ جدول brands در دسترس نیست - تشخیص برند غیرفعال شد", self::COLOR_YELLOW);
                return null;
            }

            $brands = $this->getAllBrands();

            if (empty($brands)) {
                $this->log("⚠️ هیچ برندی در دیتابیس یافت نشد", self::COLOR_YELLOW);
                return null;
            }

            // ابتدا تطابق دقیق را بررسی کن
            $exactMatch = $this->findExactMatch($text, $brands);
            if ($exactMatch) {
                return $exactMatch['name'];
            }

            // در صورت عدم تطابق دقیق، تطابق فازی را بررسی کن
            $detectedBrand = $this->findBestMatchingBrand($text, $brands);

            return $detectedBrand ? $detectedBrand['name'] : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * جستجوی تطابق دقیق بهبود یافته (اولویت بالا)
     */
    private function findExactMatch(string $text, array $brands): ?array
    {
        $normalizedText = $this->normalizeText($text);
        $textWords = $this->extractValidWords($normalizedText);

        foreach ($brands as $brand) {
            // بررسی تطابق دقیق نام اصلی
            if (!empty($brand['name']) && $this->checkFieldMatch($textWords, $brand['name'])) {
                return $brand;
            }

            // بررسی تطابق دقیق نام SEO
            if (!empty($brand['nameSeo']) && $this->checkFieldMatch($textWords, $brand['nameSeo'])) {
                return $brand;
            }

            // بررسی تطابق دقیق slug
            if (!empty($brand['slug']) && $this->checkFieldMatch($textWords, $brand['slug'])) {
                return $brand;
            }

            // بررسی تطابق کامل کلمه کلیدی
            if (!empty($brand['keyword'])) {
                $keywords = array_filter(array_map('trim', explode(',', $brand['keyword'])));
                foreach ($keywords as $keyword) {
                    if ($this->checkFieldMatch($textWords, $keyword)) {
                        return $brand;
                    }
                }
            }
        }

        return null;
    }

    /**
     * بررسی تطابق فیلد (بهینه‌شده)
     */
    private function checkFieldMatch(array $textWords, string $fieldValue): bool
    {
        $normalizedField = $this->normalizeText($fieldValue);
        $fieldWords = $this->extractValidWords($normalizedField);

        if (empty($fieldWords)) {
            return false;
        }

        $fieldWords = array_values(array_filter($fieldWords, fn($word) => strlen($word) >= self::MIN_WORD_LENGTH));

        if (empty($fieldWords)) {
            return false;
        }

        return count($fieldWords) === 1
            ? $this->hasSingleWordMatch($textWords, $fieldWords[0])
            : $this->hasSequentialMatch($textWords, $fieldWords);
    }

    /**
     * بررسی تطابق دنباله‌ای کلمات برند در متن (بهینه‌شده)
     */
    private function hasSequentialMatch(array $textWords, array $brandWords): bool
    {
        $textCount = count($textWords);
        $brandCount = count($brandWords);

        if ($brandCount > $textCount) {
            return false;
        }

        // جستجوی دنباله کاملاً پیوسته و به ترتیب
        for ($i = 0; $i <= $textCount - $brandCount; $i++) {
            $match = true;
            for ($j = 0; $j < $brandCount; $j++) {
                if ($textWords[$i + $j] !== $brandWords[$j]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return true;
            }
        }

        return false;
    }

    /**
     * بررسی تطابق یک کلمه (بهینه‌شده)
     */
    private function hasSingleWordMatch(array $textWords, string $brandWord): bool
    {
        return in_array($brandWord, $textWords, true);
    }

    /**
     * بررسی دسترسی به دیتابیس و جدول brands
     */
    private function checkDatabaseAvailability(): bool
    {
        try {
            if (!Schema::connection($this->brandConnection)->hasTable('brands')) {
                $this->log("❌ جدول 'brands' در اتصال '{$this->brandConnection}' وجود ندارد", self::COLOR_RED);

                if (!Schema::hasTable('brands')) {
                    $this->log("❌ جدول 'brands' در اتصال فعلی نیز وجود ندارد", self::COLOR_RED);
                    $this->isDatabaseAvailable = false;
                    return false;
                } else {
                    $this->log("✅ جدول 'brands' در اتصال فعلی یافت شد - تغییر اتصال", self::COLOR_GREEN);
                    $this->brandConnection = DB::getDefaultConnection();
                }
            } else {
                $this->log("✅ جدول 'brands' در اتصال '{$this->brandConnection}' یافت شد", self::COLOR_GREEN);
            }

            DB::connection($this->brandConnection)->table('brands')->limit(1)->get();
            $this->isDatabaseAvailable = true;
            return true;

        } catch (\Exception $e) {
            $this->log("❌ خطا در دسترسی به جدول brands: " . $e->getMessage(), self::COLOR_RED);
            $this->isDatabaseAvailable = false;
            return false;
        }
    }

    /**
     * دریافت تمام برندها از دیتابیس با cache
     */
    private function getAllBrands(): array
    {
        if ($this->brandsCache !== null) {
            return $this->brandsCache;
        }

        try {
            if (!$this->isDatabaseAvailable) {
                return [];
            }

            $brands = DB::connection($this->brandConnection)->table('brands')
                ->select('id', 'name', 'nameSeo', 'slug', 'body', 'bodySeo', 'keyword')
                ->get()
                ->map(fn($brand) => (array)$brand)
                ->toArray();

            $this->brandsCache = $brands;
            return $brands;

        } catch (\Exception $e) {
            $this->isDatabaseAvailable = false;
            $this->brandsCache = [];
            return [];
        }
    }

    /**
     * یافتن بهترین برند متطابق با الگوریتم پیشرفته
     */
    private function findBestMatchingBrand(string $text, array $brands): ?array
    {
        $text = $this->normalizeText($text);
        $bestMatch = null;
        $highestScore = 0;

        foreach ($brands as $brand) {
            $score = $this->calculateAdvancedBrandScore($text, $brand);

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $brand;
            }
        }

        // آستانه تطابق متحرک یا پذیرش تطابق کامل
        $dynamicThreshold = $this->calculateDynamicThreshold($highestScore);

        return ($highestScore >= 1.0 || $highestScore >= $dynamicThreshold) ? $bestMatch : null;
    }

    /**
     * محاسبه آستانه متحرک (بهینه‌شده)
     */
    private function calculateDynamicThreshold(float $highestScore): float
    {
        return match (true) {
            $highestScore >= 0.8 => 0.8,
            $highestScore >= 0.6 => 0.6,
            $highestScore >= 0.4 => 0.4,
            $highestScore >= 0.2 => 0.2,
            default => 0.1
        };
    }

    /**
     * محاسبه امتیاز پیشرفته برند (بهینه‌شده)
     */
    private function calculateAdvancedBrandScore(string $text, array $brand): float
    {
        $totalScore = 0;
        $maxPossibleScore = 0;

        // تعریف وزن‌ها و فیلدها
        $fields = [
            'name' => 4,
            'nameSeo' => 3.5,
            'slug' => 3,
            'keyword' => 2,
            'body' => 1,
            'bodySeo' => 0.5
        ];

        foreach ($fields as $field => $weight) {
            if (empty($brand[$field])) {
                continue;
            }

            $score = match ($field) {
                'keyword' => $this->calculateKeywordMatch($text, $brand[$field]),
                'body', 'bodySeo' => $this->calculateContextMatch($text, $brand[$field]),
                default => $this->calculatePreciseMatch($text, $brand[$field], $field)
            };

            $totalScore += $score * $weight;
            $maxPossibleScore += $weight;
        }

        return $maxPossibleScore > 0 ? $totalScore / $maxPossibleScore : 0;
    }

    /**
     * محاسبه تطابق دقیق بهبود یافته (بهینه‌شده)
     */
    private function calculatePreciseMatch(string $text, string $brandValue, string $fieldType): float
    {
        $normalizedBrand = $this->normalizeText($brandValue);
        $brandWords = $this->extractValidWords($normalizedBrand);

        if (empty($brandWords)) {
            return 0;
        }

        $textWords = $this->extractValidWords($text);

        // اگر برند تک‌کلمه‌ای است
        if (count($brandWords) === 1) {
            $brandWord = $brandWords[0];

            foreach ($textWords as $textWord) {
                // تطابق کامل
                if ($brandWord === $textWord) {
                    return 1.0;
                }

                // تطابق فازی برای کلمات طولانی
                if (strlen($brandWord) >= 4 && strlen($textWord) >= 4) {
                    $similarity = $this->calculateSimilarity($brandWord, $textWord);
                    if ($similarity >= self::FUZZY_MATCH_THRESHOLD) {
                        return $similarity;
                    }
                }
            }
            return 0;
        }

        // برای برندهای چندکلمه‌ای، فقط دنباله پیوسته و به ترتیب
        return $this->hasSequentialMatch($textWords, $brandWords) ? 1.0 : 0;
    }

    /**
     * محاسبه تطابق کلمات کلیدی بهبود یافته (بهینه‌شده)
     */
    private function calculateKeywordMatch(string $text, string $keywords): float
    {
        $keywordList = array_filter(array_map('trim', explode(',', $keywords)));

        if (empty($keywordList)) {
            return 0;
        }

        $matches = 0;
        $totalKeywords = 0;
        $textWords = $this->extractValidWords($text);

        foreach ($keywordList as $keyword) {
            $normalizedKeyword = $this->normalizeText($keyword);

            if (strlen($normalizedKeyword) < 3) {
                continue;
            }

            $totalKeywords++;

            // جستجوی تطابق کامل در متن
            if ($this->isCompleteWordMatch($text, $normalizedKeyword)) {
                $matches++;
                continue;
            }

            // جستجوی تطابق در کلمات جداگانه
            foreach ($textWords as $textWord) {
                if ($normalizedKeyword === $textWord) {
                    $matches++;
                    break;
                }

                // تطابق فازی برای کلمات طولانی
                if (strlen($normalizedKeyword) >= 4 && strlen($textWord) >= 4) {
                    $similarity = $this->calculateSimilarity($normalizedKeyword, $textWord);
                    if ($similarity >= self::FUZZY_MATCH_THRESHOLD) {
                        $matches += $similarity;
                        break;
                    }
                }
            }
        }

        return $totalKeywords > 0 ? min($matches / $totalKeywords, 1.0) : 0;
    }

    /**
     * محاسبه تطابق متنی (بهینه‌شده)
     */
    private function calculateContextMatch(string $text, string $context): float
    {
        $textWords = array_filter(
            $this->extractValidWords($text),
            fn($word) => strlen($word) >= 4
        );

        $contextWords = array_filter(
            $this->extractValidWords($context),
            fn($word) => strlen($word) >= 4
        );

        if (empty($textWords) || empty($contextWords)) {
            return 0;
        }

        $matches = 0;
        foreach ($textWords as $textWord) {
            foreach ($contextWords as $contextWord) {
                $similarity = $this->calculateSimilarity($textWord, $contextWord);
                if ($similarity >= 0.85) {
                    $matches += $similarity;
                    break;
                }
            }
        }

        return min($matches / count($textWords), 1.0);
    }

    /**
     * استخراج کلمات معتبر بهبود یافته (بهینه‌شده با کش)
     */
    private function extractValidWords(string $text): array
    {
        $cacheKey = md5($text);

        if (isset($this->wordExtractionCache[$cacheKey])) {
            return $this->wordExtractionCache[$cacheKey];
        }

        // تقسیم با جداکننده‌های مختلف
        $words = preg_split('/[\s\-_\.\/\|\(\)\[\]]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $validWords = [];
        foreach ($words as $word) {
            // پاکسازی کلمه از کاراکترهای غیرضروری
            $cleanWord = preg_replace('/[^\p{L}\p{N}]/u', '', $word);

            // شرایط کلمه معتبر
            if (strlen($cleanWord) >= 2 &&
                !is_numeric($cleanWord) &&
                !$this->isStopWord($cleanWord) &&
                preg_match('/\p{L}/u', $cleanWord)) {
                $validWords[] = mb_strtolower($cleanWord, 'UTF-8');
            }
        }

        $result = array_unique($validWords);

        // کش کردن نتیجه (محدود کردن اندازه کش)
        if (count($this->wordExtractionCache) < 1000) {
            $this->wordExtractionCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * بررسی کلمات توقف (بهینه‌شده با کش)
     */
    private function isStopWord(string $word): bool
    {
        if ($this->stopWordsCache === null) {
            $this->stopWordsCache = array_flip([
                // کلمات فارسی
                'مدل', 'نوع', 'برند', 'محصول', 'کیفیت', 'بالا', 'پایین', 'بزرگ', 'کوچک',
                'قرمز', 'آبی', 'سفید', 'سیاه', 'رنگ', 'اندازه', 'سایز', 'عدد', 'تعداد',
                'برای', 'مخصوص', 'ویژه', 'خاص', 'مناسب', 'بهترین', 'ارزان', 'گران',
                'جدید', 'قدیمی', 'اصل', 'اصلی', 'تقلبی', 'اورجینال', 'چینی', 'ایرانی',
                // کلمات انگلیسی
                'model', 'type', 'brand', 'product', 'quality', 'high', 'low', 'big', 'small',
                'red', 'blue', 'white', 'black', 'color', 'size', 'new', 'old', 'original',
                'for', 'special', 'best', 'cheap', 'expensive', 'chinese', 'iranian',
                // کلمات فنی
                'port', 'cable', 'meter', 'cm', 'mm', 'kg', 'gram', 'console', 'system',
                'device', 'tool', 'equipment', 'wifi', 'bluetooth', 'usb', 'hdmi'
            ]);
        }

        return isset($this->stopWordsCache[mb_strtolower($word, 'UTF-8')]);
    }

    /**
     * محاسبه شباهت بین دو کلمه (بهینه‌شده با کش)
     */
    private function calculateSimilarity(string $word1, string $word2): float
    {
        if ($word1 === $word2) {
            return 1.0;
        }

        // ایجاد کلید کش
        $cacheKey = $word1 < $word2 ? $word1 . '|' . $word2 : $word2 . '|' . $word1;

        if (isset($this->similarityCache[$cacheKey])) {
            return $this->similarityCache[$cacheKey];
        }

        $maxLength = max(strlen($word1), strlen($word2));
        if ($maxLength === 0) {
            return 0;
        }

        $distance = levenshtein($word1, $word2);
        $similarity = 1 - ($distance / $maxLength);

        // کش کردن نتیجه (محدود کردن اندازه کش)
        if (count($this->similarityCache) < 10000) {
            $this->similarityCache[$cacheKey] = $similarity;
        }

        return $similarity;
    }

    /**
     * بررسی تطابق کلمه کامل (بهینه‌شده)
     */
    private function isCompleteWordMatch(string $text, string $keyword): bool
    {
        return str_contains($text, $keyword) &&
            preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $text) === 1;
    }

    /**
     * نرمال‌سازی متن (بهینه‌شده با کش)
     */
    private function normalizeText(string $text): string
    {
        $cacheKey = md5($text);

        if (isset($this->normalizedTextCache[$cacheKey])) {
            return $this->normalizedTextCache[$cacheKey];
        }

        // تبدیل به حروف کوچک
        $normalized = mb_strtolower($text, 'UTF-8');

        // حذف کاراکترهای خاص اما حفظ حروف و اعداد
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

        // جایگزینی چندین فاصله با یک فاصله
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        // کش کردن نتیجه (محدود کردن اندازه کش)
        if (count($this->normalizedTextCache) < 1000) {
            $this->normalizedTextCache[$cacheKey] = $normalized;
        }

        return $normalized;
    }

    /**
     * تست تشخیص برند با جزئیات کامل
     */
    public function testBrandDetection(string $text): array
    {
        $startTime = microtime(true);

        $result = [
            'input_text' => $text,
            'normalized_text' => $this->normalizeText($text),
            'extracted_words' => $this->extractValidWords($this->normalizeText($text)),
            'detected_brand' => null,
            'detection_method' => null,
            'database_available' => $this->isDatabaseAvailable,
            'processing_time' => 0,
            'detailed_scores' => [],
            'exact_matches' => [],
            'brand_connection' => $this->brandConnection,
            'statistics' => []
        ];

        if (!$this->checkDatabaseAvailability()) {
            $result['error'] = 'Database not available';
            $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
            return $result;
        }

        $brands = $this->getAllBrands();
        if (empty($brands)) {
            $result['error'] = 'No brands found in database';
            $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
            return $result;
        }

        // بررسی تطابق دقیق
        $exactMatch = $this->findExactMatch($text, $brands);
        if ($exactMatch) {
            $result['detected_brand'] = $exactMatch['name'];
            $result['detection_method'] = 'exact_match';
            $result['exact_matches'][] = [
                'brand_name' => $exactMatch['name'],
                'matched_field' => 'multiple_fields',
                'confidence' => 1.0
            ];
        }

        // محاسبه امتیازات تفصیلی برای همه برندها
        $detailedScores = [];
        $normalizedText = $this->normalizeText($text);

        foreach ($brands as $brand) {
            $score = $this->calculateAdvancedBrandScore($normalizedText, $brand);
            $detailedScores[] = [
                'brand_name' => $brand['name'],
                'score' => round($score, 6),
                'brand_data' => $brand,
                'field_scores' => $this->getDetailedFieldScores($normalizedText, $brand)
            ];
        }

        // مرتب‌سازی بر اساس امتیاز
        usort($detailedScores, fn($a, $b) => $b['score'] <=> $a['score']);

        $result['detailed_scores'] = $detailedScores;

        // اگر تطابق دقیق نداشتیم، بهترین تطابق فازی را بررسی کن
        if (!$exactMatch && !empty($detailedScores)) {
            $bestScore = $detailedScores[0]['score'];
            $dynamicThreshold = $this->calculateDynamicThreshold($bestScore);

            if ($bestScore >= $dynamicThreshold) {
                $result['detected_brand'] = $detailedScores[0]['brand_name'];
                $result['detection_method'] = 'fuzzy_match';
            }
        }

        // آمار تفصیلی
        $result['statistics'] = [
            'total_brands_checked' => count($brands),
            'scores_above_threshold' => count(array_filter($detailedScores, fn($item) => $item['score'] >= 0.2)),
            'highest_score' => !empty($detailedScores) ? $detailedScores[0]['score'] : 0,
            'average_score' => !empty($detailedScores) ? array_sum(array_column($detailedScores, 'score')) / count($detailedScores) : 0,
            'dynamic_threshold' => !empty($detailedScores) ? $this->calculateDynamicThreshold($detailedScores[0]['score']) : 0
        ];

        $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
        return $result;
    }

    /**
     * دریافت امتیازات تفصیلی فیلدها
     */
    private function getDetailedFieldScores(string $text, array $brand): array
    {
        $scores = [];

        $fields = [
            'name' => ['weight' => 4.0, 'method' => 'precise'],
            'nameSeo' => ['weight' => 3.5, 'method' => 'precise'],
            'slug' => ['weight' => 3.0, 'method' => 'precise'],
            'keyword' => ['weight' => 2.0, 'method' => 'keyword'],
            'body' => ['weight' => 1.0, 'method' => 'context'],
            'bodySeo' => ['weight' => 0.5, 'method' => 'context']
        ];

        foreach ($fields as $field => $config) {
            if (empty($brand[$field])) {
                continue;
            }

            $value = $brand[$field];
            $score = match ($config['method']) {
                'keyword' => $this->calculateKeywordMatch($text, $value),
                'context' => $this->calculateContextMatch($text, $value),
                default => $this->calculatePreciseMatch($text, $value, $field)
            };

            $scores[$field] = [
                'value' => $field === 'body' || $field === 'bodySeo'
                    ? mb_substr($value, 0, 100) . '...'
                    : $value,
                'score' => round($score, 4),
                'weight' => $config['weight']
            ];
        }

        return $scores;
    }

    /**
     * لاگ کردن پیام‌ها
     */
    private function log(string $message, ?string $color = null): void
    {
        $formattedMessage = $color ? $color . $message . "\033[0m" : $message;

        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $formattedMessage);
        } else {
            $logFile = storage_path('logs/brand_detection_' . date('Ymd') . '.log');
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
        }
    }
}
