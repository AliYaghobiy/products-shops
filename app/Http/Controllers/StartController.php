<?php

namespace App\Http\Controllers;

use App\Models\FailedLink;
use App\Models\Link;
use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;

class StartController
{
    public array $config;
    private Client $httpClient;
    private $chromedriverPid = null;
    private array $failedUrlsDueToInternalError = [];
    private $outputCallback = null;
    private int $processedCount = 0;
    protected array $failedLinks = [];
    private array $sharedProductIds = [];

    // Color constants
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";
    private const COLOR_GRAY = "\033[1;90m";
    private const COLOR_CYAN = "\033[1;36m";
    private const COLOR_RESET = "\033[0m";
    private const COLOR_BOLD = "\033[1m";

    // Helper classes
    private ConfigValidator $configValidator;
    private DatabaseManager $databaseManager;
    private ProductDataProcessor $productProcessor;
    private LinkScraper $linkScraper;

    public function __construct(array $config)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        $this->config = $config;

        // Initialize helper classes
        $this->configValidator = new ConfigValidator();
        $this->configValidator->setOutputCallback([$this, 'handleOutput']);

        $this->databaseManager = new DatabaseManager($config);
        $this->databaseManager->setOutputCallback([$this, 'handleOutput']);

        $this->productProcessor = new ProductDataProcessor($config);
        $this->productProcessor->setOutputCallback([$this, 'handleOutput']);

        // ✅ بررسی حالت تست محصول قبل از اعتبارسنجی معمولی
        $isProductTestMode = $this->config['product_test'] ?? false;

        if ($isProductTestMode) {
            $this->log("🧪 Product Test Mode - Skipping standard config validation", self::COLOR_PURPLE);
            $this->configValidator->validateProductTestConfig($this->config);
        } else {
            $this->configValidator->validateAndFixConfig($this->config);
        }

        // تنظیم زمان تاخیر
        $delay = $this->config['request_delay'] ?? mt_rand(
            $this->config['request_delay_min'] ?? 500,
            $this->config['request_delay_max'] ?? 2000
        );
        $this->setRequestDelay($delay);

        // تنظیم HTTP Client
        $baseUrl = '';
        if ($isProductTestMode && !empty($this->config['product_urls'])) {
            $firstUrl = $this->config['product_urls'][0];
            $parsedUrl = parse_url($firstUrl);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        } elseif (!empty($this->config['base_urls'])) {
            $baseUrl = $this->config['base_urls'][0];
        }

        $this->httpClient = new Client([
            'timeout' => $this->config['timeout'] ?? 120,
            'verify' => $this->config['verify_ssl'] ?? false,
            'headers' => [
                'User-Agent' => $this->randomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Referer' => $baseUrl,
                'Connection' => 'keep-alive',
            ],
        ]);

