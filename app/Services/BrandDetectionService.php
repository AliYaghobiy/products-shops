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

    private $outputCallback = null;
    private ?array $brandsCache = null;
    private bool $isDatabaseAvailable = true;

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
            $this->log("🔍 Empty text provided for brand detection", self::COLOR_YELLOW);
            return null;
        }

        $this->log("🔍 شروع تشخیص برند از متن: " . substr($text, 0, 100) . "...", self::COLOR_BLUE);

        try {
            // بررسی دسترسی به دیتابیس و جدول brands
            if (!$this->checkDatabaseAvailability()) {
                $this->log("⚠️ جدول brands در دسترس نیست - تشخیص برند غیرفعال شد", self::COLOR_YELLOW);
                return null;
            }

            // دریافت تمام برندها از cache یا دیتابیس
            $brands = $this->getAllBrands();

            if (empty($brands)) {
                $this->log("⚠️ هیچ برندی در دیتابیس یافت نشد", self::COLOR_YELLOW);
                return null;
            }

            $this->log("📊 تعداد برندهای موجود در دیتابیس: " . count($brands), self::COLOR_BLUE);

            // تشخیص برند با بالاترین تطابق
            $detectedBrand = $this->findBestMatchingBrand($text, $brands);

            if ($detectedBrand) {
                $this->log("✅ برند تشخیص داده شد: " . $detectedBrand['name'], self::COLOR_GREEN);
                return $detectedBrand['name'];
            } else {
                $this->log("❌ هیچ برند مطابقی یافت نشد", self::COLOR_YELLOW);
                return null;
            }

        } catch (\Exception $e) {
            $this->log("💥 خطا در تشخیص برند: " . $e->getMessage(), self::COLOR_RED);
            $this->log("🔄 ادامه پردازش بدون تشخیص برند...", self::COLOR_YELLOW);
            return null;
        }
    }

    /**
     * بررسی دسترسی به دیتابیس و جدول brands
     */
    private function checkDatabaseAvailability(): bool
    {
        try {
            // بررسی وجود جدول brands
            if (!Schema::hasTable('brands')) {
                $this->log("❌ جدول 'brands' وجود ندارد", self::COLOR_RED);
                $this->isDatabaseAvailable = false;
                return false;
            }

            // تست ساده اتصال
            DB::table('brands')->limit(1)->get();

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
        // اگر cache موجود است، از آن استفاده کن
        if ($this->brandsCache !== null) {
            return $this->brandsCache;
        }

        try {
            if (!$this->isDatabaseAvailable) {
                return [];
            }

            $brands = DB::table('brands')
                ->select('id', 'name', 'slug', 'description', 'keywords')
                ->get()
                ->map(function ($brand) {
                    return (array) $brand;
                })
                ->toArray();

            // ذخیره در cache
            $this->brandsCache = $brands;

            $this->log("📚 برندها از دیتابیس بارگذاری و در cache ذخیره شدند", self::COLOR_BLUE);

            return $brands;

        } catch (\Exception $e) {
            $this->log("❌ خطا در دریافت برندها از دیتابیس: " . $e->getMessage(), self::COLOR_RED);
            $this->isDatabaseAvailable = false;
            $this->brandsCache = [];
            return [];
        }
    }

    /**
     * یافتن بهترین برند متطابق
     */
    private function findBestMatchingBrand(string $text, array $brands): ?array
    {
        $text = $this->normalizeText($text);
        $bestMatch = null;
        $highestScore = 0;
        $detailedScores = [];

        foreach ($brands as $brand) {
            $score = $this->calculateBrandMatchScore($text, $brand);

            $detailedScores[] = [
                'brand' => $brand['name'],
                'score' => round($score, 3)
            ];

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $brand;
            }
        }

        // لاگ بهترین نتایج
        usort($detailedScores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $topScores = array_slice($detailedScores, 0, 3);
        $this->log("🏆 بهترین امتیازات:", self::COLOR_PURPLE);
        foreach ($topScores as $i => $score) {
            $this->log("  " . ($i + 1) . ". {$score['brand']}: {$score['score']}", self::COLOR_PURPLE);
        }

        // حداقل امتیاز برای تطابق (قابل تنظیم)
        $minimumScore = 0.3;

        if ($highestScore >= $minimumScore) {
            $this->log("📈 امتیاز تطابق: " . round($highestScore, 3) . " برای برند: " . $bestMatch['name'], self::COLOR_GREEN);
            return $bestMatch;
        }

        $this->log("📉 بالاترین امتیاز (" . round($highestScore, 3) . ") کمتر از حداقل مورد نیاز (" . $minimumScore . ")", self::COLOR_YELLOW);
        return null;
    }

    /**
     * محاسبه امتیاز تطابق برند
     */
    private function calculateBrandMatchScore(string $text, array $brand): float
    {
        $score = 0;
        $weights = [
            'name' => 1.0,      // وزن بالا برای نام اصلی
            'slug' => 0.8,      // وزن متوسط برای slug
            'keywords' => 0.6,  // وزن کمتر برای کلمات کلیدی
            'description' => 0.3 // وزن پایین برای توضیحات
        ];

        // تطابق با نام برند
        if (!empty($brand['name'])) {
            $nameScore = $this->calculateDirectMatch($text, $brand['name']);
            $score += $nameScore * $weights['name'];
        }

        // تطابق با slug
        if (!empty($brand['slug'])) {
            $slugScore = $this->calculateDirectMatch($text, $brand['slug']);
            $score += $slugScore * $weights['slug'];
        }

        // تطابق با کلمات کلیدی
        if (!empty($brand['keywords'])) {
            $keywordsScore = $this->calculateKeywordsMatch($text, $brand['keywords']);
            $score += $keywordsScore * $weights['keywords'];
        }

        // تطابق با توضیحات (اختیاری)
        if (!empty($brand['description'])) {
            $descScore = $this->calculatePartialMatch($text, $brand['description']);
            $score += $descScore * $weights['description'];
        }

        // نرمال‌سازی امتیاز (حداکثر 1)
        return min($score, 1.0);
    }

    /**
     * محاسبه تطابق مستقیم
     */
    private function calculateDirectMatch(string $text, string $brandName): float
    {
        $text = $this->normalizeText($text);
        $brandName = $this->normalizeText($brandName);

        // تطابق کامل
        if (strpos($text, $brandName) !== false) {
            return 1.0;
        }

        // بررسی تطابق کلمات جداگانه
        $textWords = explode(' ', $text);
        $brandWords = explode(' ', $brandName);

        foreach ($brandWords as $brandWord) {
            if (strlen($brandWord) > 2) { // فقط کلمات با بیش از 2 حرف
                foreach ($textWords as $textWord) {
                    if ($brandWord === $textWord) {
                        return 0.8; // تطابق کلمه کامل
                    }
                    // تطابق فازی برای کلمات مشابه
                    if (strlen($textWord) > 2 && $this->calculateSimilarity($brandWord, $textWord) > 0.8) {
                        return 0.6;
                    }
                }
            }
        }

        // تطابق فازی کلی با Levenshtein distance
        $maxLength = max(strlen($text), strlen($brandName));
        if ($maxLength > 0 && $maxLength <= 255) {
            $distance = levenshtein($text, $brandName);
            $similarity = 1 - ($distance / $maxLength);
            return $similarity > 0.7 ? $similarity * 0.5 : 0; // کاهش وزن برای تطابق فازی کلی
        }

        return 0;
    }

    /**
     * محاسبه تطابق جزئی برای توضیحات
     */
    private function calculatePartialMatch(string $text, string $description): float
    {
        $text = $this->normalizeText($text);
        $description = $this->normalizeText($description);

        $textWords = array_filter(explode(' ', $text), function($word) {
            return strlen($word) > 2;
        });

        $descWords = array_filter(explode(' ', $description), function($word) {
            return strlen($word) > 2;
        });

        if (empty($textWords) || empty($descWords)) {
            return 0;
        }

        $matches = 0;
        foreach ($textWords as $textWord) {
            foreach ($descWords as $descWord) {
                if ($this->calculateSimilarity($textWord, $descWord) > 0.8) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($textWords);
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
     * محاسبه تطابق کلمات کلیدی
     */
    private function calculateKeywordsMatch(string $text, string $keywords): float
    {
        $text = $this->normalizeText($text);
        $keywordList = array_map('trim', explode(',', $keywords));
        $matches = 0;
        $totalKeywords = count($keywordList);

        foreach ($keywordList as $keyword) {
            $keyword = $this->normalizeText($keyword);
            if (!empty($keyword)) {
                // تطابق مستقیم
                if (strpos($text, $keyword) !== false) {
                    $matches++;
                    continue;
                }

                // تطابق فازی برای کلمات کلیدی
                $textWords = explode(' ', $text);
                foreach ($textWords as $textWord) {
                    if ($this->calculateSimilarity($keyword, $textWord) > 0.8) {
                        $matches += 0.7; // امتیاز کمتر برای تطابق فازی
                        break;
                    }
                }
            }
        }

        return $totalKeywords > 0 ? min($matches / $totalKeywords, 1.0) : 0;
    }

    /**
     * نرمال‌سازی متن برای مقایسه بهتر
     */
    private function normalizeText(string $text): string
    {
        // تبدیل به حروف کوچک
        $text = mb_strtolower($text, 'UTF-8');

        // حذف نقطه‌گذاری و کاراکترهای خاص
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        // جایگزینی چندین فاصله با یک فاصله
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * پاکسازی cache برندها
     */
    public function clearCache(): void
    {
        $this->brandsCache = null;
        $this->log("🗑️ Cache برندها پاک شد", self::COLOR_BLUE);
    }

    /**
     * بروزرسانی اجباری cache از دیتابیس
     */
    public function refreshCache(): bool
    {
        $this->clearCache();

        if (!$this->checkDatabaseAvailability()) {
            return false;
        }

        $brands = $this->getAllBrands();
        $this->log("🔄 Cache برندها بروزرسانی شد - تعداد: " . count($brands), self::COLOR_GREEN);

        return !empty($brands);
    }

    /**
     * دریافت آمار تشخیص برند
     */
    public function getBrandDetectionStats(): array
    {
        return [
            'database_available' => $this->isDatabaseAvailable,
            'brands_cached' => $this->brandsCache !== null,
            'total_brands' => $this->brandsCache ? count($this->brandsCache) : 0,
            'cache_status' => $this->brandsCache !== null ? 'loaded' : 'empty'
        ];
    }

    /**
     * تست تشخیص برند با جزئیات
     */
    public function testBrandDetection(string $text): array
    {
        $startTime = microtime(true);

        $result = [
            'input_text' => $text,
            'normalized_text' => $this->normalizeText($text),
            'detected_brand' => null,
            'database_available' => $this->isDatabaseAvailable,
            'processing_time' => 0,
            'detailed_scores' => []
        ];

        if (!$this->checkDatabaseAvailability()) {
            $result['error'] = 'Database not available';
            return $result;
        }

        $brands = $this->getAllBrands();
        if (empty($brands)) {
            $result['error'] = 'No brands found in database';
            return $result;
        }

        $detailedScores = [];
        $normalizedText = $this->normalizeText($text);

        foreach ($brands as $brand) {
            $score = $this->calculateBrandMatchScore($normalizedText, $brand);
            $detailedScores[] = [
                'brand_name' => $brand['name'],
                'score' => round($score, 4),
                'brand_data' => $brand
            ];
        }

        // مرتب‌سازی بر اساس امتیاز
        usort($detailedScores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $result['detailed_scores'] = $detailedScores;
        $result['detected_brand'] = $detailedScores[0]['score'] >= 0.3 ? $detailedScores[0]['brand_name'] : null;
        $result['processing_time'] = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        return $result;
    }

    /**
     * لاگ کردن پیام‌ها
     */
    private function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        // استفاده مستقیم از callback تنظیم‌شده
        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $formattedMessage);
        } else {
            // لاگ پیش‌فرض به فایل
            $logFile = storage_path('logs/brand_detection_' . date('Ymd') . '.log');
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
        }
    }
}
