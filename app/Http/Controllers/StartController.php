<?php

namespace App\Http\Controllers;

use App\Models\FailedLink;
use App\Models\Product;
use GuzzleHttp\Client;
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
        $this->linkScraper = new LinkScraper($config, $this->httpClient, $this);
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
            $this->log("🔄 Update mode detected", self::COLOR_PURPLE);
        }

        // اعتبارسنجی کانفیگ
        $this->configValidator->validateConfig($this->config);

        // تنظیم دیتابیس
        $this->databaseManager->setupDatabase();

        // اگر در حالت update هستیم، ابتدا reset انجام میدهیم
        if ($isUpdateMode) {
            $this->log("🧹 Resetting products and links for update mode...", self::COLOR_YELLOW);
            $this->databaseManager->resetProductsAndLinks();
        }

        // تنظیم اولیه
        $this->processedCount = 0;

        // اعتبارسنجی start_id
        if ($start_id !== null && $start_id <= 0) {
            $this->log("Invalid start_id: $start_id. Must be a positive integer. Ignoring start_id.", self::COLOR_RED);
            $start_id = null;
        }

        // تعیین نحوه اجرا بر اساس حالت update
        $runMethod = $isUpdateMode ? 'continue' : ($this->config['run_method'] ?? 'new');
        $this->log("Run method: $runMethod", self::COLOR_GREEN);

        $links = [];
        $pagesProcessed = 0;

        if ($runMethod === 'continue' || $isUpdateMode) {
            $this->log("📋 Getting links from database" . ($start_id ? " starting from ID $start_id" : "") . "...", self::COLOR_GREEN);

            // در حالت update، مستقیماً از دیتابیس لینک‌ها را بگیر
            $result = $this->databaseManager->getProductLinksFromDatabase($start_id);
            $links = $result['links'] ?? [];
            $pagesProcessed = $result['pages_processed'] ?? 0;

            $this->log("Got " . count($links) . " links from database", self::COLOR_GREEN);

            if (empty($links)) {
                if ($isUpdateMode) {
                    $this->log("⚠️ No links found in database for update mode. This suggests the database is empty or corrupted.", self::COLOR_YELLOW);
                    return [
                        'status' => 'error',
                        'message' => 'No links found in database for update mode',
                        'total_products' => 0,
                        'failed_links' => 0,
                        'total_pages_count' => 0,
                        'products' => []
                    ];
                } else {
                    $this->log("No links found in database" . ($start_id ? " for ID >= $start_id" : "") . ". Stopping scrape.", self::COLOR_YELLOW);
                    return [
                        'status' => 'success',
                        'total_products' => 0,
                        'failed_links' => 0,
                        'total_pages_count' => $pagesProcessed,
                        'products' => []
                    ];
                }
            }
        } else {
            // حالت معمولی - دریافت لینک‌ها از وب
            $this->log("🌐 Fetching product links from web...", self::COLOR_GREEN);
            $result = $this->linkScraper->fetchProductLinks();
            $links = $result['links'] ?? [];
            $pagesProcessed = $result['pages_processed'] ?? 0;

            $this->log("Got " . count($links) . " unique product links from web", self::COLOR_GREEN);

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

                    // اگر share_product_id_from_method_2 فعال باشد و product_id از متد ۲ آمده باشد، از آن استفاده کن
                    if (($this->config['share_product_id_from_method_2'] ?? false) && !empty($productId)) {
                        $this->log("Using product_id from method 2: {$productId} for {$url}", self::COLOR_GREEN);
                    } elseif (($this->config['product_id_method'] ?? 'selector') === 'url' && empty($productId)) {
                        // فال‌بک: استخراج product_id از URL فقط اگر product_id_method = url باشد
                        $pattern = $this->config['product_id_url_pattern'] ?? 'products/(\d+)';
                        if (preg_match("#$pattern#", $url, $matches)) {
                            $productId = $matches[1];
                            $this->log("Extracted product_id from URL: {$productId} for {$url}", self::COLOR_GREEN);
                        }
                    } else {
                        // اگر product_id_method = selector، استخراج به extractProductData واگذار شود
                        $this->log("Product_id will be extracted from product page for {$url}", self::COLOR_YELLOW);
                    }

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
                            $productData['product_id'] = $productId !== '' ? $productId : ($productData['product_id'] ?? '');
                            $this->log("Extracted product_id: \"{$productData['product_id']}\" for {$url}", self::COLOR_YELLOW);
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
        }// Method 2 & 3: Sequential processing
        else {
            $batchSize = $this->config['batch_size'] ?? 75;
            $batches = array_chunk($filteredProducts, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                $this->log("Processing batch " . ($batchIndex + 1) . "/" . count($batches) . " with " . count($batch) . " products", self::COLOR_GREEN);

                foreach ($batch as $product) {
                    $url = is_array($product) ? $product['url'] : $product;
                    $image = is_array($product) && isset($product['image']) ? $product['image'] : null;
                    $productId = is_array($product) && isset($product['product_id']) ? $product['product_id'] : '';

                    // اگر share_product_id_from_method_2 فعال باشد و product_id از متد ۲ آمده باشد، از آن استفاده کن
                    if (($this->config['share_product_id_from_method_2'] ?? false) && !empty($productId)) {
                        $this->log("Using product_id from method 2: {$productId} for {$url}", self::COLOR_GREEN);
                    } elseif (($this->config['product_id_method'] ?? 'selector') === 'url' && empty($productId)) {
                        // فال‌بک: استخراج product_id از URL فقط اگر product_id_method = url باشد
                        $pattern = $this->config['product_id_url_pattern'] ?? 'products/(\d+)';
                        if (preg_match("#$pattern#", $url, $matches)) {
                            $productId = $matches[1];
                            $this->log("Extracted product_id from URL: {$productId} for {$url}", self::COLOR_GREEN);
                        }
                    } else {
                        // اگر product_id_method = selector، استخراج به extractProductData واگذار شود
                        $this->log("Product_id will be extracted from product page for {$url}", self::COLOR_YELLOW);
                    }

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

                        $this->log("Extracted product_id: \"{$productData['product_id']}\" for {$url}", self::COLOR_YELLOW);

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
        $detailedErrors = []; // اضافه کردن آرایه برای خطاهای تفصیلی

        foreach ($productUrls as $index => $url) {
            $this->log("", null);
            $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_BLUE);
            $this->log("🔍 Testing product " . ($index + 1) . "/" . count($productUrls) . ": $url", self::COLOR_BLUE);
            $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_BLUE);

            $errorDetails = [
                'url' => $url,
                'step' => 'initialization',
                'error_type' => '',
                'error_message' => '',
                'http_status' => null,
                'html_length' => 0,
                'selectors_configured' => false,
                'selectors_found' => [],
                'selectors_missing' => [],
                'extracted_data' => null,
                'validation_errors' => []
            ];

            try {
                // مرحله ۱: دریافت محتوای HTML صفحه
                $this->log("📡 Step 1: Fetching page content...", self::COLOR_YELLOW);
                $errorDetails['step'] = 'fetching_content';

                $response = $this->httpClient->get($url, [
                    'headers' => [
                        'User-Agent' => $this->randomUserAgent(),
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.5',
                        'Connection' => 'keep-alive',
                    ]
                ]);

                $htmlContent = (string)$response->getBody();
                $errorDetails['http_status'] = $response->getStatusCode();
                $errorDetails['html_length'] = strlen($htmlContent);

                if (empty($htmlContent)) {
                    $errorDetails['error_type'] = 'empty_content';
                    $errorDetails['error_message'] = 'Empty HTML content received from server';
                    $failedProducts[] = $url;
                    $detailedErrors[] = $errorDetails;

                    $this->log("❌ CRITICAL ERROR: Empty HTML content received", self::COLOR_RED);
                    $this->log("  └─ HTTP Status Code: " . $response->getStatusCode(), self::COLOR_RED);
                    $this->log("  └─ Response Headers: " . json_encode($response->getHeaders(), JSON_UNESCAPED_UNICODE), self::COLOR_YELLOW);
                    continue;
                }

                $this->log("✅ Page content fetched successfully (" . strlen($htmlContent) . " bytes)", self::COLOR_GREEN);
                $this->log("📄 Response status: " . $response->getStatusCode(), self::COLOR_CYAN);

                // بررسی وجود تگ‌های HTML اساسی
                if (!str_contains($htmlContent, '<html') && !str_contains($htmlContent, '<body')) {
                    $errorDetails['error_type'] = 'invalid_html';
                    $errorDetails['error_message'] = 'Response does not contain valid HTML structure';
                    $failedProducts[] = $url;
                    $detailedErrors[] = $errorDetails;

                    $this->log("❌ CRITICAL ERROR: Invalid HTML structure", self::COLOR_RED);
                    $this->log("  └─ Content preview: " . substr($htmlContent, 0, 200) . "...", self::COLOR_YELLOW);
                    continue;
                }

                // مرحله ۲: بررسی تنظیمات selectors
                $this->log("", null);
                $this->log("🔍 Step 2: Analyzing selector configuration...", self::COLOR_YELLOW);
                $errorDetails['step'] = 'analyzing_selectors';

                if (!isset($this->config['selectors']['product_page'])) {
                    $errorDetails['error_type'] = 'missing_selectors_config';
                    $errorDetails['error_message'] = 'No product_page selectors configuration found';
                    $failedProducts[] = $url;
                    $detailedErrors[] = $errorDetails;

                    $this->log("❌ CRITICAL ERROR: No product_page selectors configured", self::COLOR_RED);
                    $this->log("  └─ Available config keys: " . implode(', ', array_keys($this->config)), self::COLOR_YELLOW);
                    continue;
                }

                $selectors = $this->config['selectors']['product_page'];
                $errorDetails['selectors_configured'] = true;

                $this->log("🎯 Analyzing configured selectors:", self::COLOR_CYAN);

                // بررسی دقیق هر selector
                $selectorAnalysis = [];
                foreach ($selectors as $field => $config) {
                    if (is_array($config) && isset($config['selector'])) {
                        $selectorList = is_array($config['selector']) ? $config['selector'] : [$config['selector']];

                        $this->log("  └─ {$field}:", self::COLOR_GRAY);

                        foreach ($selectorList as $selector) {
                            // تست وجود selector در HTML با روش‌های مختلف
                            $foundMethods = [];

                            // روش ۱: جستجوی مستقیم
                            if (str_contains($htmlContent, $selector)) {
                                $foundMethods[] = 'direct_match';
                            }

                            // روش ۲: بررسی class ها
                            if (str_starts_with($selector, '.')) {
                                $className = substr($selector, 1);
                                if (preg_match('/class=["\'][^"\']*' . preg_quote($className) . '[^"\']*["\']/', $htmlContent)) {
                                    $foundMethods[] = 'class_attribute';
                                }
                            }

                            // روش ۳: بررسی ID ها
                            if (str_starts_with($selector, '#')) {
                                $idName = substr($selector, 1);
                                if (preg_match('/id=["\']' . preg_quote($idName) . '["\']/', $htmlContent)) {
                                    $foundMethods[] = 'id_attribute';
                                }
                            }

                            // روش ۴: بررسی تگ‌ها
                            if (!str_starts_with($selector, '.') && !str_starts_with($selector, '#')) {
                                if (preg_match('/<' . preg_quote($selector) . '[\s>]/', $htmlContent)) {
                                    $foundMethods[] = 'tag_match';
                                }
                            }

                            if (!empty($foundMethods)) {
                                $errorDetails['selectors_found'][] = $field . ':' . $selector;
                                $this->log("    ├─ '{$selector}': ✅ FOUND (" . implode(', ', $foundMethods) . ")", self::COLOR_GREEN);
                            } else {
                                $errorDetails['selectors_missing'][] = $field . ':' . $selector;
                                $this->log("    ├─ '{$selector}': ❌ NOT FOUND", self::COLOR_RED);

                                // پیشنهاد selectors مشابه
                                $this->suggestSimilarSelectors($htmlContent, $selector);
                            }
                        }
                    }
                }

                // مرحله ۳: استخراج داده‌های محصول
                $this->log("", null);
                $this->log("🔍 Step 3: Attempting to extract product data...", self::COLOR_YELLOW);
                $errorDetails['step'] = 'extracting_data';

                $productData = $this->productProcessor->extractProductData($url, $htmlContent);
                $errorDetails['extracted_data'] = $productData;

                if ($productData !== null && !empty($productData)) {
                    $this->log("✅ Product data extracted successfully!", self::COLOR_GREEN);

                    // نمایش تمام داده‌های استخراج شده
                    $this->log("", null);
                    $this->log("📦 RAW EXTRACTED DATA:", self::COLOR_PURPLE);
                    $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);

                    foreach ($productData as $key => $value) {
                        if (is_array($value)) {
                            $this->log("  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), self::COLOR_CYAN);
                        } else {
                            $displayValue = $value === null ? 'NULL' :
                                ($value === '' ? 'EMPTY STRING' :
                                    (is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : $value));
                            $this->log("  {$key}: {$displayValue}", self::COLOR_CYAN);
                        }
                    }
                    $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);

                    // مرحله ۴: اعتبارسنجی داده‌ها
                    $this->log("", null);
                    $this->log("🔍 Step 4: Validating extracted data...", self::COLOR_YELLOW);
                    $errorDetails['step'] = 'validating_data';

                    if ($this->productProcessor->validateProductData($productData)) {
                        $successfulProducts[] = $productData;
                        $this->log("✅ Product data validation PASSED!", self::COLOR_GREEN);

                        // نمایش جزئیات محصول پس از validation
                        $this->displayValidatedProductDetails($productData);

                    } else {
                        $errorDetails['error_type'] = 'validation_failed';
                        $errorDetails['error_message'] = 'Product data validation failed';

                        // تحلیل دقیق مشکلات validation
                        $validationErrors = $this->analyzeValidationFailures($productData);
                        $errorDetails['validation_errors'] = $validationErrors;

                        $failedProducts[] = $url;
                        $detailedErrors[] = $errorDetails;

                        $this->log("❌ Product data validation FAILED", self::COLOR_RED);
                        $this->displayValidationAnalysis($validationErrors);
                    }
                } else {
                    $errorDetails['error_type'] = 'extraction_failed';
                    $errorDetails['error_message'] = 'Failed to extract product data - productData is null or empty';
                    $failedProducts[] = $url;
                    $detailedErrors[] = $errorDetails;

                    $this->log("❌ Failed to extract product data - productData is null or empty", self::COLOR_RED);
                    $this->displayExtractionDebugInfo($htmlContent, $selectors);
                }

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $errorDetails['error_type'] = 'http_request_exception';
                $errorDetails['error_message'] = $e->getMessage();
                if ($e->hasResponse()) {
                    $errorDetails['http_status'] = $e->getResponse()->getStatusCode();
                }

                $failedProducts[] = $url;
                $detailedErrors[] = $errorDetails;

                $this->log("💥 HTTP Request Exception occurred!", self::COLOR_RED);
                $this->log("  └─ Error: " . $e->getMessage(), self::COLOR_RED);

                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $this->log("  └─ HTTP Status: {$statusCode}", self::COLOR_RED);

                    // تحلیل کدهای خطای HTTP
                    $this->analyzeHttpError($statusCode);
                }

            } catch (\Exception $e) {
                $errorDetails['error_type'] = 'general_exception';
                $errorDetails['error_message'] = $e->getMessage();

                $failedProducts[] = $url;
                $detailedErrors[] = $errorDetails;

                $this->log("💥 General Exception occurred!", self::COLOR_RED);
                $this->log("  └─ Error: " . $e->getMessage(), self::COLOR_RED);
                $this->log("  └─ File: " . $e->getFile(), self::COLOR_YELLOW);
                $this->log("  └─ Line: " . $e->getLine(), self::COLOR_YELLOW);

                // نمایش stack trace فقط برای خطاهای مهم
                if (str_contains($e->getMessage(), 'Fatal') || str_contains($e->getMessage(), 'Parse')) {
                    $this->log("  └─ Stack trace: " . $e->getTraceAsString(), self::COLOR_GRAY);
                }
            }

            // اعمال تاخیر بین درخواست‌ها
            if ($index < count($productUrls) - 1) {
                $delayTime = mt_rand(
                    $this->config['request_delay_min'] ?? 500,
                    $this->config['request_delay_max'] ?? 1000
                );
                $this->log("⏱️ Applying delay ({$delayTime}ms) before next request...", self::COLOR_YELLOW);
                usleep($delayTime * 1000);
            }
        }

        // نمایش خلاصه نتایج نهایی با جزئیات خطاها
        $this->displayFinalTestResults($successfulProducts, $failedProducts, $productUrls, $detailedErrors);

        return [
            'status' => 'success',
            'test_mode' => true,
            'total_tested' => count($productUrls),
            'total_products' => count($successfulProducts),
            'failed_links' => count($failedProducts),
            'success_rate' => count($productUrls) > 0 ? round((count($successfulProducts) / count($productUrls)) * 100, 2) : 0,
            'products' => $successfulProducts,
            'failed_urls' => $failedProducts,
            'detailed_errors' => $detailedErrors // اضافه کردن جزئیات خطاها به response
        ];
    }

    private function suggestSimilarSelectors(string $htmlContent, string $originalSelector): void
    {
        // پیشنهاد selectors مشابه بر اساس محتوای HTML
        $suggestions = [];

        if (str_starts_with($originalSelector, '.')) {
            // جستجو برای class های مشابه
            preg_match_all('/class=["\']([^"\']*)["\']/', $htmlContent, $matches);
            $classes = array_unique($matches[1]);

            foreach ($classes as $class) {
                $classNames = explode(' ', $class);
                foreach ($classNames as $className) {
                    if (stripos($className, substr($originalSelector, 1)) !== false && $className !== substr($originalSelector, 1)) {
                        $suggestions[] = '.' . $className;
                    }
                }
            }
        }

        if (!empty($suggestions)) {
            $this->log("      └─ 💡 Similar selectors found: " . implode(', ', array_slice($suggestions, 0, 3)), self::COLOR_YELLOW);
        }
    }

    private function analyzeValidationFailures(array $productData): array
    {
        $errors = [];
        $requiredFields = ['title', 'price'];

        foreach ($requiredFields as $field) {
            if (!isset($productData[$field]) || empty($productData[$field])) {
                $errors[$field] = 'missing_or_empty';
            } elseif ($field === 'price' && !is_numeric(str_replace([',', ' ', 'تومان', 'ریال'], '', $productData['price']))) {
                $errors[$field] = 'invalid_format';
            }
        }

        return $errors;
    }

    private function displayValidationAnalysis(array $validationErrors): void
    {
        $this->log("", null);
        $this->log("🔍 VALIDATION FAILURE ANALYSIS:", self::COLOR_RED);
        $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);

        foreach ($validationErrors as $field => $error) {
            $errorDesc = match ($error) {
                'missing_or_empty' => 'Field is missing or empty',
                'invalid_format' => 'Field has invalid format',
                default => $error
            };

            $this->log("  └─ {$field}: ❌ {$errorDesc}", self::COLOR_RED);
        }

        $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);
    }

    private function displayExtractionDebugInfo(string $htmlContent, array $selectors): void
    {
        $this->log("", null);
        $this->log("🔍 EXTRACTION DEBUG ANALYSIS:", self::COLOR_YELLOW);
        $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);
        $this->log("  └─ HTML length: " . strlen($htmlContent) . " characters", self::COLOR_YELLOW);
        $this->log("  └─ HTML starts with: " . substr($htmlContent, 0, 100) . "...", self::COLOR_YELLOW);

        // بررسی وجود محتوای مهم در HTML
        $importantElements = ['title', 'h1', 'h2', 'price', 'product', 'buy', 'cart'];
        foreach ($importantElements as $element) {
            $count = substr_count(strtolower($htmlContent), $element);
            if ($count > 0) {
                $this->log("  └─ Found '{$element}' {$count} times in HTML", self::COLOR_GREEN);
            }
        }

        $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);
    }

    private function analyzeHttpError(int $statusCode): void
    {
        $errorAnalysis = match ($statusCode) {
            404 => "صفحه مورد نظر یافت نشد - احتمالاً URL اشتباه است یا محصول حذف شده",
            403 => "دسترسی به صفحه ممنوع - ممکن است سایت از bot ها محافظت کند",
            429 => "تعداد درخواست‌ها بیش از حد - باید delay بین درخواست‌ها افزایش یابد",
            500, 502, 503, 504 => "خطای سرور - مشکل در سمت وب‌سایت هدف",
            default => "خطای HTTP ناشناخته"
        };

        $this->log("  └─ تحلیل خطا: {$errorAnalysis}", self::COLOR_YELLOW);
    }

    private function displayValidatedProductDetails(array $productData): void
    {
        $this->log("", null);
        $this->log("📦 VALIDATED PRODUCT DETAILS:", self::COLOR_BLUE);
        $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);
        $this->log("  └─ 🏷️  Title: " . ($productData['title'] ?? 'N/A'), self::COLOR_CYAN);
        $this->log("  └─ 💰 Price: " . ($productData['price'] ?? 'N/A'), self::COLOR_CYAN);
        $this->log("  └─ 📦 Available: " . (isset($productData['availability']) ? ($productData['availability'] ? 'Yes' : 'No') : 'N/A'), self::COLOR_CYAN);

        if (!empty($productData['product_id'])) {
            $this->log("  └─ 🆔 Product ID: " . $productData['product_id'], self::COLOR_CYAN);
        }
        if (!empty($productData['category'])) {
            $this->log("  └─ 📂 Category: " . $productData['category'], self::COLOR_CYAN);
        }
        if (!empty($productData['guarantee'])) {
            $this->log("  └─ 🛡️  Guarantee: " . $productData['guarantee'], self::COLOR_CYAN);
        }
        if (!empty($productData['image'])) {
            $this->log("  └─ 🖼️  Image URL: " . $productData['image'], self::COLOR_CYAN);
        }
        if (isset($productData['off']) && $productData['off'] > 0) {
            $this->log("  └─ 🏷️  Discount: " . $productData['off'] . "%", self::COLOR_CYAN);
        }

        $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);
    }

    private function displayFinalTestResults(array $successfulProducts, array $failedProducts, array $productUrls, array $detailedErrors): void
    {
        $this->log("", null);
        $this->log("", null);
        $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_PURPLE);
        $this->log("📊 FINAL TEST RESULTS SUMMARY", self::COLOR_PURPLE);
        $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_PURPLE);

        $successCount = count($successfulProducts);
        $failCount = count($failedProducts);
        $totalCount = count($productUrls);

        $this->log("  ✅ Successful extractions: {$successCount}", self::COLOR_GREEN);
        $this->log("  ❌ Failed extractions: {$failCount}", self::COLOR_RED);
        $this->log("  📊 Total tested: {$totalCount}", self::COLOR_BLUE);

        if ($totalCount > 0) {
            $successRate = round(($successCount / $totalCount) * 100, 2);
            $this->log("  📈 Success Rate: {$successRate}%", $successRate > 80 ? self::COLOR_GREEN : ($successRate > 50 ? self::COLOR_YELLOW : self::COLOR_RED));
        }

        // نمایش تحلیل تفصیلی خطاها
        if (!empty($detailedErrors)) {
            $this->log("", null);
            $this->log("💀 DETAILED ERROR ANALYSIS:", self::COLOR_RED);
            $this->log("─────────────────────────────────────────────────────────────", self::COLOR_GRAY);

            $errorTypes = [];
            foreach ($detailedErrors as $error) {
                $errorTypes[$error['error_type']] = ($errorTypes[$error['error_type']] ?? 0) + 1;
            }

            foreach ($errorTypes as $type => $count) {
                $this->log("  └─ {$type}: {$count} occurrences", self::COLOR_YELLOW);
            }

            $this->log("", null);
            $this->log("📋 Failed URLs with detailed reasons:", self::COLOR_RED);

            foreach ($detailedErrors as $error) {
                $this->log("  ┌─ URL: {$error['url']}", self::COLOR_YELLOW);
                $this->log("  ├─ Step: {$error['step']}", self::COLOR_GRAY);
                $this->log("  ├─ Error Type: {$error['error_type']}", self::COLOR_RED);
                $this->log("  ├─ Error Message: {$error['error_message']}", self::COLOR_RED);

                if ($error['http_status']) {
                    $this->log("  ├─ HTTP Status: {$error['http_status']}", self::COLOR_CYAN);
                }
                if ($error['html_length'] > 0) {
                    $this->log("  ├─ HTML Length: {$error['html_length']} bytes", self::COLOR_CYAN);
                }
                if (!empty($error['selectors_missing'])) {
                    $this->log("  ├─ Missing Selectors: " . implode(', ', $error['selectors_missing']), self::COLOR_YELLOW);
                }
                if (!empty($error['validation_errors'])) {
                    $this->log("  ├─ Validation Errors: " . implode(', ', array_keys($error['validation_errors'])), self::COLOR_YELLOW);
                }

                $this->log("  └─────────────────────────────────────────────────────────", self::COLOR_GRAY);
            }
        }

        if (!empty($successfulProducts)) {
            $this->log("", null);
            $this->log("🎉 Successfully Extracted Products:", self::COLOR_GREEN);
            foreach ($successfulProducts as $idx => $product) {
                $this->log("  Product " . ($idx + 1) . ":", self::COLOR_CYAN);
                $this->log("    - Title: " . ($product['title'] ?? 'N/A'), self::COLOR_GRAY);
                $this->log("    - Price: " . ($product['price'] ?? 'N/A'), self::COLOR_GRAY);
                $this->log("    - Available: " . (isset($product['availability']) ? ($product['availability'] ? 'Yes' : 'No') : 'N/A'), self::COLOR_GRAY);
            }
        }

        $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_PURPLE);
        $this->log("🏁 Product Test Mode completed!", self::COLOR_GREEN);
    }

    // Helper methods که هنوز نیاز هستند
    private function retryFailedLinks(): void
    {
        $maxAttempts = $this->config['max_retry_attempts'] ?? 3;
        $failedLinks = FailedLink::where('attempts', '<', $maxAttempts)->get();

        if ($failedLinks->isEmpty()) {
            $this->log("✅ هیچ لینک ناموفقی برای تلاش مجدد وجود ندارد", self::COLOR_GREEN);
            return;
        }

        $this->log("🔄 شروع تلاش مجدد برای " . $failedLinks->count() . " لینک ناموفق...", self::COLOR_PURPLE);
        $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_PURPLE);

        $proxies = $this->config['proxies'] ?? [];
        if (empty($proxies)) {
            $this->log("⚠️ هشدار: پروکسی تعریف نشده - استفاده از اتصال مستقیم", self::COLOR_YELLOW);
            $proxies = [['ip' => '', 'port' => '', 'username' => '', 'password' => '']];
        }

        $successCount = 0;
        $stillFailedCount = 0;
        $skippedCount = 0;

        foreach ($failedLinks as $index => $link) {
            $url = $link->url;

            // Check if the URL contains "?add-to-cart="
            if (stripos($url, '?add-to-cart=') !== false) {
                $this->log("⏭️ Skipping URL containing ?add-to-cart=: $url", self::COLOR_YELLOW);
                try {
                    $link->delete();
                    $skippedCount++;
                    $this->log("🗑️ Successfully deleted link: $url", self::COLOR_GREEN);
                } catch (\Exception $e) {
                    $this->log("❌ Failed to delete link: $url - Error: " . $e->getMessage(), self::COLOR_RED);
                }
                continue;
            }

            $attemptNumber = $link->attempts + 1;

            $this->log("🔍 تلاش مجدد [" . ($index + 1) . "/" . $failedLinks->count() . "] - تلاش #{$attemptNumber}: $url", self::COLOR_BLUE);

            try {
                $content = $this->fetchWithProxyAndRandomUA($url, $proxies, 30, $maxAttempts);

                if (!$content) {
                    throw new \Exception("عدم دریافت محتوا پس از چندین تلاش با پروکسی‌های مختلف");
                }

                $productData = $this->productProcessor->extractProductData($url, $content);

                if ($productData && $this->productProcessor->validateProductData($productData)) {
                    DB::beginTransaction();
                    try {
                        $this->productProcessor->saveProductToDatabase($productData);
                        $this->databaseManager->updateLinkProcessedStatus($url, true);

                        // حذف از failed_links
                        $link->delete();

                        DB::commit();

                        // لاگ موفقیت بازیابی
                        $extraInfo = [
                            'تلاش‌های قبلی' => $link->attempts,
                            'زمان بازیابی' => now()->format('H:i:s')
                        ];
                        $this->productProcessor->logProduct($productData, 'RETRY_SUCCESS', $extraInfo);

                        $this->processedCount++;
                        $successCount++;

                        $this->log("🎉 موفقیت در بازیابی لینک: $url", self::COLOR_GREEN);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $this->handleRetryFailure($link, "خطای دیتابیس: " . $e->getMessage());
                        $stillFailedCount++;
                    }
                } else {
                    $this->handleRetryFailure($link, "داده محصول نامعتبر");
                    $stillFailedCount++;
                }
            } catch (\Exception $e) {
                $this->handleRetryFailure($link, "خطا در تلاش مجدد: " . $e->getMessage());
                $stillFailedCount++;
            }

            // فاصله بین لینک‌ها
            $this->log("───────────────────────────────────────────────────────────────", self::COLOR_GRAY);
        }

        // پاکسازی لینک‌های منقضی
        $this->cleanupExhaustedLinks($maxAttempts);

        // گزارش نهایی
        $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_PURPLE);
        $this->log("📊 گزارش تلاش مجدد تکمیل شد:", self::COLOR_PURPLE);
        $this->log("  ✅ موفق: $successCount", self::COLOR_GREEN);
        $this->log("  ❌ ناموفق: $stillFailedCount", self::COLOR_RED);
        $this->log("  ⏭️ پرش شده (به دلیل ?add-to-cart=): $skippedCount", self::COLOR_YELLOW);
        $this->log("═══════════════════════════════════════════════════════════════", self::COLOR_PURPLE);
    }

    private function handleRetryFailure(FailedLink $link, string $errorMessage): void
    {
        $this->log("❌ شکست در تلاش مجدد: {$link->url}", self::COLOR_RED);
        $this->log("  └─ خطا: $errorMessage", self::COLOR_RED);

        $link->attempts = $link->attempts + 1;
        $link->error_message = $errorMessage;
        $link->save();
    }

    private function cleanupExhaustedLinks(int $maxAttempts): void
    {
        $exhaustedLinks = FailedLink::where('attempts', '>=', $maxAttempts)->get();

        if ($exhaustedLinks->count() > 0) {
            $this->log("🗑️  حذف " . $exhaustedLinks->count() . " لینک منقضی از صف تلاش مجدد...", self::COLOR_YELLOW);

            foreach ($exhaustedLinks as $link) {
                $this->log("💀 حداکثر تلاش رسیده - حذف شد: {$link->url}", self::COLOR_RED);
                $this->log("  └─ آخرین خطا: {$link->error_message}", self::COLOR_RED);
            }

            FailedLink::where('attempts', '>=', $maxAttempts)->delete();
            $this->log("✅ لینک‌های منقضی حذف شدند", self::COLOR_GREEN);
        }
    }

    private function fetchWithProxyAndRandomUA(string $url, array $proxies, int $timeout = 30, int $maxRetries = 3): ?string
    {
        // بررسی وجود پروکسی
        if (empty($proxies)) {
            $this->log("No proxies provided for fetchWithProxyAndRandomUA", self::COLOR_YELLOW);
            return null;
        }

        // ثبت لاگ
        $this->log("Attempting to fetch failed URL with proxy and random UA: $url", self::COLOR_BLUE);

        // تعداد تلاش‌ها
        $attempt = 0;
        $maxAttempts = count($proxies) * 2; // هر پروکسی حداکثر دو بار تلاش می‌شود
        $maxAttempts = min($maxAttempts, $maxRetries * 2); // با توجه به محدودیت maxRetries

        // لیست خطاها برای گزارش
        $errors = [];

        while ($attempt < $maxAttempts) {
            // انتخاب یک پروکسی رندوم
            $proxyIndex = array_rand($proxies);
            $proxy = $proxies[$proxyIndex];

            // انتخاب یک User-Agent رندوم
            $userAgent = $this->randomUserAgent();

            // تأخیر متغیر بین درخواست‌ها (بین 1 تا 3 ثانیه)
            $delay = rand(1000, 3000);
            usleep($delay * 1000); // تبدیل به میکروثانیه

            // ایجاد یک session cURL جدید
            $ch = curl_init();

            // تنظیم URL
            curl_setopt($ch, CURLOPT_URL, $url);

            // تنظیم User-Agent
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

            // تنظیم پروکسی
            curl_setopt($ch, CURLOPT_PROXY, $proxy['ip']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);

            // تنظیم نوع پروکسی اگر مشخص شده باشد
            if (!empty($proxy['type'])) {
                $proxyType = CURLPROXY_HTTP; // مقدار پیش‌فرض

                if (strtolower($proxy['type']) === 'socks4') {
                    $proxyType = CURLPROXY_SOCKS4;
                } elseif (strtolower($proxy['type']) === 'socks5') {
                    $proxyType = CURLPROXY_SOCKS5;
                }

                curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);
            }

            // اگر پروکسی نیاز به احراز هویت دارد
            if (!empty($proxy['username']) && !empty($proxy['password'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['username'] . ':' . $proxy['password']);
            }

            // تنظیمات امنیتی
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // غیرفعال کردن بررسی SSL
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // غیرفعال کردن بررسی هاست SSL

            // تنظیمات دیگر
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

            // تنظیم هدرهای اضافی برای شبیه‌سازی بهتر مرورگر
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Referer: ' . parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/'
            ]);

            // فعال کردن اطلاعات خطا
            curl_setopt($ch, CURLOPT_FAILONERROR, true);

            // لاگ اتصال به پروکسی
            $this->log("🔌 Connecting to proxy " . $proxy['ip'] . ":" . $proxy['port'] . " (Type: " . ($proxy['type'] ?? 'unknown') . ") for URL: $url (Attempt " . ($attempt + 1) . ")", self::COLOR_BLUE);

            // اجرای درخواست
            $content = curl_exec($ch);

            // بررسی خطا
            if ($content === false) {
                $errorCode = curl_errno($ch);
                $errorMessage = curl_error($ch);
                $errors[] = "cURL error ($errorCode): $errorMessage with proxy " . $proxy['ip'] . ":" . $proxy['port'];

                $this->log("Attempt " . ($attempt + 1) . " failed: cURL error ($errorCode): $errorMessage", self::COLOR_YELLOW);
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                // اگر کد HTTP مناسب است (2xx یا 3xx)
                if ($httpCode >= 200 && $httpCode < 400) {
                    curl_close($ch);
                    $this->log("Successfully fetched content with proxy " . $proxy['ip'] . ":" . $proxy['port'] . " and UA: " . substr($userAgent, 0, 30) . "...", self::COLOR_GREEN);
                    return $content;
                } else {
                    $errors[] = "HTTP error: $httpCode with proxy " . $proxy['ip'] . ":" . $proxy['port'];
                    $this->log("Attempt " . ($attempt + 1) . " failed: HTTP error $httpCode", self::COLOR_YELLOW);
                }
            }

            // بستن session cURL
            curl_close($ch);

            // افزایش شمارنده تلاش
            $attempt++;

            // محاسبه تأخیر با استفاده از استراتژی exponential backoff
            $backoffDelay = $this->linkScraper->exponentialBackoff($attempt);
            usleep($backoffDelay * 1000); // تبدیل به میکروثانیه
        }

        // لاگ خطاهای نهایی
        $this->log("All attempts failed for URL: $url", self::COLOR_RED);
        foreach ($errors as $index => $error) {
            $this->log("Error " . ($index + 1) . ": $error", self::COLOR_RED);
        }

        return null;
    }

    private function processProductPageWithPlaywright(string $url): ?array
    {
        // Placeholder for Playwright processing
        $this->log("Playwright processing not implemented in refactored version", self::COLOR_YELLOW);
        return null;
    }

    public function saveFailedLink(string $url, string $errorMessage): void
    {
        if (stripos($url, '?add-to-cart=') !== false) {
            $this->log("⏭️ Skipping saving failed link containing ?add-to-cart=: $url", self::COLOR_YELLOW);
            return;
        }
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
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/37.0.2062.94 Chrome/37.0.2062.94 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/600.8.9 (KHTML, like Gecko) Version/8.0.8 Safari/600.8.9',
            'Mozilla/5.0 (iPad; CPU OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H321 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10240',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/600.7.12 (KHTML, like Gecko) Version/8.0.7 Safari/600.7.12',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/600.8.9 (KHTML, like Gecko) Version/7.1.8 Safari/537.85.17',
            'Mozilla/5.0 (iPad; CPU OS 8_4 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H143 Safari/600.1.4',
            'Mozilla/5.0 (iPad; CPU OS 8_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12F69 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.1; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; Touch; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 5.1; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/600.6.3 (KHTML, like Gecko) Version/8.0.6 Safari/600.6.3',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/600.5.17 (KHTML, like Gecko) Version/8.0.5 Safari/600.5.17',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H321 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (iPad; CPU OS 7_1_2 like Mac OS X) AppleWebKit/537.51.2 (KHTML, like Gecko) Version/7.0 Mobile/11D257 Safari/9537.53',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
            'Mozilla/5.0 (Windows NT 6.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (X11; CrOS x86_64 7077.134.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.156 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/600.7.12 (KHTML, like Gecko) Version/7.1.7 Safari/537.85.16',
            'Mozilla/5.0 (Windows NT 6.0; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (iPad; CPU OS 8_1_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B466 Safari/600.1.4',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.3.18 (KHTML, like Gecko) Version/8.0.3 Safari/600.3.18',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 8_1_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B440 Safari/600.1.4',
            'Mozilla/5.0 (Linux; U; Android 4.0.3; en-us; KFTT Build/IML74K) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 8_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12D508 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:39.0) Gecko/20100101 Firefox/39.0',
            'Mozilla/5.0 (iPad; CPU OS 7_1_1 like Mac OS X) AppleWebKit/537.51.2 (KHTML, like Gecko) Version/7.0 Mobile/11D201 Safari/9537.53',
            'Mozilla/5.0 (Linux; U; Android 4.4.3; en-us; KFTHWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/600.6.3 (KHTML, like Gecko) Version/7.1.6 Safari/537.85.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.4.10 (KHTML, like Gecko) Version/8.0.4 Safari/600.4.10',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2',
            'Mozilla/5.0 (iPad; CPU OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) CriOS/45.0.2454.68 Mobile/12H321 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.3; Win64; x64; Trident/7.0; Touch; rv:11.0) like Gecko',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 8_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B410 Safari/600.1.4',
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (Windows NT 6.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; Win64; x64; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; TNJB; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; ARM; Trident/7.0; Touch; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; MDDCJS; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.0; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_4 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H143 Safari/600.1.4',
            'Mozilla/5.0 (Linux; U; Android 4.4.3; en-us; KFASWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) GSA/7.0.55539 Mobile/12H321 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.155 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; Touch; rv:11.0) like Gecko',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:31.0) Gecko/20100101 Firefox/31.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12F70 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; MATBJS; rv:11.0) like Gecko',
            'Mozilla/5.0 (Linux; U; Android 4.0.4; en-us; KFJWI Build/IMM76D) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 7_1 like Mac OS X) AppleWebKit/537.51.2 (KHTML, like Gecko) Version/7.0 Mobile/11D167 Safari/9537.53',
            'Mozilla/5.0 (X11; CrOS armv7l 7077.134.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.156 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64; rv:34.0) Gecko/20100101 Firefox/34.0',
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/7.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10) AppleWebKit/600.1.25 (KHTML, like Gecko) Version/8.0 Safari/600.1.25',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/600.2.5 (KHTML, like Gecko) Version/8.0.2 Safari/600.2.5',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.134 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/600.1.25 (KHTML, like Gecko) Version/8.0 Safari/600.1.25',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:39.0) Gecko/20100101 Firefox/39.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/601.1.56 (KHTML, like Gecko) Version/9.0 Safari/601.1.56',
            'Mozilla/5.0 (Linux; U; Android 4.4.3; en-us; KFSOWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 5_1_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Version/5.1 Mobile/9B206 Safari/7534.48.3',
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 8_1_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B435 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10240',
            'Mozilla/5.0 (Windows NT 6.3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; Touch; LCJB; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; MDDRJS; rv:11.0) like Gecko',
            'Mozilla/5.0 (Linux; U; Android 4.4.3; en-us; KFAPWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; Touch; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; LCJB; rv:11.0) like Gecko',
            'Mozilla/5.0 (Linux; U; Android 4.0.3; en-us; KFOT Build/IML74K) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 6_1_3 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10B329 Safari/8536.25',
            'Mozilla/5.0 (Linux; U; Android 4.4.3; en-us; KFARWI Build/KTU84M) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; ASU2JS; rv:11.0) like Gecko',
            'Mozilla/5.0 (iPad; CPU OS 8_0_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A405 Safari/600.1.4',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.77.4 (KHTML, like Gecko) Version/7.0.5 Safari/537.77.4',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; rv:38.0) Gecko/20100101 Firefox/38.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; yie11; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; MALNJS; rv:11.0) like Gecko',
            'Mozilla/5.0 (iPad; CPU OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) GSA/8.0.57838 Mobile/12H321 Safari/600.1.4',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:39.0) Gecko/20100101 Firefox/39.0',
            'Mozilla/5.0 (Windows NT 10.0; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; MAGWJS; rv:11.0) like Gecko',
            'Mozilla/5.0 (X11; Linux x86_64; rv:31.0) Gecko/20100101 Firefox/31.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/600.5.17 (KHTML, like Gecko) Version/7.1.5 Safari/537.85.14',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.152 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.3; WOW64; Trident/7.0; Touch; TNJB; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; NP06; rv:11.0) like Gecko',
            'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:40.0) Gecko/20100101 Firefox/40.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.155 Safari/537.36 OPR/31.0.1889.174',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.4.8 (KHTML, like Gecko) Version/8.0.3 Safari/600.4.8',
            'Mozilla/5.0 (iPad; CPU OS 7_0_6 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B651 Safari/9537.53',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36',
            'Microsoft Office/16.0 (Windows NT 10.0; Microsoft Outlook 16.0.17928; Pro)',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 OPR/110.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:126.0) Gecko/20100101 Firefox/126.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1.2 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:125.0) Gecko/20100101 Firefox/125.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:124.0) Gecko/20100101 Firefox/124.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/116.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:127.0) Gecko/20100101 Firefox/127.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:123.0) Gecko/20100101 Firefox/123.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/117.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36 Edg/118.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36 Edg/117.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/118.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36 Edg/116.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/119.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36 Edg/115.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Edg/114.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/120.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36 Edg/113.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/113.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/112.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36 Edg/112.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36 Edg/110.0.1587.63',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36 Edg/111.0.1661.62',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/110.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:102.0) Gecko/20100101 Firefox/102.0',
            'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0) Gecko/20100101 Firefox/91.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/111.0',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36 Edg/109.0.1518.78',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36 OPR/94.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/119.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36 Edg/108.0.1462.54',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/109.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0;

 Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:119.0) Gecko/20100101 Firefox/119.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:118.0) Gecko/20100101 Firefox/118.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:117.0) Gecko/20100101 Firefox/117.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:116.0) Gecko/20100101 Firefox/116.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:115.0) Gecko/20100101 Firefox/115.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:114.0) Gecko/20100101 Firefox/114.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:113.0) Gecko/20100101 Firefox/113.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:112.0) Gecko/20100101 Firefox/112.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:111.0) Gecko/20100101 Firefox/111.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:110.0) Gecko/20100101 Firefox/110.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/109.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:107.0) Gecko/20100101 Firefox/107.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:106.0) Gecko/20100101 Firefox/106.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:104.0) Gecko/20100101 Firefox/104.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:103.0) Gecko/20100101 Firefox/103.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:102.0) Gecko/20100101 Firefox/102.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:101.0) Gecko/20100101 Firefox/101.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:100.0) Gecko/20100101 Firefox/100.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:99.0) Gecko/20100101 Firefox/99.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:98.0) Gecko/20100101 Firefox/98.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:97.0) Gecko/20100101 Firefox/97.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:96.0) Gecko/20100101 Firefox/96.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:95.0) Gecko/20100101 Firefox/95.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:94.0) Gecko/20100101 Firefox/94.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:93.0) Gecko/20100101 Firefox/93.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:92.0) Gecko/20100101 Firefox/92.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0) Gecko/20100101 Firefox/91.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:90.0) Gecko/20100101 Firefox/90.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:88.0) Gecko/20100101 Firefox/88.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:87.0) Gecko/20100101 Firefox/87.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:85.0) Gecko/20100101 Firefox/85.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:84.0) Gecko/20100101 Firefox/84.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:83.0) Gecko/20100101 Firefox/83.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:82.0) Gecko/20100101 Firefox/82.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:80.0) Gecko/20100101 Firefox/80.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:79.0) Gecko/20100101 Firefox/79.0'
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

    public function log(string $message, ?string $color = null): void
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
        return true;
    }

}
