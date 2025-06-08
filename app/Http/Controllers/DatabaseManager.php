<?php

namespace App\Http\Controllers;

use App\Models\FailedLink;
use App\Models\Link;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DatabaseManager
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";

    private array $config;
    private $outputCallback = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    public function setupDatabase(): void
    {
        $dbName = $this->getDatabaseNameFromBaseUrl();
        $databaseMode = $this->config['database'] ?? 'clear';
        $this->log("Database mode: $databaseMode", self::COLOR_GREEN);

        // استفاده از اتصال پیش‌فرض برای مدیریت دیتابیس
        DB::setDefaultConnection('mysql');

        // بررسی وجود دیتابیس
        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
        $databaseExists = !empty($exists);

        if ($databaseMode === 'clear') {
            if ($databaseExists) {
                $this->log("Database $dbName exists, dropping it...", self::COLOR_YELLOW);
                DB::statement("DROP DATABASE `$dbName`");
            }
            $this->log("Creating database $dbName...", self::COLOR_GREEN);
            DB::statement("CREATE DATABASE `$dbName`");
        } elseif ($databaseMode === 'continue') {
            if (!$databaseExists) {
                $this->log("Database $dbName does not exist, creating it...", self::COLOR_YELLOW);
                DB::statement("CREATE DATABASE `$dbName`");
            } else {
                $this->log("Using existing database $dbName", self::COLOR_GREEN);
            }
        } else {
            throw new \Exception("Invalid database mode specified: $databaseMode. Use 'clear' or 'continue'.");
        }

        // تنظیم اتصال داینامیک
        config(["database.connections.dynamic" => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $dbName,
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // تعویض اتصال به داینامیک
        DB::purge('mysql');
        DB::setDefaultConnection('dynamic');

        $this->log("Switched to database: $dbName", self::COLOR_GREEN);

        // اجرای مهاجرت‌ها
        if ($databaseMode === 'clear' || !$databaseExists) {
            $this->runMigrations();
        }
    }

    public function saveProductLinksToDatabase(array $links): void
    {
        if (empty($links)) {
            $this->log("No links to save to database", self::COLOR_YELLOW);
            return;
        }

        $this->log("Saving " . count($links) . " product links to database...", self::COLOR_GREEN);

        try {
            $insertData = [];
            $duplicateCount = 0;
            $batchSize = 1000; // پردازش دسته‌ای برای عملکرد بهتر

            // آماده‌سازی داده‌ها برای insert
            foreach ($links as $link) {
                $url = is_array($link) ? ($link['url'] ?? '') : $link;
                $productId = is_array($link) && isset($link['product_id']) ? $link['product_id'] : null;

                // اعتبارسنجی ساده URL
                if (empty($url) || !preg_match('/^https?:\/\/.+/', $url)) {
                    $this->log("Invalid URL skipped: " . ($url ?? 'empty'), self::COLOR_YELLOW);
                    continue;
                }

                // لاگ برای دیباگ
                $this->log("Preparing to save link: $url", self::COLOR_BLUE);

                $insertData[] = [
                    'url' => $url,
                    'is_processed' => false,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (empty($insertData)) {
                $this->log("No valid links to insert", self::COLOR_YELLOW);
                return;
            }

            // استفاده از insertOrIgnore برای جلوگیری از درج لینک‌های تکراری
            $chunks = array_chunk($insertData, $batchSize);
            $totalInserted = 0;

            foreach ($chunks as $chunk) {
                try {
                    $inserted = DB::table('links')->insertOrIgnore($chunk);
                    $totalInserted += $inserted;
                } catch (\Exception $e) {
                    $this->log("Error inserting batch: " . $e->getMessage(), self::COLOR_RED);

                    // اگر دسته‌ای شکست خورد، لینک‌ها رو یکی‌یکی امتحان می‌کنیم
                    foreach ($chunk as $item) {
                        try {
                            $existingLink = DB::table('links')->where('url', $item['url'])->exists();
                            if (!$existingLink) {
                                DB::table('links')->insert($item);
                                $totalInserted++;
                            } else {
                                $duplicateCount++;
                            }
                        } catch (\Exception $individualError) {
                            $this->log("Failed to insert link {$item['url']}: " . $individualError->getMessage(), self::COLOR_RED);
                        }
                    }
                }
            }

            $this->log("Successfully saved $totalInserted new links to database", self::COLOR_GREEN);
            if ($duplicateCount > 0) {
                $this->log("Skipped $duplicateCount duplicate links", self::COLOR_YELLOW);
            }

        } catch (\Exception $e) {
            $this->log("Critical error saving links to database: " . $e->getMessage(), self::COLOR_RED);
            throw $e;
        }
    }

    public function getProductLinksFromDatabase(?int $start_id = null): array
    {
        $this->log("📋 Fetching product links from database" . ($start_id ? " starting from ID $start_id" : ""), self::COLOR_GREEN);

        try {
            $query = DB::table('links')
                ->where('is_processed', 0)
                ->select('id', 'url', 'source_url', 'product_id')
                ->orderBy('id');

            if ($start_id !== null) {
                $query->where('id', '>=', $start_id);
            }

            $links = $query->get()->map(function ($link) {
                return [
                    'id' => $link->id,
                    'url' => $link->url,
                    'sourceUrl' => $link->source_url,
                    'product_id' => $link->product_id
                ];
            })->toArray();

            $totalLinksInDb = DB::table('links')->count();
            $processedLinksCount = DB::table('links')->where('is_processed', 1)->count();
            $unprocessedLinksCount = count($links);

            $this->log("📊 Database status:", self::COLOR_BLUE);
            $this->log("  • Total links in DB: $totalLinksInDb", self::COLOR_BLUE);
            $this->log("  • Processed links: $processedLinksCount", self::COLOR_BLUE);
            $this->log("  • Unprocessed links: $unprocessedLinksCount", self::COLOR_BLUE);

            $pagesProcessed = DB::table('links')
                ->distinct()
                ->count('source_url');

            return [
                'links' => $links,
                'pages_processed' => $pagesProcessed
            ];

        } catch (\Exception $e) {
            $this->log("❌ Failed to fetch links from database: {$e->getMessage()}", self::COLOR_RED);
            return [
                'links' => [],
                'pages_processed' => 0
            ];
        }
    }

    public function updateLinkProcessedStatus(string $url, bool $status = true): void
    {
        try {
            if (empty($url)) {
                $this->log("Cannot update status: empty URL provided", self::COLOR_RED);
                return;
            }

            $affected = DB::table('links')
                ->where('url', $url)
                ->update([
                    'is_processed' => $status,
                    'updated_at' => now()
                ]);

            if ($affected === 0) {
                $this->log("Link not found in database for status update: $url", self::COLOR_YELLOW);
            } else {
                $statusText = $status ? 'processed' : 'unprocessed';
                $this->log("Marked $affected link(s) as $statusText: $url", self::COLOR_BLUE);
            }

        } catch (\Exception $e) {
            $this->log("Error updating link status for $url: " . $e->getMessage(), self::COLOR_RED);
        }
    }

    public function resetProductsAndLinks(): void
    {
        $this->log("🔄 Reset mode activated - clearing products and marking all links as unprocessed...", self::COLOR_YELLOW);

        try {
            DB::beginTransaction();

            $productsCount = Product::count();
            if ($productsCount > 0) {
                Product::truncate();
                $this->log("✅ Cleared $productsCount products from database", self::COLOR_GREEN);
            }

            $linksUpdated = Link::where('is_processed', 1)->update(['is_processed' => 0, 'updated_at' => now()]);
            $this->log("🔄 Reset $linksUpdated links to unprocessed state", self::COLOR_GREEN);

            $failedLinksCount = FailedLink::count();
            if ($failedLinksCount > 0) {
                FailedLink::truncate();
                $this->log("🗑️ Cleared $failedLinksCount failed links from database", self::COLOR_GREEN);
            }

            DB::commit();
            $this->log("✅ Database reset completed successfully", self::COLOR_GREEN);

            $totalLinksInDb = Link::count();
            $unprocessedLinks = Link::where('is_processed', 0)->count();

            $this->log("📊 Database status after reset:", self::COLOR_BLUE);
            $this->log("  • Total links: $totalLinksInDb", self::COLOR_BLUE);
            $this->log("  • Unprocessed links: $unprocessedLinks", self::COLOR_BLUE);
            $this->log("  • Products: 0", self::COLOR_BLUE);
            $this->log("  • Failed links: 0", self::COLOR_BLUE);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->log("❌ Failed to reset database: " . $e->getMessage(), self::COLOR_RED);
            throw $e;
        }
    }

    private function getDatabaseNameFromBaseUrl(): string
    {
        $baseUrl = $this->config['base_urls'][0] ?? '';
        if (empty($baseUrl)) {
            throw new \Exception("No base_url defined for generating database name.");
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (!$host) {
            throw new \Exception("Invalid base URL: $baseUrl");
        }

        $host = preg_replace('/^www\./', '', $host);
        $dbName = str_replace('.', '_', $host);
        $this->log("Generated database name: $dbName", self::COLOR_GREEN);
        return $dbName;
    }

    private function runMigrations(): void
    {
        $this->log("Running specific migrations...", self::COLOR_GREEN);

        $migrationFiles = [
            database_path('migrations/2025_04_08_162133_create_products_table.php'),
            database_path('migrations/2025_04_13_073528_create_failed_links_table.php'),
            database_path('migrations/2025_05_19_162835_create_links_table.php'),
        ];

        foreach ($migrationFiles as $file) {
            if (!file_exists($file)) {
                $this->log("Migration file $file not found", self::COLOR_RED);
                continue;
            }

            require_once $file;

            $className = $this->getMigrationClassName($file);
            if (!class_exists($className)) {
                $this->log("Migration class $className not found in $file", self::COLOR_RED);
                continue;
            }

            $migration = new $className();
            $migration->up();
            $this->log("Applied migration: " . basename($file), self::COLOR_GREEN);
        }

        $this->log("Specific migrations completed", self::COLOR_GREEN);
    }

    private function getMigrationClassName(string $file): string
    {
        $contents = file_get_contents($file);
        if (preg_match('/class\s+(\w+)\s+extends\s+Migration/', $contents, $matches)) {
            return $matches[1];
        }
        throw new \Exception("Could not determine migration class name for $file");
    }

    private function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        $logFile = storage_path('logs/scraper_' . date('Ymd') . '.log');
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);

        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $formattedMessage);
        } else {
            echo $formattedMessage . PHP_EOL;
        }
    }

    public function getDatabaseStatus(): array
    {
        try {
            $totalLinks = Link::count();
            $processedLinks = Link::where('is_processed', 1)->count();
            $unprocessedLinks = Link::where('is_processed', 0)->count();
            $totalProducts = Product::count();
            $failedLinks = FailedLink::count();

            return [
                'total_links' => $totalLinks,
                'processed_links' => $processedLinks,
                'unprocessed_links' => $unprocessedLinks,
                'total_products' => $totalProducts,
                'failed_links' => $failedLinks
            ];
        } catch (\Exception $e) {
            $this->log("❌ Error getting database status: " . $e->getMessage(), self::COLOR_RED);
            return [
                'total_links' => 0,
                'processed_links' => 0,
                'unprocessed_links' => 0,
                'total_products' => 0,
                'failed_links' => 0
            ];
        }
    }
}