        // Initialize LinkScraper with HTTP client
        $this->linkScraper = new LinkScraper($config, $this->httpClient);
        $this->linkScraper->setOutputCallback([$this, 'handleOutput']);
    }

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    public function setRequestDelay(int $delay): void
    {
        $this->config['request_delay_min'] = $delay;
        $this->config['request_delay_max'] = $delay;
    }

    public function scrapeMultiple(?int $start_id = null): array
    {
        $this->log("Inside scrapeMultiple method", self::COLOR_PURPLE);
        $this->log("Starting scraper with start_id: " . ($start_id ?? 'not set'), self::COLOR_GREEN);

        // ✅ بررسی حالت تست محصول
        $isProductTestMode = $this->config['product_test'] ?? false;
        if ($isProductTestMode) {
            $this->log("🧪 Product Test Mode Detected - Testing individual products", self::COLOR_PURPLE);
            return $this->runProductTestMode();
        }

        // بررسی حالت update
        $isUpdateMode = $this->config['update_mode'] ?? false;
        if ($isUpdateMode) {
            $this->log("Update mode detected", self::COLOR_PURPLE);
        }

        // لاگ محتوای کانفیگ برای دیباگ
        $this->log("Config contents: " . json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), self::COLOR_YELLOW);

        // اعتبارسنجی کانفیگ
        $this->configValidator->validateConfig($this->config);

        // تنظیم دیتابیس
        $this->databaseManager->setupDatabase();

        // اگر در حالت update هستیم، ابتدا reset انجام میدهیم
        if ($isUpdateMode) {
            $this->databaseManager->resetProductsAndLinks();
        }

        // تنظیم اولیه
        $this->processedCount = 0;

        // اعتبارسنجی start_id
        if ($start_id !== null && $start_id <= 0) {
            $this->log("Invalid start_id: $start_id. Must be a positive integer. Ignoring start_id.", self::COLOR_RED);
            $start_id = null;
        }

        // بررسی run_method
        $runMethod = $this->config['run_method'] ?? 'new';
        $this->log("Run method: $runMethod", self::COLOR_GREEN);

        $links = [];
        $pagesProcessed = 0;

        if ($runMethod === 'continue' || $isUpdateMode) {
            $this->log("Continuing with links from database" . ($start_id ? " starting from ID $start_id" : "") . "...", self::COLOR_GREEN);

            if ($isUpdateMode) {
                $totalLinksInDb = Link::count();
                $this->log("Total links in database: $totalLinksInDb", self::COLOR_BLUE);

                if ($totalLinksInDb == 0) {
                    $this->log("No links found in database for update mode. Need to fetch from web first.", self::COLOR_YELLOW);

                    $this->log("Fetching product links from web for update mode...", self::COLOR_GREEN);
                    $result = $this->linkScraper->fetchProductLinks();
                    $links = $result['links'] ?? [];
                    $pagesProcessed = $result['pages_processed'] ?? 0;

                    $this->log("Got " . count($links) . " unique product links from web", self::COLOR_GREEN);

                    if (!empty($links)) {
                        $this->databaseManager->saveProductLinksToDatabase($links);
                        $result = $this->databaseManager->getProductLinksFromDatabase($start_id);
                        $links = $result['links'] ?? [];
                        $pagesProcessed = $result['pages_processed'] ?? 0;
                    } else {
                        $this->log("No links collected from web. Stopping scrape.", self::COLOR_YELLOW);
                        return [
                            'status' => 'success',
                            'total_products' => 0,
                            'failed_links' => 0,
                            'total_pages_count' => $pagesProcessed,
                            'products' => []
                        ];
                    }
                } else {
                    $result = $this->databaseManager->getProductLinksFromDatabase($start_id);
                    $links = $result['links'] ?? [];
                    $pagesProcessed = $result['pages_processed'] ?? 0;
                }
            } else {
                $result = $this->databaseManager->getProductLinksFromDatabase($start_id);
                $links = $result['links'] ?? [];
                $pagesProcessed = $result['pages_processed'] ?? 0;
            }

            $this->log("Got " . count($links) . " links from database", self::COLOR_GREEN);
            if (empty($links)) {
                $this->log("No links found in database" . ($start_id ? " for ID >= $start_id" : "") . ". Stopping scrape.", self::COLOR_YELLOW);
                return [
                    'status' => 'success',
                    'total_products' => 0,
                    'failed_links' => 0,
                    'total_pages_count' => $pagesProcessed,
                    'products' => []
                ];
            }
        } else {
            $this->log("Fetching product links from web...", self::COLOR_GREEN);
            $result = $this->linkScraper->fetchProductLinks();
            $links = $result['links'] ?? [];
            $pagesProcessed = $result['pages_processed'] ?? 0;

            $this->log("Got " . count($links) . " unique product links from web", self::COLOR_GREEN);
            $this->log("Links structure: " . json_encode(array_slice($links, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "...", self::COLOR_YELLOW);

            if (!empty($links)) {
                $this->databaseManager->saveProductLinksToDatabase($links);
            } else {
                $this->log("No links collected from web. Stopping scrape.", self::COLOR_YELLOW);
                return [
                    'status' => 'success',
                    'total_products' => 0,
                    'failed_links' => 0,
                    'total_pages_count' => $pagesProcessed,
                    'products' => []
                ];
            }
        }

        // حذف لینک‌های تکراری
        $uniqueLinks = array_values(array_unique(array_map(function ($link) {
            return is_array($link) ? $link['url'] : $link;
        }, $links)));
        $this->log("After deduplication, processing " . count($uniqueLinks) . " unique links", self::COLOR_GREEN);

        // پردازش لینک‌های جمع‌آوری‌شده
        $processingMethod = $this->config['processing_method'] ?? $this->config['method'] ?? 1;
        $this->log("Processing links using method: $processingMethod", self::COLOR_GREEN);
        $processedResult = $this->processPagesInBatches($uniqueLinks, $processingMethod);

        // Get failed links count from database
        $failedLinksCount = FailedLink::count();

        // تلاش مجدد برای لینک‌های شکست‌خورده
        if ($failedLinksCount > 0) {
            $this->log("Found $failedLinksCount failed links in database. Attempting to retry...", self::COLOR_PURPLE);

            $processedBefore = $this->processedCount;
            $this->retryFailedLinks();
            $processedDuringRetry = $this->processedCount - $processedBefore;
            $this->log("Successfully processed $processedDuringRetry failed links during retry", self::COLOR_GREEN);
        }

        // Get updated failed links count after retries
        $remainingFailedLinksCount = FailedLink::count();

        $this->log("Scraping completed! Processed: {$this->processedCount}, Failed: {$remainingFailedLinksCount}", self::COLOR_GREEN);

        // جمع‌آوری محصولات از دیتابیس
        $products = Product::all()->map(function ($product) {
            return [
                'title' => $product->title,
                'price' => $product->price,
                'product_id' => $product->product_id ?? '',
                'page_url' => $product->page_url,
                'availability' => (int)$product->availability,
                'off' => (int)$product->off,
                'image' => $product->image,
                'guarantee' => $product->guarantee,
                'category' => $product->category,
            ];
        })->toArray();

        return [
            'status' => 'success',
            'total_products' => $this->processedCount,
            'failed_links' => $remainingFailedLinksCount,
            'total_pages_count' => $pagesProcessed,
            'products' => $products
        ];
    }

    private function processPagesInBatches(array $links, int $processingMethod = null): array
    {
        $this->log("Processing " . count($links) . " product links in batches...", self::COLOR_GREEN);

        $totalProducts = count($links);
        $this->processedCount = 0;
        $processedUrls = [];

        $this->log("Input links: " . json_encode(array_slice($links, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "...", self::COLOR_YELLOW);

        // فیلتر کردن لینک‌های نامعتبر
        $filteredProducts = array_filter($links, function ($product) {
            $url = is_array($product) ? $product['url'] : $product;
            $isValid = !$this->isUnwantedDomain($url) && !$this->isInvalidLink($url);
            if (!$isValid) {
                $this->log("Filtered out unwanted/invalid link: $url", self::COLOR_YELLOW);
            }
            return $isValid;
        });

        $this->log("Filtered to " . count($filteredProducts) . " valid product links", self::COLOR_GREEN);

        // تعیین روش پردازش
        $method = $processingMethod ?? $this->config['method'] ?? 1;
        $this->log("Using processing method: $method", self::COLOR_GREEN);

        // Method 1: Concurrent HTTP requests using Guzzle Pool
        if ($method === 1) {
            $requests = function () use ($filteredProducts) {
                foreach ($filteredProducts as $product) {
                    yield new Request('GET', is_array($product) ? $product['url'] : $product);
                }
            };

            $pool = new Pool($this->httpClient, $requests(), [
                'concurrency' => $this->config['concurrency'] ?? 5,
                'fulfilled' => function ($response, $index) use ($filteredProducts, &$processedUrls, $totalProducts) {
                    $product = $filteredProducts[$index];
                    $url = is_array($product) ? $product['url'] : $product;
                    $image = is_array($product) && isset($product['image']) ? $product['image'] : null;
                    $productId = is_array($product) && isset($product['product_id']) ? $product['product_id'] : '';

                    if (in_array($url, $processedUrls)) {
                        $this->log("Skipping duplicate URL: $url", self::COLOR_YELLOW);
                        return;
                    }

                    $this->processedCount++;
                    $this->log("Processing product {$this->processedCount}/{$totalProducts}: $url", self::COLOR_GREEN);

                    try {
                        $productData = $this->productProcessor->extractProductData($url, (string)$response->getBody(), $image, $productId);

                        if ($productData && $this->productProcessor->validateProductData($productData)) {
                            if (is_array($product) && isset($product['off'])) {
                                $productData['off'] = $product['off'];
                            }

                            DB::beginTransaction();
                            try {
                                $this->productProcessor->saveProductToDatabase($productData);
                                $this->databaseManager->updateLinkProcessedStatus($url);
                                DB::commit();

                                $processedUrls[] = $url;
                                $this->log("Successfully processed: $url", self::COLOR_GREEN);
                            } catch (\Exception $e) {
                                DB::rollBack();
                                $this->saveFailedLink($url, "Database error: " . $e->getMessage());
                                $this->log("Failed to save product: $url - {$e->getMessage()}", self::COLOR_RED);
                            }
                        } else {
                            $this->saveFailedLink($url, "Invalid or missing product data");
                            $this->log("Failed to extract valid data: $url", self::COLOR_RED);
                        }
                    } catch (\Exception $e) {
                        $this->saveFailedLink($url, "Processing error: " . $e->getMessage());
                        $this->log("Processing error: $url - {$e->getMessage()}", self::COLOR_RED);
                    }
                },
                'rejected' => function ($reason, $index) use ($filteredProducts) {
                    $url = is_array($filteredProducts[$index]) ? $filteredProducts[$index]['url'] : $filteredProducts[$index];
                    $this->saveFailedLink($url, "Failed to fetch: " . $reason->getMessage());
                    $this->log("Fetch failed: $url - {$reason->getMessage()}", self::COLOR_YELLOW);
                },
            ]);

            $promise = $pool->promise();
            $promise->wait();
        }
        // Method 2 & 3: Sequential processing
        else {
            $batchSize = $this->config['batch_size'] ?? 75;
            $batches = array_chunk($filteredProducts, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                $this->log("Processing batch " . ($batchIndex + 1) . "/" . count($batches) . " with " . count($batch) . " products", self::COLOR_GREEN);

                foreach ($batch as $product) {
                    $url = is_array($product) ? $product['url'] : $product;
                    $image = is_array($product) && isset($product['image']) ? $product['image'] : null;
                    $productId = is_array($product) && isset($product['product_id']) ? $product['product_id'] : '';

                    if (in_array($url, $processedUrls)) {
                        $this->log("Skipping duplicate URL: $url", self::COLOR_YELLOW);
                        continue;
                    }

                    $this->processedCount++;
                    $this->log("Processing product {$this->processedCount}/{$totalProducts}: $url", self::COLOR_GREEN);

                    try {
                        if ($method === 2) {
                            $productData = $this->processProductPageWithPlaywright($url);
                        } else {
                            $productData = $this->productProcessor->extractProductData($url, null, $image, $productId);
                        }

                        if ($productData === null || (isset($productData['error']) && $productData['error'])) {
                            $error = isset($productData['error']) ? $productData['error'] : "Failed to extract product data";
                            $this->saveFailedLink($url, $error);
                            $this->log("Failed: $url - $error", self::COLOR_RED);
                            continue;
                        }

                        $productData['page_url'] = $url;
                        $productData['image'] = $image ?? ($productData['image'] ?? '');
                        $productData['product_id'] = $productId !== '' ? $productId : ($productData['product_id'] ?? '');
                        $productData['availability'] = isset($productData['availability']) ? (int)$productData['availability'] : 0;
                        $productData['off'] = isset($productData['off']) ? (int)$productData['off'] : 0;
                        $productData['category'] = $productData['category'] ?? '';
                        $productData['guarantee'] = $productData['guarantee'] ?? '';

                        if ($this->productProcessor->validateProductData($productData)) {
                            DB::beginTransaction();
                            try {
                                $this->productProcessor->saveProductToDatabase($productData);
                                $this->databaseManager->updateLinkProcessedStatus($url);
                                DB::commit();

                                $processedUrls[] = $url;
                                $this->log("Successfully processed: $url", self::COLOR_GREEN);
                            } catch (\Exception $e) {
                                DB::rollBack();
                                $this->saveFailedLink($url, "Database error: " . $e->getMessage());
                                $this->log("Failed to save product: $url - {$e->getMessage()}", self::COLOR_RED);
                            }
                        } else {
                            $this->saveFailedLink($url, "Invalid product data: " . json_encode($productData, JSON_UNESCAPED_UNICODE));
                            $this->log("Invalid product data: $url", self::COLOR_RED);
                        }
                    } catch (\Exception $e) {
                        $this->saveFailedLink($url, "Processing error: " . $e->getMessage());
                        $this->log("Processing error: $url - {$e->getMessage()}", self::COLOR_RED);
                    }

                    // Add delay between requests
                    usleep(rand($this->config['request_delay_min'] ?? 1000, $this->config['request_delay_max'] ?? 3000) * 1000);
                }
            }
        }

        $failedLinksCount = FailedLink::count();
        $this->log("Batch processing completed. Processed: {$this->processedCount}, Failed: {$failedLinksCount}", self::COLOR_GREEN);

        return [
            'processed' => $this->processedCount,
            'failed' => $failedLinksCount,
            'pages_processed' => count($filteredProducts)
        ];
    }

    private function runProductTestMode(): array
    {
        $this->log("🚀 Starting Product Test Mode", self::COLOR_GREEN);

        $productUrls = $this->config['product_urls'] ?? [];
        if (empty($productUrls)) {
            $this->log("❌ No product_urls found in config for test mode", self::COLOR_RED);
            return [
                'status' => 'error',
                'message' => 'No product_urls provided for test mode',
                'total_products' => 0,
                'failed_links' => 0,
                'products' => []
            ];
        }

        $this->log("📝 Found " . count($productUrls) . " product URLs to test", self::COLOR_GREEN);

        $successfulProducts = [];
        $failedProducts = [];

        foreach ($productUrls as $index => $url) {
            $this->log("🔍 Testing product " . ($index + 1) . "/" . count($productUrls) . ": $url", self::COLOR_BLUE);

            try {
                $this->log("📡 Attempting to extract product data...", self::COLOR_YELLOW);
                $productData = $this->productProcessor->extractProductData($url);

                if ($productData !== null) {
                    $successfulProducts[] = $productData;
                    $this->log("✅ Product data extracted successfully!", self::COLOR_GREEN);

                    $this->log("📦 Product: {$productData['title']}", self::COLOR_BLUE);
                    $this->log("💰 Price: {$productData['price']}", self::COLOR_BLUE);
                    $this->log("📊 Available: " . ($productData['availability'] ? 'Yes' : 'No'), self::COLOR_BLUE);

                } else {
                    $failedProducts[] = $url;
                    $this->log("❌ Failed to extract data - productData is null", self::COLOR_RED);
                }

            } catch (\Exception $e) {
                $failedProducts[] = $url;
                $this->log("💥 Exception occurred: " . $e->getMessage(), self::COLOR_RED);
                $this->log("📍 File: " . $e->getFile() . " Line: " . $e->getLine(), self::COLOR_YELLOW);
            }

            $this->log("⏱️ Applying delay...", self::COLOR_YELLOW);
            $delay = mt_rand(500, 1000);
            usleep($delay * 1000);
            $this->log("✅ Delay completed, continuing...", self::COLOR_GREEN);
        }

        // خلاصه نتایج
        $this->log("", null);
        $this->log("📊 Test Results Summary:", self::COLOR_PURPLE);
        $successCount = count($successfulProducts);
        $failCount = count($failedProducts);
        $totalCount = count($productUrls);

        $this->log("  ✅ Successful: $successCount", self::COLOR_GREEN);
        $this->log("  ❌ Failed: $failCount", self::COLOR_RED);

        if ($totalCount > 0) {
            $successRate = round(($successCount / $totalCount) * 100, 2);
            $this->log("  📈 Success Rate: {$successRate}%", self::COLOR_BLUE);
        }

        if (!empty($failedProducts)) {
            $this->log("", null);
            $this->log("💀 Failed URLs:", self::COLOR_RED);
            foreach ($failedProducts as $failedUrl) {
                $this->log("  - $failedUrl", self::COLOR_YELLOW);
            }
        }

        $this->log("🏁 Product Test Mode completed!", self::COLOR_GREEN);

        return [
            'status' => 'success',
            'test_mode' => true,
            'total_tested' => $totalCount,
            'total_products' => $successCount,
            'failed_links' => $failCount,
            'success_rate' => $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0,
            'products' => $successfulProducts,
            'failed_urls' => $failedProducts
        ];
    }

    // Helper methods که هنوز نیاز هستند
    private function retryFailedLinks(): void
    {
        // Implementation for retrying failed links
        // This would use the productProcessor and other helper classes
        $this->log("Retry functionality needs to be implemented with new architecture", self::COLOR_YELLOW);
    }

    private function processProductPageWithPlaywright(string $url): ?array
    {
        // Placeholder for Playwright processing
        $this->log("Playwright processing not implemented in refactored version", self::COLOR_YELLOW);
        return null;
    }

    private function saveFailedLink(string $url, string $errorMessage): void
    {
        try {
            $existingFailedLink = FailedLink::where('url', $url)->first();

            if ($existingFailedLink) {
                $oldAttempts = $existingFailedLink->attempts;
                $existingFailedLink->update([
                    'attempts' => $oldAttempts + 1,
                    'error_message' => $errorMessage,
                    'updated_at' => now()
                ]);

                $this->log("🔄 لینک ناموفق آپدیت شد (تلاش #{$existingFailedLink->attempts}): $url", self::COLOR_YELLOW);
                $this->log("  └─ خطا: $errorMessage", self::COLOR_RED);

            } else {
                FailedLink::create([
                    'url' => $url,
                    'attempts' => 1,
                    'error_message' => $errorMessage,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $this->log("❌ لینک جدید به failed_links اضافه شد: $url", self::COLOR_RED);
                $this->log("  └─ خطا: $errorMessage", self::COLOR_RED);
            }

        } catch (\Exception $e) {
            $this->log("💥 خطا در ذخیره failed_link $url: {$e->getMessage()}", self::COLOR_RED);
        }
    }

    private function randomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
            // ... more user agents would be here
        ];
        return $agents[array_rand($agents)];
    }

    private function isUnwantedDomain(string $url): bool
    {
        $unwantedDomains = [
            'telegram.me', 't.me', 'wa.me', 'whatsapp.com', 'aparat.com', 'rubika.ir', 'sapp.ir', 'igap.net', 'bale.ai',
        ];

        $parsedUrl = parse_url($url, PHP_URL_HOST);
        if (!$parsedUrl) {
            return true;
        }

        foreach ($unwantedDomains as $domain) {
            if (stripos($parsedUrl, $domain) !== false) {
                $this->log("Skipping unwanted domain: $url", self::COLOR_YELLOW);
                return true;
            }
        }

        return false;
    }

    private function isInvalidLink(?string $href): bool
    {
        return empty($href) || $href === '#' || stripos($href, 'javascript:') === 0;
    }

    public function handleOutput(string $message): void
    {
        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $message);
        } else {
            echo $message . PHP_EOL;
        }
    }

    private function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        // ذخیره در فایل لاگ
        $logFile = storage_path('logs/scraper_' . date('Ymd') . '.log');
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);

        // حذف کدهای رنگی برای بررسی
        $cleanMessage = preg_replace("/\033\[[0-9;]*m/", "", $message);

        // شرایط نمایش لاگ‌های مهم
        $shouldDisplay = $this->shouldDisplayLog($cleanMessage);

        if ($shouldDisplay) {
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, $formattedMessage);
            } else {
                echo $formattedMessage . PHP_EOL;
            }
        }
    }

    private function shouldDisplayLog(string $cleanMessage): bool
    {
        $displayConditions = [
            str_contains($cleanMessage, '🆕') || str_contains($cleanMessage, '🔄') ||
            str_contains($cleanMessage, '✅') || str_contains($cleanMessage, '❌'),
            str_starts_with($cleanMessage, '+') && str_contains($cleanMessage, '|'),
            str_starts_with($cleanMessage, 'Fetching page') ||
            str_starts_with($cleanMessage, 'Completed processing page') ||
            str_contains($cleanMessage, 'Extracted product_id') ||
            str_contains($cleanMessage, 'failed_links') ||
            str_contains($cleanMessage, 'Failed to fetch') ||
            str_contains($cleanMessage, 'Invalid link') ||
            str_contains($cleanMessage, '═══') || str_contains($cleanMessage, '───') ||
            str_contains($cleanMessage, 'Playwright') ||
            str_contains($cleanMessage, 'Starting Playwright') ||
            str_contains($cleanMessage, 'Temporary script file') ||
            str_contains($cleanMessage, 'Playwright console log')
        ];

        return array_reduce($displayConditions, function ($carry, $condition) {
            return $carry || $condition;
        }, false);
    }
}
