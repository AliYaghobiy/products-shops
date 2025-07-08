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
    private const EXACT_MATCH_THRESHOLD = 0.7;      // آستانه برای تطابق دقیق
    private const PARTIAL_MATCH_THRESHOLD = 0.6;    // آستانه برای تطابق جزئی
    private const FUZZY_MATCH_THRESHOLD = 0.9;      // آستانه برای تطابق فازی
    private const MIN_WORD_LENGTH = 3;              // حداقل طول کلمه برای بررسی

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

            if ($detectedBrand) {
                return $detectedBrand['name'];
            } else {
                return null;
            }

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
            if (!empty($brand['name'])) {
                $brandName = $this->normalizeText($brand['name']);
                $brandWords = $this->extractValidWords($brandName);

                if ($this->isExactWordMatch($textWords, $brandWords)) {
                    return $brand;
                }
            }

            // بررسی تطابق دقیق نام SEO
            if (!empty($brand['nameSeo'])) {
                $brandNameSeo = $this->normalizeText($brand['nameSeo']);
                $brandSeoWords = $this->extractValidWords($brandNameSeo);

                if ($this->isExactWordMatch($textWords, $brandSeoWords)) {
                    return $brand;
                }
            }

            // بررسی تطابق دقیق slug
            if (!empty($brand['slug'])) {
                $brandSlug = $this->normalizeText($brand['slug']);
                $brandSlugWords = $this->extractValidWords($brandSlug);

                if ($this->isExactWordMatch($textWords, $brandSlugWords)) {
                    return $brand;
                }
            }

            // بررسی تطابق کامل کلمه کلیدی
            if (!empty($brand['keyword'])) {
                $keywords = array_filter(array_map('trim', explode(',', $brand['keyword'])));
                foreach ($keywords as $keyword) {
                    $normalizedKeyword = $this->normalizeText($keyword);
                    $keywordWords = $this->extractValidWords($normalizedKeyword);
                    if ($this->isExactWordMatch($textWords, $keywordWords)) {
                        return $brand;
                    }
                }
            }
        }

        return null;
    }

    /**
     * بررسی تطابق دقیق کلمات
     */
    private function isExactWordMatch(array $textWords, array $brandWords): bool
    {
        if (empty($brandWords)) {
            return false;
        }

        $brandWords = array_filter($brandWords, function ($word) {
            return strlen($word) >= self::MIN_WORD_LENGTH;
        });

        if (empty($brandWords)) {
            return false;
        }

        // بازسازی اندیس‌های آرایه بعد از فیلتر کردن
        $brandWords = array_values($brandWords);

        // اگر برند فقط یک کلمه دارد، روش قبلی را استفاده کن
        if (count($brandWords) == 1) {
            return $this->hasSingleWordMatch($textWords, $brandWords[0]);
        }

        // برای برندهای چندکلمه‌ای، فقط دنباله کاملاً پیوسته و به ترتیب پذیرفته می‌شود
        $sequentialMatch = $this->hasSequentialMatch($textWords, $brandWords);
        if ($sequentialMatch) {
            return true;
        }

        return false;
    }

    /**
     * بررسی اینکه آیا کلمات برند در متن نسبتاً نزدیک هم قرار دارند
     * این تابع جدید اضافه شده است
     */
    private function areWordsReasonablyClose(array $textWords, array $brandWords): bool
    {
        $brandPositions = [];

        // یافتن موقعیت‌های هر کلمه برند در متن
        foreach ($brandWords as $brandWord) {
            foreach ($textWords as $index => $textWord) {
                if ($brandWord === $textWord) {
                    $brandPositions[$brandWord] = $index;
                    break;
                }
            }
        }

        // اگر تمام کلمات برند یافت نشدند
        if (count($brandPositions) !== count($brandWords)) {
            return false;
        }

        $positions = array_values($brandPositions);
        sort($positions);

        // محاسبه حداکثر فاصله بین کلمات برند
        $maxDistance = max($positions) - min($positions);

        // اگر کلمات برند در بیش از 5 کلمه فاصله باشند، رد شود
        // این عدد قابل تنظیم است و بر اساس نیاز می‌توان آن را تغییر داد
        $maxAllowedDistance = 5;

        $isClose = $maxDistance <= $maxAllowedDistance;

        return $isClose;
    }

    /**
     * بررسی تطابق دنباله‌ای کلمات برند در متن (بهبود یافته برای جلوگیری از فاصله غیرمجاز)
     */
    private function hasSequentialMatch(array $textWords, array $brandWords): bool
    {
        $textWordsCount = count($textWords);
        $brandWordsCount = count($brandWords);

        if ($brandWordsCount > $textWordsCount) {
            return false;
        }

        // جستجوی دنباله کاملاً پیوسته و به ترتیب
        for ($i = 0; $i <= $textWordsCount - $brandWordsCount; $i++) {
            $isSequentialMatch = true;

            for ($j = 0; $j < $brandWordsCount; $j++) {
                if ($textWords[$i + $j] !== $brandWords[$j]) {
                    $isSequentialMatch = false;
                    break;
                }
            }

        }

        return false;
    }

    /**
     * بررسی تطابق یک کلمه
     */
    private function hasSingleWordMatch(array $textWords, string $brandWord): bool
    {
        foreach ($textWords as $textWord) {
            if ($brandWord === $textWord) {
                return true;
            }
        }
        return false;
    }

    /**
     * بررسی تطابق دنباله‌ای با حداکثر یک کلمه فاصله
     */
    private function hasSequentialMatchWithGap(array $textWords, array $brandWords): bool
    {
        $textWordsCount = count($textWords);
        $brandWordsCount = count($brandWords);

        if ($brandWordsCount < 2) {
            return false; // برای کلمات تکی این بررسی لازم نیست
        }

        // جستجوی اولین کلمه برند
        for ($i = 0; $i < $textWordsCount; $i++) {
            if ($textWords[$i] === $brandWords[0]) {
                // بررسی ادامه دنباله با حداکثر یک فاصله
                if ($this->checkSequenceWithGap($textWords, $brandWords, $i)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * بررسی دنباله از موقعیت مشخص با در نظر گیری فاصله
     */
    private function checkSequenceWithGap(array $textWords, array $brandWords, int $startIndex): bool
    {
        $textWordsCount = count($textWords);
        $brandWordsCount = count($brandWords);
        $currentTextIndex = $startIndex;
        $foundWords = [$brandWords[0]]; // اولین کلمه پیدا شده

        // جستجوی کلمات بعدی برند
        for ($brandIndex = 1; $brandIndex < $brandWordsCount; $brandIndex++) {
            $wordFound = false;

            // جستجو در حداکثر 3 کلمه بعدی (1 کلمه فاصله + کلمه هدف + 1 کلمه اضافی)
            for ($gap = 1; $gap <= 3 && ($currentTextIndex + $gap) < $textWordsCount; $gap++) {
                if ($textWords[$currentTextIndex + $gap] === $brandWords[$brandIndex]) {
                    $currentTextIndex = $currentTextIndex + $gap;
                    $foundWords[] = $brandWords[$brandIndex];
                    $wordFound = true;
                    break;
                }
            }

            if (!$wordFound) {
                return false;
            }
        }

        // اگر همه کلمات برند یافت شدند
        if (count($foundWords) === $brandWordsCount) {
            return true;
        }

        return false;
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
                ->map(function ($brand) {
                    return (array)$brand;
                })
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
        $detailedScores = [];

        foreach ($brands as $brand) {
            $score = $this->calculateAdvancedBrandScore($text, $brand);

            $detailedScores[] = [
                'brand' => $brand['name'],
                'score' => round($score, 4),
                'brand_data' => $brand
            ];

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $brand;
            }
        }

        // مرتب‌سازی و نمایش بهترین نتایج
        usort($detailedScores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // آستانه تطابق متحرک یا پذیرش تطابق کامل
        $dynamicThreshold = $this->calculateDynamicThreshold($highestScore);
        if ($highestScore >= 1.0 || $highestScore >= $dynamicThreshold) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * محاسبه آستانه متحرک
     */
    private function calculateDynamicThreshold(float $highestScore): float
    {
        if ($highestScore >= 0.8) {
            return 0.8;
        } elseif ($highestScore >= 0.6) {
            return 0.6;
        } elseif ($highestScore >= 0.4) {
            return 0.4;
        } elseif ($highestScore >= 0.2) {
            return 0.2;
        }

        return 0.1; // حداقل آستانه
    }

    /**
     * محاسبه امتیاز پیشرفته برند
     */
    private function calculateAdvancedBrandScore(string $text, array $brand): float
    {
        $totalScore = 0;
        $maxPossibleScore = 0;

        // امتیاز نام اصلی (وزن: 4)
        if (!empty($brand['name'])) {
            $nameScore = $this->calculatePreciseMatch($text, $brand['name'], 'name');
            $weightedScore = $nameScore * 4;
            $totalScore += $weightedScore;
            $maxPossibleScore += 4;

        }

        // امتیاز نام SEO (وزن: 3.5)
        if (!empty($brand['nameSeo'])) {
            $nameSeoScore = $this->calculatePreciseMatch($text, $brand['nameSeo'], 'nameSeo');
            $weightedScore = $nameSeoScore * 3.5;
            $totalScore += $weightedScore;
            $maxPossibleScore += 3.5;
        }

        // امتیاز slug (وزن: 3)
        if (!empty($brand['slug'])) {
            $slugScore = $this->calculatePreciseMatch($text, $brand['slug'], 'slug');
            $weightedScore = $slugScore * 3;
            $totalScore += $weightedScore;
            $maxPossibleScore += 3;
        }

        // امتیاز کلمات کلیدی (وزن: 2)
        if (!empty($brand['keyword'])) {
            $keywordScore = $this->calculateKeywordMatch($text, $brand['keyword']);
            $weightedScore = $keywordScore * 2;
            $totalScore += $weightedScore;
            $maxPossibleScore += 2;
        }

        // امتیاز توضیحات (وزن: 1)
        if (!empty($brand['body'])) {
            $bodyScore = $this->calculateContextMatch($text, $brand['body']);
            $weightedScore = $bodyScore * 1;
            $totalScore += $weightedScore;
            $maxPossibleScore += 1;
        }

        // امتیاز توضیحات SEO (وزن: 0.5)
        if (!empty($brand['bodySeo'])) {
            $bodySeoScore = $this->calculateContextMatch($text, $brand['bodySeo']);
            $weightedScore = $bodySeoScore * 0.5;
            $totalScore += $weightedScore;
            $maxPossibleScore += 0.5;
        }

        // نرمال‌سازی امتیاز
        $finalScore = $maxPossibleScore > 0 ? $totalScore / $maxPossibleScore : 0;


        return $finalScore;
    }

    /**
     * محاسبه تطابق دقیق بهبود یافته
     */
    private function calculatePreciseMatch(string $text, string $brandValue, string $fieldType): float
    {
        $normalizedText = $this->normalizeText($text);
        $normalizedBrand = $this->normalizeText($brandValue);

        $textWords = $this->extractValidWords($normalizedText);
        $brandWords = $this->extractValidWords($normalizedBrand);

        if (empty($brandWords)) {
            return 0;
        }

        // اگر برند تک‌کلمه‌ای است، منطق قبلی را اجرا کن
        if (count($brandWords) == 1) {
            $brandWord = $brandWords[0];
            $bestWordScore = 0;
            $bestMatchWord = '';

            foreach ($textWords as $textWord) {
                // تطابق کامل (امتیاز 1)
                if ($brandWord === $textWord) {
                    $bestWordScore = 1.0;
                    $bestMatchWord = $textWord;
                    break;
                }

                // تطابق فازی برای کلمات طولانی
                if (strlen($brandWord) >= 4 && strlen($textWord) >= 4) {
                    $similarity = $this->calculateSimilarity($brandWord, $textWord);
                    if ($similarity >= self::FUZZY_MATCH_THRESHOLD && $similarity > $bestWordScore) {
                        $bestWordScore = $similarity;
                        $bestMatchWord = $textWord;
                    }
                }
            }

            return $bestWordScore;
        }

        // برای برندهای چندکلمه‌ای، فقط دنباله پیوسته و به ترتیب پذیرفته می‌شود
        if ($this->hasSequentialMatch($textWords, $brandWords)) {
            return 1.0;
        }

        return 0;
    }

    /**
     * محاسبه تطابق کلمات کلیدی بهبود یافته
     */
    private function calculateKeywordMatch(string $text, string $keywords): float
    {
        $normalizedText = $this->normalizeText($text);
        $keywordList = array_filter(array_map('trim', explode(',', $keywords)));

        if (empty($keywordList)) {
            return 0;
        }

        $matches = 0;
        $totalKeywords = count($keywordList);
        $textWords = $this->extractValidWords($normalizedText);

        foreach ($keywordList as $keyword) {
            $normalizedKeyword = $this->normalizeText($keyword);

            if (strlen($normalizedKeyword) < 3) {
                $totalKeywords--; // کلمات کوتاه را نادیده بگیر
                continue;
            }

            $keywordFound = false;

            // جستجوی تطابق کامل در متن
            if ($this->isCompleteWordMatch($normalizedText, $normalizedKeyword)) {
                $matches += 1.0;
                $keywordFound = true;
            } else {
                // جستجوی تطابق در کلمات جداگانه
                foreach ($textWords as $textWord) {
                    if ($normalizedKeyword === $textWord) {
                        $matches += 1.0;
                        $keywordFound = true;
                        break;
                    }

                    // تطابق فازی برای کلمات طولانی
                    if (strlen($normalizedKeyword) >= 4 && strlen($textWord) >= 4) {
                        $similarity = $this->calculateSimilarity($normalizedKeyword, $textWord);
                        if ($similarity >= self::FUZZY_MATCH_THRESHOLD) {
                            $matches += $similarity;
                            $keywordFound = true;
                            break;
                        }
                    }
                }
            }

        }

        return $totalKeywords > 0 ? min($matches / $totalKeywords, 1.0) : 0;
    }

    /**
     * محاسبه تطابق متنی (برای توضیحات)
     */
    private function calculateContextMatch(string $text, string $context): float
    {
        $normalizedText = $this->normalizeText($text);
        $normalizedContext = $this->normalizeText($context);

        $textWords = array_filter($this->extractValidWords($normalizedText), function ($word) {
            return strlen($word) >= 4; // فقط کلمات طولانی
        });

        $contextWords = array_filter($this->extractValidWords($normalizedContext), function ($word) {
            return strlen($word) >= 4; // فقط کلمات طولانی
        });

        if (empty($textWords) || empty($contextWords)) {
            return 0;
        }

        $matches = 0;
        foreach ($textWords as $textWord) {
            foreach ($contextWords as $contextWord) {
                $similarity = $this->calculateSimilarity($textWord, $contextWord);
                if ($similarity >= 0.85) { // آستانه بالاتر برای متن
                    $matches += $similarity;
                    break;
                }
            }
        }

        return min($matches / count($textWords), 1.0);
    }

    /**
     * استخراج کلمات معتبر بهبود یافته
     */
    private function extractValidWords(string $text): array
    {
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

        return array_unique($validWords);
    }

    /**
     * بررسی کلمات توقف
     */
    private function isStopWord(string $word): bool
    {
        $word = mb_strtolower($word, 'UTF-8');

        $stopWords = [
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
        ];

        return in_array($word, $stopWords);
    }

    /**
     * محاسبه شباهت بین دو کلمه
     */
    private function calculateSimilarity(string $word1, string $word2): float
    {
        if ($word1 === $word2) {
            return 1.0;
        }

        $maxLength = max(strlen($word1), strlen($word2));
        if ($maxLength === 0) {
            return 0;
        }

        $distance = levenshtein($word1, $word2);
        return 1 - ($distance / $maxLength);
    }

    /**
     * بررسی تطابق کلمه کامل
     */
    private function isCompleteWordMatch(string $text, string $keyword): bool
    {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/u';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * نرمال‌سازی متن
     */
    private function normalizeText(string $text): string
    {
        // تبدیل به حروف کوچک
        $text = mb_strtolower($text, 'UTF-8');

        // حذف کاراکترهای خاص اما حفظ حروف و اعداد
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        // جایگزینی چندین فاصله با یک فاصله
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
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
        usort($detailedScores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

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
            'scores_above_threshold' => count(array_filter($detailedScores, function ($item) {
                return $item['score'] >= 0.2;
            })),
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

        if (!empty($brand['name'])) {
            $scores['name'] = [
                'value' => $brand['name'],
                'score' => round($this->calculatePreciseMatch($text, $brand['name'], 'name'), 4),
                'weight' => 4.0
            ];
        }

        if (!empty($brand['nameSeo'])) {
            $scores['nameSeo'] = [
                'value' => $brand['nameSeo'],
                'score' => round($this->calculatePreciseMatch($text, $brand['nameSeo'], 'nameSeo'), 4),
                'weight' => 3.5
            ];
        }

        if (!empty($brand['slug'])) {
            $scores['slug'] = [
                'value' => $brand['slug'],
                'score' => round($this->calculatePreciseMatch($text, $brand['slug'], 'slug'), 4),
                'weight' => 3.0
            ];
        }

        if (!empty($brand['keyword'])) {
            $scores['keyword'] = [
                'value' => $brand['keyword'],
                'score' => round($this->calculateKeywordMatch($text, $brand['keyword']), 4),
                'weight' => 2.0
            ];
        }

        if (!empty($brand['body'])) {
            $scores['body'] = [
                'value' => mb_substr($brand['body'], 0, 100) . '...',
                'score' => round($this->calculateContextMatch($text, $brand['body']), 4),
                'weight' => 1.0
            ];
        }

        if (!empty($brand['bodySeo'])) {
            $scores['bodySeo'] = [
                'value' => mb_substr($brand['bodySeo'], 0, 100) . '...',
                'score' => round($this->calculateContextMatch($text, $brand['bodySeo']), 4),
                'weight' => 0.5
            ];
        }

        return $scores;
    }

    /**
     * لاگ کردن پیام‌ها
     */
    private function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $formattedMessage);
        } else {
            $logFile = storage_path('logs/brand_detection_' . date('Ymd') . '.log');
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
        }
    }
}
