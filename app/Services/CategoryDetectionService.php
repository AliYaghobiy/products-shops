<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoryDetectionService
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_CYAN = "\033[1;96m";
    private const COLOR_WHITE = "\033[1;97m";

    private $outputCallback = null;
    private ?array $categoriesCache = null;
    private bool $isDatabaseAvailable = true;
    private string $categoryConnection = 'mysql';

    // تنظیمات امتیازدهی بر اساس تطابق کلمات - بهبود یافته
    private const PERFECT_COMPLETE_MATCH_SCORE = 100.0;    // تطابق کامل و دقیق تمام کلمات
    private const COMPLETE_WORD_COVERAGE_SCORE = 80.0;     // پوشش کامل کلمات هدف
    private const PARTIAL_WORD_MATCH_SCORE = 50.0;        // تطابق جزئی کلمات
    private const SINGLE_WORD_MATCH_SCORE = 20.0;         // تطابق تک کلمه
    private const MINIMUM_ACCEPTABLE_SCORE = 60.0;        // حداقل امتیاز قابل قبول
    private const MINIMUM_WORD_LENGTH = 3;
    private const EXACT_MATCH_BONUS = 20.0;               // امتیاز اضافی برای تطابق دقیق

    // کش برای بهبود عملکرد
    private array $normalizedTextCache = [];
    private array $wordExtractionCache = [];
    private array $categoryWordCache = [];
    private ?array $stopWordsCache = null;

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    /**
     * شناسایی دسته‌بندی‌ها از متن ورودی با تطابق دقیق کلمات
     * @param string $text
     * @return array|null
     */
    public function detectCategoriesFromText(string $text): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        try {
            if (!$this->checkDatabaseAvailability()) {
                $this->log("⚠️ جدول categories در دسترس نیست", self::COLOR_YELLOW);
                return null;
            }

            $categories = $this->getAllCategories();
            if (empty($categories)) {
                $this->log("⚠️ هیچ دسته‌بندی در دیتابیس یافت نشد", self::COLOR_YELLOW);
                return null;
            }

            $detectedCategories = [];
            $textCategories = array_filter(array_map('trim', explode(',', $text)));

            foreach ($textCategories as $categoryText) {
                $this->log("🔍 بررسی متن: '{$categoryText}'", self::COLOR_BLUE);

                // یافتن بهترین تطابق با الگوریتم بهبود یافته
                $bestMatch = $this->findBestCategoryMatch($categoryText, $categories);

                if ($bestMatch && $bestMatch['score'] >= self::MINIMUM_ACCEPTABLE_SCORE) {
                    $detectedCategories[] = $bestMatch['category']['name'];
                    $this->log("✅ بهترین تطابق: {$bestMatch['category']['name']} (امتیاز: {$bestMatch['score']})", self::COLOR_GREEN);
                } else {
                    $this->log("❌ هیچ تطابق قابل قبولی یافت نشد", self::COLOR_RED);
                }
            }

            return !empty($detectedCategories) ? array_unique($detectedCategories) : null;

        } catch (\Exception $e) {
            $this->log("❌ خطا در تشخیص دسته‌بندی: " . $e->getMessage(), self::COLOR_RED);
            return null;
        }
    }

    /**
     * یافتن بهترین تطابق دسته‌بندی
     */
    private function findBestCategoryMatch(string $text, array $categories): ?array
    {
        $inputWords = $this->extractCleanWords($text);

        if (empty($inputWords)) {
            return null;
        }

        $this->log("🔤 کلمات ورودی: [" . implode(', ', $inputWords) . "]", self::COLOR_CYAN);

        $scoredCategories = [];

        foreach ($categories as $category) {
            $score = $this->calculatePreciseWordScore($inputWords, $category);

            if ($score > 0) {
                $scoredCategories[] = [
                    'category' => $category,
                    'score' => $score,
                    'match_details' => $this->getDetailedMatchInfo($inputWords, $category)
                ];

                $this->log("📊 دسته '{$category['name']}': امتیاز {$score}", self::COLOR_WHITE);
            }
        }

        if (empty($scoredCategories)) {
            return null;
        }

        // مرتب‌سازی بر اساس امتیاز (نزولی)
        usort($scoredCategories, function($a, $b) {
            // اگر امتیازها برابر باشند، ترجیح با دسته‌ای که کلمات کمتری دارد
            if ($a['score'] === $b['score']) {
                $aWordCount = $this->getTotalCategoryWords($a['category']);
                $bWordCount = $this->getTotalCategoryWords($b['category']);
                return $aWordCount <=> $bWordCount; // کمتر بهتر
            }
            return $b['score'] <=> $a['score'];
        });

        return $scoredCategories[0];
    }

    /**
     * محاسبه امتیاز دقیق بر اساس تطابق کلمات
     */
    private function calculatePreciseWordScore(array $inputWords, array $category): float
    {
        $totalScore = 0;
        $inputWordCount = count($inputWords);

        // وزن‌های فیلدها
        $fieldsWithWeights = [
            'name' => 15.0,
            'nameSeo' => 12.0,
            'slug' => 10.0,
            'keyword' => 14.0,
            'body' => 5.0,
            'bodySeo' => 3.0
        ];

        $bestFieldScore = 0;
        $bestFieldName = '';

        foreach ($fieldsWithWeights as $field => $weight) {
            if (empty($category[$field])) {
                continue;
            }

            $fieldScore = $this->calculateFieldPreciseScore($inputWords, $category[$field], $field);

            if ($fieldScore > 0) {
                $weightedScore = $fieldScore * $weight;
                $this->log("   📍 فیلد {$field}: امتیاز خام={$fieldScore}, وزنی={$weightedScore}", self::COLOR_WHITE);

                // ثبت بهترین فیلد
                if ($fieldScore > $bestFieldScore) {
                    $bestFieldScore = $fieldScore;
                    $bestFieldName = $field;
                }

                $totalScore += $weightedScore;
            }
        }

        // امتیاز اضافی برای بهترین تطابق
        if ($bestFieldScore >= self::PERFECT_COMPLETE_MATCH_SCORE) {
            $totalScore += self::EXACT_MATCH_BONUS;
            $this->log("   🎯 امتیاز اضافی تطابق کامل: +{$this->EXACT_MATCH_BONUS}", self::COLOR_GREEN);
        }

        return round($totalScore, 2);
    }

    /**
     * محاسبه امتیاز دقیق فیلد
     */
    private function calculateFieldPreciseScore(array $inputWords, string $fieldValue, string $fieldType): float
    {
        if ($fieldType === 'keyword') {
            return $this->calculateKeywordPreciseScore($inputWords, $fieldValue);
        }

        $fieldWords = $this->extractCleanWords($fieldValue);

        if (empty($fieldWords)) {
            return 0;
        }

        return $this->calculateWordMatchScore($inputWords, $fieldWords);
    }

    /**
     * محاسبه امتیاز تطابق کلمات
     */
    private function calculateWordMatchScore(array $inputWords, array $targetWords): float
    {
        $inputCount = count($inputWords);
        $targetCount = count($targetWords);

        // یافتن کلمات مشترک
        $commonWords = array_intersect($inputWords, $targetWords);
        $commonCount = count($commonWords);

        if ($commonCount === 0) {
            return 0;
        }

        $this->log("      🔍 کلمات مشترک: [" . implode(', ', $commonWords) . "]", self::COLOR_CYAN);

        // حالت 1: تطابق کامل و دقیق (همه کلمات ورودی = همه کلمات هدف)
        if ($commonCount === $inputCount && $commonCount === $targetCount) {
            $this->log("      ✅ تطابق کامل و دقیق", self::COLOR_GREEN);
            return self::PERFECT_COMPLETE_MATCH_SCORE;
        }

        // حالت 2: پوشش کامل کلمات ورودی (همه کلمات ورودی در هدف موجودند)
        if ($commonCount === $inputCount) {
            $coverageRatio = $commonCount / $targetCount;
            $score = self::COMPLETE_WORD_COVERAGE_SCORE * $coverageRatio;
            $this->log("      ✅ پوشش کامل ورودی: {$commonCount}/{$inputCount} (نسبت هدف: {$coverageRatio})", self::COLOR_GREEN);
            return $score;
        }

        // حالت 3: پوشش کامل کلمات هدف (همه کلمات هدف در ورودی موجودند)
        if ($commonCount === $targetCount) {
            $inputCoverageRatio = $commonCount / $inputCount;
            $score = self::COMPLETE_WORD_COVERAGE_SCORE * 0.9 * $inputCoverageRatio;
            $this->log("      ✅ پوشش کامل هدف: {$commonCount}/{$targetCount} (نسبت ورودی: {$inputCoverageRatio})", self::COLOR_GREEN);
            return $score;
        }

        // حالت 4: تطابق جزئی
        $inputRatio = $commonCount / $inputCount;
        $targetRatio = $commonCount / $targetCount;
        $averageRatio = ($inputRatio + $targetRatio) / 2;

        $score = self::PARTIAL_WORD_MATCH_SCORE * $averageRatio;

        $this->log("      ⚠️ تطابق جزئی: ورودی={$inputRatio}, هدف={$targetRatio}, میانگین={$averageRatio}", self::COLOR_YELLOW);

        return $score;
    }

    /**
     * محاسبه امتیاز دقیق کلمات کلیدی
     */
    private function calculateKeywordPreciseScore(array $inputWords, string $keywords): float
    {
        $keywordList = array_filter(array_map('trim', explode(',', $keywords)));

        if (empty($keywordList)) {
            return 0;
        }

        $maxScore = 0;

        foreach ($keywordList as $keyword) {
            $keywordWords = $this->extractCleanWords($keyword);

            if (empty($keywordWords)) {
                continue;
            }

            $score = $this->calculateWordMatchScore($inputWords, $keywordWords);

            if ($score > $maxScore) {
                $maxScore = $score;
                $this->log("      🔑 کلمه کلیدی '{$keyword}': امتیاز {$score}", self::COLOR_PURPLE);
            }
        }

        return $maxScore;
    }

    /**
     * استخراج کلمات تمیز و معتبر - بهبود یافته
     */
    private function extractCleanWords(string $text): array
    {
        $cacheKey = md5($text);

        if (isset($this->wordExtractionCache[$cacheKey])) {
            return $this->wordExtractionCache[$cacheKey];
        }

        // نرمال‌سازی متن
        $normalizedText = $this->normalizeText($text);

        // تقسیم به کلمات با علائم بیشتر
        $words = preg_split('/[\s\-_\.\/\|\(\)\[\]\{\}\,\;\:\"\'\`\~\!\@\#\$\%\^\&\*\+\=]+/', $normalizedText, -1, PREG_SPLIT_NO_EMPTY);

        $cleanWords = [];

        foreach ($words as $word) {
            // حذف کاراکترهای غیرضروری
            $cleanWord = preg_replace('/[^\p{L}\p{N}]/u', '', $word);

            // فیلتر کردن کلمات
            if (strlen($cleanWord) >= self::MINIMUM_WORD_LENGTH &&
                !is_numeric($cleanWord) &&
                !$this->isStopWord($cleanWord) &&
                preg_match('/\p{L}/u', $cleanWord)) {

                $cleanWords[] = mb_strtolower($cleanWord, 'UTF-8');
            }
        }

        $result = array_values(array_unique($cleanWords));

        // کش کردن نتیجه
        if (count($this->wordExtractionCache) < 1000) {
            $this->wordExtractionCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * دریافت جزئیات تطابق تفصیلی
     */
    private function getDetailedMatchInfo(array $inputWords, array $category): array
    {
        $details = [];

        $fieldsToCheck = [
            'name' => $category['name'] ?? '',
            'nameSeo' => $category['nameSeo'] ?? '',
            'slug' => $category['slug'] ?? '',
            'keyword' => $category['keyword'] ?? '',
            'body' => $category['body'] ?? '',
            'bodySeo' => $category['bodySeo'] ?? ''
        ];

        foreach ($fieldsToCheck as $fieldName => $fieldValue) {
            if (empty($fieldValue)) {
                continue;
            }

            if ($fieldName === 'keyword') {
                $keywords = array_filter(array_map('trim', explode(',', $fieldValue)));
                foreach ($keywords as $keyword) {
                    $keywordWords = $this->extractCleanWords($keyword);
                    $matches = array_intersect($inputWords, $keywordWords);
                    if (!empty($matches)) {
                        $details[] = [
                            'field' => $fieldName,
                            'value' => $keyword,
                            'field_words' => $keywordWords,
                            'matched_words' => array_values($matches),
                            'match_ratio' => count($matches) / count($keywordWords),
                            'coverage_ratio' => count($matches) / count($inputWords),
                            'match_type' => $this->getMatchType($inputWords, $keywordWords, $matches)
                        ];
                    }
                }
            } else {
                $fieldWords = $this->extractCleanWords($fieldValue);
                $matches = array_intersect($inputWords, $fieldWords);
                if (!empty($matches)) {
                    $details[] = [
                        'field' => $fieldName,
                        'value' => $fieldValue,
                        'field_words' => $fieldWords,
                        'matched_words' => array_values($matches),
                        'match_ratio' => count($matches) / count($fieldWords),
                        'coverage_ratio' => count($matches) / count($inputWords),
                        'match_type' => $this->getMatchType($inputWords, $fieldWords, $matches)
                    ];
                }
            }
        }

        return $details;
    }

    /**
     * تعیین نوع تطابق
     */
    private function getMatchType(array $inputWords, array $fieldWords, array $matches): string
    {
        $inputCount = count($inputWords);
        $fieldCount = count($fieldWords);
        $matchCount = count($matches);

        if ($matchCount === $inputCount && $matchCount === $fieldCount) {
            return 'perfect_match';
        } elseif ($matchCount === $inputCount) {
            return 'complete_input_coverage';
        } elseif ($matchCount === $fieldCount) {
            return 'complete_field_coverage';
        } else {
            return 'partial_match';
        }
    }

    /**
     * محاسبه تعداد کل کلمات دسته‌بندی
     */
    private function getTotalCategoryWords(array $category): int
    {
        $totalWords = 0;
        $fieldsToCheck = ['name', 'nameSeo', 'slug', 'keyword'];

        foreach ($fieldsToCheck as $field) {
            if (!empty($category[$field])) {
                if ($field === 'keyword') {
                    $keywords = array_filter(array_map('trim', explode(',', $category[$field])));
                    foreach ($keywords as $keyword) {
                        $totalWords += count($this->extractCleanWords($keyword));
                    }
                } else {
                    $totalWords += count($this->extractCleanWords($category[$field]));
                }
            }
        }

        return $totalWords;
    }

    /**
     * بررسی دسترسی به دیتابیس
     */
    private function checkDatabaseAvailability(): bool
    {
        try {
            if (!Schema::connection($this->categoryConnection)->hasTable('categories')) {
                if (!Schema::hasTable('categories')) {
                    $this->isDatabaseAvailable = false;
                    return false;
                } else {
                    $this->categoryConnection = DB::getDefaultConnection();
                }
            }

            DB::connection($this->categoryConnection)->table('categories')->limit(1)->get();
            $this->isDatabaseAvailable = true;
            return true;

        } catch (\Exception $e) {
            $this->log("❌ خطا در دسترسی به جدول categories: " . $e->getMessage(), self::COLOR_RED);
            $this->isDatabaseAvailable = false;
            return false;
        }
    }

    /**
     * دریافت تمام دسته‌بندی‌ها
     */
    private function getAllCategories(): array
    {
        if ($this->categoriesCache !== null) {
            return $this->categoriesCache;
        }

        try {
            if (!$this->isDatabaseAvailable) {
                return [];
            }

            $categories = DB::connection($this->categoryConnection)->table('categories')
                ->select('id', 'name', 'nameSeo', 'slug', 'body', 'bodySeo', 'keyword')
                ->get()
                ->map(fn($category) => (array)$category)
                ->toArray();

            $this->categoriesCache = $categories;
            return $categories;

        } catch (\Exception $e) {
            $this->isDatabaseAvailable = false;
            $this->categoriesCache = [];
            return [];
        }
    }

    /**
     * بررسی کلمات توقف - بهبود یافته
     */
    private function isStopWord(string $word): bool
    {
        if ($this->stopWordsCache === null) {
            $this->stopWordsCache = array_flip([
                // کلمات توقف فارسی
                'و', 'یا', 'در', 'از', 'به', 'با', 'تا', 'که', 'این', 'آن', 'برای', 'مخصوص',
                'نوع', 'مدل', 'قسم', 'جور', 'گونه', 'رنگ', 'اندازه', 'سایز', 'تعداد', 'عدد',
                'بالا', 'پایین', 'بزرگ', 'کوچک', 'جدید', 'قدیمی', 'اصل', 'اصلی', 'اورجینال',
                'ایرانی', 'چینی', 'خارجی', 'داخلی', 'بهترین', 'مناسب', 'ویژه', 'خاص',
                'فروش', 'خرید', 'قیمت', 'ارزان', 'گران', 'تخفیف', 'درصد', 'ریال', 'تومان',

                // کلمات توقف انگلیسی
                'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
                'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after',
                'type', 'model', 'kind', 'sort', 'color', 'size', 'new', 'old', 'original', 'best',
                'good', 'bad', 'high', 'low', 'big', 'small', 'large', 'little', 'special', 'price'
            ]);
        }

        return isset($this->stopWordsCache[mb_strtolower($word, 'UTF-8')]);
    }

    /**
     * نرمال‌سازی متن - بهبود یافته
     */
    private function normalizeText(string $text): string
    {
        $cacheKey = md5($text);

        if (isset($this->normalizedTextCache[$cacheKey])) {
            return $this->normalizedTextCache[$cacheKey];
        }

        // تبدیل به حروف کوچک
        $normalized = mb_strtolower($text, 'UTF-8');

        // تبدیل ارقام فارسی و عربی به انگلیسی
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $normalized = str_replace($persianNumbers, $englishNumbers, $normalized);
        $normalized = str_replace($arabicNumbers, $englishNumbers, $normalized);

        // حذف کاراکترهای غیرضروری (به جز حروف، اعداد و فاصله)
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

        // فشرده‌سازی فاصله‌ها
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        if (count($this->normalizedTextCache) < 1000) {
            $this->normalizedTextCache[$cacheKey] = $normalized;
        }

        return $normalized;
    }

    /**
     * تست دقیق تشخیص دسته‌بندی - بهبود یافته
     */
    public function testCategoryDetection(string $text): array
    {
        $startTime = microtime(true);

        $result = [
            'input_text' => $text,
            'extracted_words' => $this->extractCleanWords($text),
            'detected_categories' => [],
            'detection_details' => [],
            'scoring_details' => [],
            'processing_time' => 0,
            'database_available' => $this->isDatabaseAvailable
        ];

        if (!$this->checkDatabaseAvailability()) {
            $result['error'] = 'Database not available';
            $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
            return $result;
        }

        $categories = $this->getAllCategories();
        if (empty($categories)) {
            $result['error'] = 'No categories found';
            $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);
            return $result;
        }

        $textCategories = array_filter(array_map('trim', explode(',', $text)));

        foreach ($textCategories as $categoryText) {
            $inputWords = $this->extractCleanWords($categoryText);

            // یافتن بهترین تطابق
            $bestMatch = $this->findBestCategoryMatch($categoryText, $categories);

            if ($bestMatch && $bestMatch['score'] >= self::MINIMUM_ACCEPTABLE_SCORE) {
                $result['detected_categories'][] = $bestMatch['category']['name'];
                $result['scoring_details'][] = [
                    'input' => $categoryText,
                    'input_words' => $inputWords,
                    'matched_category' => $bestMatch['category']['name'],
                    'score' => $bestMatch['score'],
                    'match_details' => $bestMatch['match_details'],
                    'minimum_required_score' => self::MINIMUM_ACCEPTABLE_SCORE
                ];
            }
        }

        $result['detected_categories'] = array_unique($result['detected_categories']);
        $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
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
            $logFile = storage_path('logs/category_detection_' . date('Ymd') . '.log');
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
        }
    }
}
