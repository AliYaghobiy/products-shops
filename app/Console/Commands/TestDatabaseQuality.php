<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TestDatabaseQuality extends Command
{
    protected $signature = 'test:database {database_name}';
    protected $description = 'Test database quality and show missing data statistics';

    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_CYAN = "\033[1;36m";
    private const COLOR_RESET = "\033[0m";

    public function handle()
    {
        $databaseName = $this->argument('database_name');

        $this->info("🔍 شروع تست کیفیت دیتابیس: {$databaseName}");
        $this->line(str_repeat('═', 70));

        // بررسی وجود دیتابیس
        if (!$this->checkDatabaseExists($databaseName)) {
            $this->error("❌ دیتابیس '{$databaseName}' یافت نشد!");
            return 1;
        }

        // اتصال به دیتابیس
        $this->connectToDatabase($databaseName);

        // بررسی وجود جدول products
        if (!$this->checkProductsTableExists()) {
            $this->error("❌ جدول 'products' در دیتابیس یافت نشد!");
            return 1;
        }

        // اجرای تست‌ها
        $this->runQualityTests();

        $this->line(str_repeat('═', 70));
        $this->info("✅ تست کیفیت دیتابیس تکمیل شد!");

        return 0;
    }

    private function checkDatabaseExists(string $databaseName): bool
    {
        try {
            $databases = DB::select("SHOW DATABASES LIKE '{$databaseName}'");
            return !empty($databases);
        } catch (\Exception $e) {
            $this->error("خطا در بررسی وجود دیتابیس: " . $e->getMessage());
            return false;
        }
    }

    private function connectToDatabase(string $databaseName): void
    {
        Config::set('database.connections.test_db', [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $databaseName,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        DB::purge('test_db');
        DB::setDefaultConnection('test_db');

        $this->info("✅ اتصال به دیتابیس '{$databaseName}' برقرار شد");
    }

    private function checkProductsTableExists(): bool
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'products'");
            return !empty($tables);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function runQualityTests(): void
    {
        $this->line("");
        $this->coloredLine("📊 آمار کلی دیتابیس:", self::COLOR_BLUE);
        $this->line(str_repeat('─', 50));

        // آمار کلی
        $totalProducts = $this->getTotalProducts();
        $this->coloredLine("📦 تعداد کل محصولات: {$totalProducts}", self::COLOR_CYAN);

        if ($totalProducts == 0) {
            $this->coloredLine("⚠️ دیتابیس خالی است!", self::COLOR_YELLOW);
            return;
        }

        $availableProducts = $this->getAvailableProducts();
        $unavailableProducts = $totalProducts - $availableProducts;

        $this->coloredLine("✅ محصولات موجود: {$availableProducts}", self::COLOR_GREEN);
        $this->coloredLine("❌ محصولات ناموجود: {$unavailableProducts}", self::COLOR_RED);

        $this->line("");
        $this->coloredLine("🔍 تست‌های کیفیت داده:", self::COLOR_PURPLE);
        $this->line(str_repeat('─', 50));

        // تست‌های کیفیت
        $this->testMissingTitles();
        $this->testMissingPrices();
        $this->testMissingProductIds();
        $this->testMissingImages();
        $this->testMissingCategories();
        $this->testMissingBrands();
        $this->testMissingPageUrls();
        $this->testDuplicateUrls();
        $this->testInvalidPrices();
        $this->testEmptyGuarantees();

        $this->line("");
        $this->coloredLine("📈 آمار پیشرفته:", self::COLOR_BLUE);
        $this->line(str_repeat('─', 50));

        $this->showAdvancedStats();
    }

    private function getTotalProducts(): int
    {
        return DB::table('products')->count();
    }

    private function getAvailableProducts(): int
    {
        return DB::table('products')->where('availability', 1)->count();
    }

    private function testMissingTitles(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('title')
                    ->orWhere('title', '')
                    ->orWhere('title', 'like', '%null%')
                    ->orWhere('title', 'like', '%undefined%');
            })
            ->count();

        $this->displayTestResult('عنوان (Title)', $count);
    }

    private function testMissingPrices(): void
    {
        $count = DB::table('products')
            ->where('availability', 1) // فقط محصولات موجود
            ->where(function($query) {
                $query->whereNull('price')
                    ->orWhere('price', '')
                    ->orWhere('price', '0')
                    ->orWhere('price', 'like', '%null%');
            })
            ->count();

        $this->displayTestResult('قیمت (Price) - محصولات موجود', $count);
    }

    private function testMissingProductIds(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('product_id')
                    ->orWhere('product_id', '')
                    ->orWhere('product_id', 'like', '%null%');
            })
            ->count();

        $this->displayTestResult('شناسه محصول (Product ID)', $count);
    }

    private function testMissingImages(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('image')
                    ->orWhere('image', '')
                    ->orWhere('image', 'like', '%null%');
            })
            ->count();

        $this->displayTestResult('تصویر (Image)', $count);
    }

    private function testMissingCategories(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('category')
                    ->orWhere('category', '')
                    ->orWhere('category', 'like', '%null%')
                    ->orWhere('category', 'like', '%دسته بندی نشده%');
            })
            ->count();

        $this->displayTestResult('دسته‌بندی (Category)', $count);
    }

    private function testMissingBrands(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('brand')
                    ->orWhere('brand', '')
                    ->orWhere('brand', 'like', '%null%');
            })
            ->count();

        $this->displayTestResult('برند (Brand)', $count);
    }

    private function testMissingPageUrls(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('page_url')
                    ->orWhere('page_url', '')
                    ->orWhere('page_url', 'like', '%null%');
            })
            ->count();

        $this->displayTestResult('آدرس صفحه (Page URL)', $count);
    }

    private function testDuplicateUrls(): void
    {
        $count = DB::table('products')
            ->select('page_url')
            ->whereNotNull('page_url')
            ->where('page_url', '!=', '')
            ->groupBy('page_url')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->displayTestResult('URL های تکراری', $count);
    }

    private function testInvalidPrices(): void
    {
        $count = DB::table('products')
            ->where('availability', 1)
            ->where(function($query) {
                $query->where('price', 'like', '%[a-zA-Z]%')
                    ->orWhere('price', 'like', '%-%')
                    ->orWhere('price', 'like', '%+%')
                    ->orWhere('price', 'like', '%=%')
                    ->orWhere('price', 'like', '%#%');
            })
            ->count();

        $this->displayTestResult('قیمت‌های نامعتبر (حاوی حروف)', $count);
    }

    private function testEmptyGuarantees(): void
    {
        $count = DB::table('products')
            ->where(function($query) {
                $query->whereNull('guarantee')
                    ->orWhere('guarantee', '')
                    ->orWhere('guarantee', 'like', '%null%');
            })
            ->count();

        $this->displayTestResult('گارانتی (Guarantee)', $count);
    }

    private function displayTestResult(string $testName, int $count): void
    {
        $icon = $count > 0 ? '⚠️' : '✅';
        $color = $count > 0 ? self::COLOR_YELLOW : self::COLOR_GREEN;
        $status = $count > 0 ? "ناقص" : "کامل";

        $this->coloredLine(sprintf("  %s %-35s: %d محصول %s", $icon, $testName, $count, $status), $color);
    }

    private function showAdvancedStats(): void
    {
        // آمار تخفیف‌ها
        $discountedProducts = DB::table('products')->where('off', '>', 0)->count();
        $this->coloredLine("🏷️ محصولات دارای تخفیف: {$discountedProducts}", self::COLOR_CYAN);

        // میانگین قیمت
        $avgPrice = DB::table('products')
            ->where('availability', 1)
            ->whereNotNull('price')
            ->where('price', '!=', '')
            ->where('price', '!=', '0')
            ->avg(DB::raw('CAST(REPLACE(REPLACE(price, ",", ""), " ", "") AS UNSIGNED)'));

        if ($avgPrice) {
            $avgPrice = number_format($avgPrice);
            $this->coloredLine("💰 میانگین قیمت محصولات: {$avgPrice} تومان", self::COLOR_CYAN);
        }

        // محصولات بدون تصویر از کل
        $totalProducts = $this->getTotalProducts();
        $productsWithImages = DB::table('products')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->count();

        if ($totalProducts > 0) {
            $imagePercentage = round(($productsWithImages / $totalProducts) * 100, 1);
            $this->coloredLine("📸 درصد محصولات دارای تصویر: {$imagePercentage}%", self::COLOR_CYAN);
        }

        // تعداد برندهای منحصر به فرد
        $uniqueBrands = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct('brand')
            ->count();
        $this->coloredLine("🏢 تعداد برندهای منحصر به فرد: {$uniqueBrands}", self::COLOR_CYAN);

        // تعداد دسته‌بندی‌های منحصر به فرد
        $uniqueCategories = DB::table('products')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct('category')
            ->count();
        $this->coloredLine("📂 تعداد دسته‌بندی‌های منحصر به فرد: {$uniqueCategories}", self::COLOR_CYAN);
    }

    private function coloredLine(string $message, string $color): void
    {
        $this->line($color . $message . self::COLOR_RESET);
    }
}
