<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ProductDataProcessor;

class BrandDetectionService
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";
    private $outputCallback = null;
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
            $this->log("Empty text provided for brand detection", self::COLOR_YELLOW);
            return null;
        }

        $this->log("🔍 شروع تشخیص برند از متن: " . substr($text, 0, 100) . "...", self::COLOR_BLUE);

        try {
            // دریافت تمام برندها از دیتابیس
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
            $this->log("💥 خطا در تشخیص برند: " . $e->getMessage(), self::COLOR_YELLOW);
            return null;
        }
    }

    /**
     * دریافت تمام برندها از دیتابیس
     */
    private function getAllBrands(): array
    {
        try {
            return DB::table('brands')
                ->select('id', 'name', 'slug', 'description', 'keywords')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $this->log("خطا در دریافت برندها از دیتابیس: " . $e->getMessage(), self::COLOR_YELLOW);
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

        foreach ($brands as $brand) {
            $score = $this->calculateBrandMatchScore($text, $brand);

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = (array) $brand;
            }
        }

        // حداقل امتیاز برای تطابق (قابل تنظیم)
        $minimumScore = 0.3;

        if ($highestScore >= $minimumScore) {
            $this->log("📈 امتیاز تطابق: " . round($highestScore, 3) . " برای برند: " . $bestMatch['name'], self::COLOR_BLUE);
            return $bestMatch;
        }

        $this->log("📉 بالاترین امتیاز (" . round($highestScore, 3) . ") کمتر از حداقل مورد نیاز (" . $minimumScore . ")", self::COLOR_YELLOW);
        return null;
    }

    /**
     * محاسبه امتیاز تطابق برند
     */
    private function calculateBrandMatchScore(string $text, object $brand): float
    {
        $score = 0;
        $brandData = (array) $brand;

        // تطابق مستقیم با نام برند
        $nameScore = $this->calculateDirectMatch($text, $brandData['name']);
        $score += $nameScore * 1.0; // وزن بالا برای نام

        // تطابق با slug
        if (!empty($brandData['slug'])) {
            $slugScore = $this->calculateDirectMatch($text, $brandData['slug']);
            $score += $slugScore * 0.8; // وزن متوسط برای slug
        }

        // تطابق با کلمات کلیدی
        if (!empty($brandData['keywords'])) {
            $keywordsScore = $this->calculateKeywordsMatch($text, $brandData['keywords']);
            $score += $keywordsScore * 0.6; // وزن کمتر برای کلمات کلیدی
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

        // تطابق فازی با Levenshtein distance
        $maxLength = max(strlen($text), strlen($brandName));
        if ($maxLength > 0) {
            $distance = levenshtein(substr($text, 0, 255), substr($brandName, 0, 255));
            return 1 - ($distance / $maxLength);
        }

        return 0;
    }

    /**
     * محاسبه تطابق کلمات کلیدی
     */
    private function calculateKeywordsMatch(string $text, string $keywords): float
    {
        $text = $this->normalizeText($text);
        $keywordList = explode(',', $keywords);
        $matches = 0;
        $totalKeywords = count($keywordList);

        foreach ($keywordList as $keyword) {
            $keyword = $this->normalizeText(trim($keyword));
            if (!empty($keyword) && strpos($text, $keyword) !== false) {
                $matches++;
            }
        }

        return $totalKeywords > 0 ? $matches / $totalKeywords : 0;
    }

    /**
     * نرمال‌سازی متن برای مقایسه بهتر
     */
    private function normalizeText(string $text): string
    {
        // تبدیل به حروف کوچک
        $text = mb_strtolower($text, 'UTF-8');

        // حذف کاراکترهای اضافی
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        // حذف فاصله‌های اضافی
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
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
            $logFile = storage_path('logs/scraper_' . date('Ymd') . '.log');
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
        }
    }
}
