<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class LinkScraper
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_BLUE = "\033[1;94m";
    private ProductDataProcessor $productProcessor;
    private StartController $startController;
    private array $config;
    private Client $httpClient;
    private $outputCallback = null;

    public function __construct(array $config, Client $httpClient, StartController $startController)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->startController = $startController;
        // اضافه کردن وابستگی به ProductDataProcessor
        $this->productProcessor = new ProductDataProcessor($config);
        $this->productProcessor->setOutputCallback([$this, 'handleOutput']);

    }

    public function handleOutput(string $message): void
    {
        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $message);
        } else {
            echo $message . PHP_EOL;
        }
    }

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    public function fetchProductLinks(): array
    {
        $method = $this->config['method'] ?? 1;
        $this->log("🔄 STARTING fetchProductLinks - Method: $method", self::COLOR_GREEN);

        $this->log("📄 Config check - products_urls count: " . count($this->config['products_urls'] ?? []), self::COLOR_PURPLE);
        $this->log("📄 Config check - base_urls: " . json_encode($this->config['base_urls'] ?? []), self::COLOR_PURPLE);

        if (!isset($this->config['selectors']['main_page']['product_links'])) {
            throw new \Exception("Main page product_links selector is required.");
        }

        $productLinksSelector = $this->config['selectors']['main_page']['product_links'];
        if (is_array($productLinksSelector)) {
            $this->log("✅ Product links selector found (array): " . json_encode($productLinksSelector), self::COLOR_GREEN);
        } else {
            $this->log("✅ Product links selector found: " . $productLinksSelector, self::COLOR_GREEN);
        }

        $allLinks = [];
        $totalPagesProcessed = 0;
        $processedUrls = [];

        // برای روش ۳
        if ($method === 3) {
            $this->log("🎯 Using scrapeMethodThree for method 3...", self::COLOR_GREEN);
            $result = $this->scrapeMethodThree();
            $allLinks = $result['links'] ?? [];
            $totalPagesProcessed = $result['pages_processed'] ?? 0;
            $this->log("📊 Method 3 result - Links: " . count($allLinks) . ", Pages: $totalPagesProcessed", self::COLOR_GREEN);
            return [
                'links' => array_values($allLinks),
                'pages_processed' => $totalPagesProcessed
            ];
        }

        // پردازش اولیه برای روش‌های ۱ و ۲
        $this->log("🔄 Processing " . count($this->config['products_urls']) . " product URLs...", self::COLOR_PURPLE);

        foreach ($this->config['products_urls'] as $index => $productUrl) {
            $this->log("🌐 Processing URL " . ($index + 1) . "/" . count($this->config['products_urls']) . ": $productUrl", self::COLOR_PURPLE);

            $normalizedUrl = $this->normalizeUrl($productUrl);
            if (in_array($normalizedUrl, $processedUrls)) {
                $this->log("⚠️ Skipping duplicate products_url: $productUrl", self::COLOR_YELLOW);
                continue;
            }
            $processedUrls[] = $normalizedUrl;

            try {
                $this->log("🔗 Testing connection to: $productUrl", self::COLOR_PURPLE);
                $testContent = $this->fetchPageContent($productUrl, false, false);

                if ($testContent === null) {
                    $this->log("❌ CRITICAL: Cannot fetch content from $productUrl", self::COLOR_RED);
                    continue;
                }

                $this->log("✅ Connection successful - Content length: " . strlen($testContent), self::COLOR_GREEN);
                $this->log("📄 First 200 chars of content: " . substr($testContent, 0, 200), self::COLOR_YELLOW);

                $result = match ($method) {
                    1 => $this->scrapeMethodOneForUrl($productUrl),
                    2 => $this->scrapeWithPlaywright(2, $productUrl),
                    default => throw new \Exception("Invalid method: $method"),
                };

                $this->log("📊 Scrape result: " . json_encode([
                        'links_count' => count($result['links'] ?? []),
                        'pages_processed' => $result['pages_processed'] ?? 0
                    ]), self::COLOR_YELLOW);

                $rawLinks = $result['links'] ?? [];
                $pagesProcessed = $result['pages_processed'] ?? 0;
                $totalPagesProcessed += $pagesProcessed;

                $this->log("🔗 Found " . count($rawLinks) . " raw links from $productUrl", self::COLOR_GREEN);

                if (!empty($rawLinks)) {
                    $this->log("📋 Sample links: " . json_encode(array_slice($rawLinks, 0, 3)), self::COLOR_YELLOW);
                }

                foreach ($rawLinks as $link) {
                    $url = is_array($link) ? ($link['url'] ?? $link) : $link;
                    if ($url && !$this->isUnwantedDomain($url) && !in_array($url, array_column($allLinks, 'url'))) {
                        $productId = is_array($link) && isset($link['product_id']) ? $link['product_id'] : '';
                        if (empty($productId) && ($this->config['product_id_method'] ?? 'selector') === 'url') {
                            $productId = $this->extractProductIdFromUrl($url);
                        }
                        $allLinks[] = [
                            'url' => $url,
                            'sourceUrl' => $productUrl,
                            'product_id' => $productId
                        ];
                    }
                }

                $this->log("📈 Total links so far: " . count($allLinks), self::COLOR_GREEN);

            } catch (\Exception $e) {
                $this->log("💥 ERROR processing $productUrl: " . $e->getMessage(), self::COLOR_RED);
                $this->log("📍 Stack trace: " . $e->getTraceAsString(), self::COLOR_RED);
            }
        }

        $this->log("🏁 FINAL RESULT - Total unique links: " . count($allLinks), self::COLOR_GREEN);

        if (empty($allLinks)) {
            $this->log("🚨 CRITICAL: NO LINKS FOUND AT ALL!", self::COLOR_RED);
        }

        return [
            'links' => array_values($allLinks),
            'pages_processed' => $totalPagesProcessed
        ];
    }

    public function fetchPageContent(string $url, bool $useDeep, bool $isProductPage = true): ?string
    {
        $this->log("🌐 FETCHING: $url", self::COLOR_PURPLE);

        $maxRetries = $this->config['max_retries'] ?? 3;
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            $userAgent = $this->randomUserAgent();
            $this->log("🔄 Attempt $attempt/$maxRetries - UserAgent: " . substr($userAgent, 0, 50) . "...", self::COLOR_GREEN);

            try {
                $parsedUrl = parse_url($url);
                $host = $parsedUrl['host'] ?? 'unknown';
                $this->log("🔍 Testing DNS for host: $host", self::COLOR_PURPLE);

                // بهینه‌سازی تنظیمات HTTP
                $options = [
                    'allow_redirects' => [
                        'track_redirects' => true,
                        'max' => 5
                    ],
                    'verify' => false, // غیرفعال کردن SSL verification
                    'timeout' => 60, // افزایش timeout به 60 ثانیه
                    'connect_timeout' => 30, // افزایش connect timeout
                    'read_timeout' => 45, // تنظیم read timeout
                    'headers' => [
                        'User-Agent' => $userAgent,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                        'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7', // اولویت فارسی
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Connection' => 'keep-alive',
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache',
                        'Upgrade-Insecure-Requests' => '1',
                        'Sec-Fetch-Dest' => 'document',
                        'Sec-Fetch-Mode' => 'navigate',
                        'Sec-Fetch-Site' => 'none',
                        'Sec-Fetch-User' => '?1',
                    ],
                    'curl' => [
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 5,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // فقط IPv4
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_TCP_KEEPIDLE => 300,
                        CURLOPT_FRESH_CONNECT => false,
                        CURLOPT_FORBID_REUSE => false,
                    ]
                ];

                // حذف Referer در صورت مشکل
                if ($attempt > 1) {
                    unset($options['headers']['Referer']);
                    $this->log("🔄 Removing Referer header for retry", self::COLOR_YELLOW);
                }

                $response = $this->httpClient->get($url, $options);

                $statusCode = $response->getStatusCode();
                $this->log("✅ HTTP $statusCode - Content-Type: " . $response->getHeaderLine('Content-Type'), self::COLOR_GREEN);

                $contentLength = $response->getHeaderLine('Content-Length');
                $server = $response->getHeaderLine('Server');
                $this->log("📊 Server: $server, Content-Length: $contentLength", self::COLOR_YELLOW);

                $body = (string)$response->getBody();
                $bodyLength = strlen($body);
                $this->log("📄 Response body length: $bodyLength bytes", self::COLOR_GREEN);

                if (empty($body)) {
                    $this->log("⚠️ Empty response body for $url", self::COLOR_YELLOW);
                    $attempt++;
                    continue;
                }

                // بررسی محتوای مشکوک
                $lowercaseBody = strtolower(substr($body, 0, 2000)); // افزایش محدوده بررسی
                $suspiciousPatterns = [
                    'cloudflare', 'captcha', 'access denied', 'blocked',
                    'forbidden', 'rate limit', 'too many requests',
                    'security check', 'please wait', 'checking your browser'
                ];

                foreach ($suspiciousPatterns as $pattern) {
                    if (strpos($lowercaseBody, $pattern) !== false) {
                        $this->log("🚨 Suspicious pattern detected: '$pattern' in response", self::COLOR_RED);
                        if ($attempt < $maxRetries) {
                            $delay = $this->exponentialBackoff($attempt) * 2; // افزایش تأخیر
                            $this->log("⏳ Waiting longer due to suspicious content: $delay ms", self::COLOR_YELLOW);
                            usleep($delay * 1000);
                        }
                        $attempt++;
                        continue 2; // ادامه حلقه اصلی
                    }
                }

                $this->log("✅ Successfully fetched content from $url", self::COLOR_GREEN);
                return $body;

            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                $responseBody = $e->hasResponse() ? substr((string)$e->getResponse()->getBody(), 0, 200) : 'No response';

                $this->log("❌ Request failed (Attempt $attempt): " . $e->getMessage(), self::COLOR_RED);
                $this->log("📊 Status: $statusCode, Response: $responseBody", self::COLOR_RED);

                // تشخیص دقیق‌تر نوع خطا
                if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
                    $this->log("🔌 Connection error - Possible causes:", self::COLOR_RED);
                    $this->log("   - Firewall blocking", self::COLOR_RED);
                    $this->log("   - Server overload", self::COLOR_RED);
                    $this->log("   - Anti-bot protection", self::COLOR_RED);

                    // تغییر User-Agent در تلاش بعدی
                    if ($attempt < $maxRetries) {
                        $this->log("🔄 Will try with different User-Agent", self::COLOR_YELLOW);
                    }

                } elseif ($e instanceof \GuzzleHttp\Exception\ClientException) {
                    $this->log("👤 Client error (4xx) - Possible blocking/authentication issue", self::COLOR_RED);
                } elseif ($e instanceof \GuzzleHttp\Exception\ServerException) {
                    $this->log("🖥️ Server error (5xx) - Target server issue", self::COLOR_RED);
                }

                if ($attempt < $maxRetries) {
                    $delay = $this->exponentialBackoff($attempt) * 3; // افزایش تأخیر
                    $this->log("⏳ Retrying after $delay ms...", self::COLOR_YELLOW);
                    usleep($delay * 1000);
                }
                $attempt++;

            } catch (\Exception $e) {
                $this->log("💥 Unexpected error: " . $e->getMessage(), self::COLOR_RED);
                $this->log("📍 Exception type: " . get_class($e), self::COLOR_RED);

                // اگر خطای SSL باشد
                if (strpos($e->getMessage(), 'SSL') !== false) {
                    $this->log("🔐 SSL Error detected - trying without SSL verification", self::COLOR_YELLOW);
                }

                return null;
            }
        }

        $this->log("🔴 FAILED to fetch $url after $maxRetries attempts", self::COLOR_RED);
        return null;
    }

    public function scrapeMethodOneForUrl(string $baseUrl): array
    {

        $links = [];
        $currentPage = 1;
        $hasMorePages = true;
        $pagesProcessed = 0;
        $consecutiveEmptyPages = 0;

        while ($hasMorePages && $currentPage <= $this->config['method_settings']['method_1']['pagination']['max_pages']) {
            $pageUrl = $this->buildPaginationUrl($baseUrl, $currentPage, $this->config['method_settings']['method_1']['pagination']);
            $this->log("دریافت صفحه: $pageUrl", self::COLOR_GREEN);
            $body = $this->fetchPageContent($pageUrl, false);

            if ($body === null) {
                $consecutiveEmptyPages++;
                $this->log("خطا در دریافت صفحه $currentPage برای $baseUrl یا ریدایرکت شده. به‌عنوان صفحه خالی در نظر گرفته شد. تعداد صفحات خالی متوالی: $consecutiveEmptyPages", self::COLOR_YELLOW);
                $pagesProcessed++;

                if ($consecutiveEmptyPages >= 3) {
                    $this->log("توقف صفحه‌بندی: 3 صفحه خالی متوالی برای $baseUrl.", self::COLOR_YELLOW);
                    $hasMorePages = false;
                    break;
                }

                $currentPage++;
                continue;
            }

            $crawler = new Crawler($body);
            $linkSelectorConfig = $this->config['selectors']['main_page']['product_links'];
            $selectorType = $linkSelectorConfig['type'] ?? 'css';
            $linkSelector = $linkSelectorConfig['selector'];
            $linkAttribute = $linkSelectorConfig['attribute'] ?? 'href';
            $imageSelector = $this->config['selectors']['main_page']['image']['selector'] ?? '';
            $productIdSelector = $this->config['selectors']['main_page']['product_id']['selector'] ?? '';
            $productIdAttribute = $this->config['selectors']['main_page']['product_id']['attribute'] ?? 'data-product_id';
            $productIdFromLink = $this->config['selectors']['main_page']['product_links']['product_id'] ?? false;
            $productIdSource = $this->config['product_id_source'] ?? 'main_page';

            try {
                // ثبت فضانام برای XML سایت‌مپ
                if ($selectorType === 'xpath') {
                    $crawler->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
                    // اصلاح سلکتور برای استفاده از فضانام
                    $linkSelector = str_replace('//url/loc', '//s:url/s:loc', $linkSelector);
                    $this->log("سلکتور XPath اصلاح‌شده: $linkSelector", self::COLOR_PURPLE);
                }

                $linksFound = ($selectorType === 'xpath')
                    ? $crawler->filterXPath($linkSelector)->count()
                    : $crawler->filter($linkSelector)->count();
                $this->log("صفحه $currentPage -> $linksFound لینک پیدا شد", self::COLOR_GREEN);

                if ($linksFound === 0) {
                    $consecutiveEmptyPages++;
                    $this->log("هیچ محصولی در صفحه $currentPage برای $baseUrl پیدا نشد. تعداد صفحات خالی متوالی: $consecutiveEmptyPages", self::COLOR_YELLOW);
                    $htmlSnippet = substr($body, 0, 500);
                    $this->log("بخشی از HTML صفحه $currentPage: $htmlSnippet", self::COLOR_YELLOW);

                    if ($consecutiveEmptyPages >= 3) {
                        $this->log("توقف صفحه‌بندی: 3 صفحه بدون محصول برای $baseUrl.", self::COLOR_YELLOW);
                        $hasMorePages = false;
                        break;
                    }

                    $currentPage++;
                    $pagesProcessed++;
                    continue;
                }

                $consecutiveEmptyPages = 0;

                $crawlerMethod = ($selectorType === 'xpath') ? 'filterXPath' : 'filter';
                $crawler->$crawlerMethod($linkSelector)->each(function (Crawler $node, $index) use (&$links, $crawler, $imageSelector, $productIdSelector, $productIdAttribute, $productIdFromLink, $productIdSource, $linkAttribute, $selectorType) {
                    $href = ($selectorType === 'xpath' && $linkAttribute === 'text')
                        ? trim($node->text())
                        : $node->attr($linkAttribute);

                    if ($this->isInvalidLink($href)) {
                        $this->log("لینک نامعتبر حذف شد: $href", self::COLOR_YELLOW);
                        return;
                    }

                    $fullUrl = $this->makeAbsoluteUrl($href);
                    if ($this->isUnwantedDomain($fullUrl)) {
                        $this->log("دامنه نامطلوب حذف شد: $fullUrl", self::COLOR_YELLOW);
                        return;
                    }

                    $linkData = ['url' => $fullUrl, 'image' => '', 'product_id' => ''];
                    $this->log("پردازش لینک: $fullUrl", self::COLOR_GREEN);

                    try {
                        $parentNode = $node->ancestors()->first();
                        if (!$parentNode->count()) {
                            $this->log("والد برای لینک پیدا نشد: $fullUrl", self::COLOR_YELLOW);
                        } else {
                            $this->log("والد برای لینک پیدا شد: $fullUrl", self::COLOR_GREEN);
                        }

                        if ($imageSelector) {
                            $this->log("تلاش برای سلکتور تصویر: $imageSelector", self::COLOR_YELLOW);
                            try {
                                $parentNodeHtml = $parentNode->count() ? $parentNode->html() : 'والد وجود ندارد';
                                $this->log("HTML والد: " . substr($parentNodeHtml, 0, 500), self::COLOR_YELLOW);
                                $imageElement = $parentNode->filter($imageSelector);
                                $this->log("تعداد عناصر تصویر پیدا شده: {$imageElement->count()}", self::COLOR_YELLOW);
                                if ($imageElement->count() > 0) {
                                    $image = $imageElement->attr($this->config['selectors']['main_page']['image']['attribute'] ?? 'src');
                                    $this->log("لینک خام تصویر: $image", self::COLOR_YELLOW);
                                    $linkData['image'] = $this->makeAbsoluteUrl($image);
                                    $this->log("تصویر استخراج‌شده از صفحه اصلی: {$linkData['image']} برای $fullUrl", self::COLOR_GREEN);
                                } else {
                                    $this->log("تصویری با ابزار $imageSelector برای $fullUrl پیدا نشد", self::COLOR_YELLOW);
                                }
                            } catch (\Exception $e) {
                                $this->log("خطا در استخراج تصویر برای $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                            }
                        }

                        if ($productIdSource === 'product_links' && $productIdFromLink) {
                            try {
                                $productId = $node->attr($productIdFromLink);
                                $this->log("شناسه محصول خام از لینک‌ها: '$productId' برای $fullUrl", self::COLOR_YELLOW);
                                if ($productId) {
                                    $linkData['product_id'] = $productId;
                                    $this->log("شناسه محصول استخراج شده از لینک‌ها: {$linkData['product_id']} برای $fullUrl", self::COLOR_GREEN);
                                } else {
                                    $this->log("شناسه محصولی با ویژگی $productIdFromLink برای $fullUrl پیدا نشد", self::COLOR_YELLOW);
                                }
                            } catch (\Exception $e) {
                                $this->log("خطا در استخراج شناسه محصول از لینک‌ها برای $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                            }
                        } elseif ($productIdSource === 'main_page') {
                            if ($productIdFromLink) {
                                try {
                                    $productId = $node->attr($productIdFromLink);
                                    if ($productId) {
                                        $linkData['product_id'] = $productId;
                                        $this->log("شناسه محصول استخراج شده از ویژگی: {$linkData['product_id']} برای $fullUrl", self::COLOR_GREEN);
                                    } else {
                                        $this->log("شناسه محصولی با ویژگی $productIdFromLink برای $fullUrl پیدا نشد", self::COLOR_YELLOW);
                                    }
                                } catch (\Exception $e) {
                                    $this->log("خطا در استخراج شناسه از ویژگی لینک برای $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                                }
                            }

                            if (!$linkData['product_id'] && $productIdSelector) {
                                try {
                                    $productIdElements = $crawler->filter($productIdSelector);
                                    if ($productIdElements->count() > 0) {
                                        $productId = $productIdElements->attr($productIdAttribute);
                                        if ($productId) {
                                            $linkData['product_id'] = $productId;
                                            $this->log("شناسه محصول با سلکتور '$productIdSelector': {$linkData['product_id']} برای $fullUrl", self::COLOR_GREEN);
                                        } else {
                                            $this->log("شناسه محصولی با سلکتور '$productIdSelector' برای $fullUrl پیدا نشد", self::COLOR_YELLOW);
                                        }
                                    } else {
                                        $this->log("هیچ عنصری با سلکتور شناسه محصول '$productIdSelector' برای $fullUrl پیدا نشد", self::COLOR_YELLOW);
                                        $ancestorWithId = $node->ancestors()->filter($productIdSelector)->first();
                                        if ($ancestorWithId->count() > 0) {
                                            $productId = $ancestorWithId->attr($productIdAttribute);
                                            $linkData['product_id'] = $productId;
                                            $this->log("شناسه محصول استخراج شده از والد: {$linkData['product_id']} برای $fullUrl", self::COLOR_GREEN);
                                        } else {
                                            $this->log("شناسه محصولی در والدان با سلکتور '$productIdSelector' برای $fullUrl پیدا نشد", self::COLOR_YELLOW);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    $this->log("خطا در استخراج شناسه محصول برای $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                                }
                            }
                        }

                        $links[] = $linkData;
                        $this->log("لینک اضافه شد: $fullUrl", self::COLOR_GREEN);
                    } catch (\Exception $e) {
                        $this->log("خطا در پردازش نود برای $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                    }
                });
            } catch (\Exception $e) {
                $this->log("خطا در پردازش سلکتور '$linkSelector': {$e->getMessage()}", self::COLOR_RED);
                $consecutiveEmptyPages++;
                $pagesProcessed++;
                $currentPage++;
                continue;
            }

            $pagesProcessed++;
            $currentPage++;
        }

        return [
            'links' => array_unique($links, SORT_REGULAR),
            'pages_processed' => $pagesProcessed
        ];
    }

    public function scrapeWithPlaywright(int $method, string $productUrl = ''): array
    {
        if ($method !== 2) {
            $this->log("Playwright is only supported for method 2", self::COLOR_RED);
            return ['links' => [], 'pages_processed' => 0];
        }

        $this->log("Starting Playwright scraping process for URL: $productUrl...", self::COLOR_GREEN);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $config = $this->config;
        $maxPages = $config['method_settings']['method_2']['navigation']['max_pages'] ?? 10;
        $scrollDelay = $config['method_settings']['method_2']['navigation']['scroll_delay'] ?? 3000;
        $paginationMethod = $config['method_settings']['method_2']['navigation']['pagination']['method'] ?? 'url';
        $this->log("Pagination method: $paginationMethod", self::COLOR_YELLOW);

        $linkSelector = addslashes($config['selectors']['main_page']['product_links']['selector'] ?? '');
        $linkAttribute = addslashes($config['selectors']['main_page']['product_links']['attribute'] ?? 'href');
        $imageSelector = addslashes($config['selectors']['main_page']['image']['selector'] ?? '');
        $imageAttribute = addslashes($config['selectors']['main_page']['image']['attribute'] ?? 'src');
        $productIdSelector = addslashes($config['selectors']['main_page']['product_id']['selector'] ?? '');
        $productIdAttribute = addslashes($config['selectors']['main_page']['product_id']['attribute'] ?? 'data-product_id');
        $productIdFromLink = addslashes($config['selectors']['main_page']['product_links']['product_id'] ?? '');
        $imageMethod = addslashes($config['image_method'] ?? 'main_page');
        $productIdSource = addslashes($config['product_id_source'] ?? 'main_page');
        $productIdMethod = addslashes($config['product_id_method'] ?? 'selector');
        $urlFilter = addslashes($config['selectors']['main_page']['product_links']['url_filter'] ?? '');
        $userAgent = addslashes($this->randomUserAgent());
        $container = addslashes($config['container'] ?? '');
        $baseUrl = addslashes($config['base_urls'][0] ?? '');
        $productIdUrlPattern = addslashes($config['product_id_url_pattern'] ?? 'products/(\d+)');

        $paginationConfig = $config['method_settings']['method_2']['navigation']['pagination']['url'] ?? [];
        $paginationType = addslashes($paginationConfig['type'] ?? 'query');
        $paginationParam = addslashes($paginationConfig['parameter'] ?? 'page');
        $paginationSeparator = addslashes($paginationConfig['separator'] ?? '=');
        $paginationSuffix = addslashes($paginationConfig['suffix'] ?? '');
        $useSampleUrl = $paginationConfig['use_sample_url'] ?? false;
        $sampleUrl = addslashes($paginationConfig['sample_url'] ?? '');
        $forceTrailingSlash = $paginationConfig['force_trailing_slash'] ?? false;
        $paginationConfigJson = json_encode($paginationConfig, JSON_UNESCAPED_SLASHES);

        $nextButtonSelector = '';
        if ($paginationMethod === 'next_button') {
            $nextButtonSelector = addslashes($config['method_settings']['method_2']['navigation']['pagination']['next_button']['selector'] ?? '');
            $this->log("Next button selector: $nextButtonSelector", self::COLOR_YELLOW);
            if (empty($nextButtonSelector)) {
                $this->log("Next button selector is required for pagination method 'next_button'", self::COLOR_RED);
                return ['links' => [], 'pages_processed' => 0];
            }
        }

        $playwrightScript = <<<'JAVASCRIPT'
const { chromium } = require('playwright');

(async () => {
    let allLinks = [];
    let pagesProcessed = 0;
    let consoleLogs = [];
    let browser = null;
    let context = null;
    let page = null;
    let pageNumber = 1;
    const seenLinks = new Set();

    const initializeBrowser = async () => {
        console.log('Launching headless Chrome browser...');
        browser = await chromium.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-extensions']
        });

        console.log('Creating new browser context...');
        context = await browser.newContext({
            userAgent: USER_AGENT,
            viewport: { width: 1920, height: 1080 },
            bypassCSP: true,
            ignoreHTTPSErrors: true
        });

        console.log('Creating new page...');
        page = await context.newPage();

        browser.on('disconnected', () => {
            console.log('Browser disconnected unexpectedly.');
            consoleLogs.push('Browser disconnected unexpectedly.');
        });
    };

    const closeBrowser = async () => {
        if (browser) {
            console.log('Closing browser...');
            await browser.close().catch((e) => console.log(`Error closing browser: ${e.message}`));
            browser = null;
            console.log('Browser closed.');
        }
    };

    const buildPaginationUrl = (baseUrl, pageNum) => {
        let url = baseUrl.replace(/\/$/, '');
        const config = PAGINATION_CONFIG;
        const param = PAGINATION_PARAM;
        const separator = PAGINATION_SEPARATOR;
        const type = PAGINATION_TYPE;
        const suffix = PAGINATION_SUFFIX;
        const useSampleUrl = USE_SAMPLE_URL;
        const sampleUrl = SAMPLE_URL;
        const forceTrailingSlash = FORCE_TRAILING_SLASH;

        if (useSampleUrl && sampleUrl && pageNum > 1) {
            const pattern = sampleUrl.replace(new RegExp(`${param}${separator}\\d+`), `${param}${separator}${pageNum}`);
            return pattern;
        }

        if (pageNum === 1 && !suffix) {
            return forceTrailingSlash && type === 'path' ? `${url}/` : url;
        }

        if (type === 'query') {
            return `${url}?${param}${separator}${pageNum}${suffix}`;
        } else if (type === 'path') {
            return forceTrailingSlash ? `${url}/${param}${separator}${pageNum}${suffix}/` : `${url}/${param}${separator}${pageNum}${suffix}`;
        }
        return `${url}?page=${pageNum}`;
    };

    const scrollPage = async () => {
        for (let i = 0; i < 3; i++) {
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            console.log(`Scroll ${i + 1} completed.`);
            await page.waitForTimeout(SCROLL_DELAY);
        }
    };

    const extractLinks = async () => {
        console.log('Waiting for product links to appear (max 30s)...');
        await page.waitForSelector(LINK_SELECTOR, { timeout: 30000 }).catch((e) => {
            console.log(`Error waiting for links: ${e.message}`);
            consoleLogs.push(`Error waiting for links on page ${pageNumber}: ${e.message}`);
        });

        const links = await page.$$eval(LINK_SELECTOR, (elements, args) => {
            const {
                linkAttr,
                imageSel,
                imageAttr,
                productIdSel,
                productIdAttr,
                productIdFromLink,
                imageMethod,
                productIdSource,
                productIdMethod,
                urlFilter,
                container,
                baseUrl,
                productIdUrlPattern
            } = args;
            const linkData = [];
            elements.forEach((element, index) => {
                let href = element.getAttribute(linkAttr);
                if (href && !href.startsWith('javascript:') && !href.startsWith('#') && (!urlFilter || urlFilter.test(href))) {
                    const fullUrl = href.startsWith('http') ? href : new URL(href, baseUrl).href;
                    let image = '';
                    if (imageSel && imageMethod === 'main_page') {
                        const parent = container ? element.closest(container) : element.closest('div');
                        const imageElement = parent ? parent.querySelector(imageSel) : null;
                        image = imageElement ? imageElement.getAttribute(imageAttr) : '';
                    }
                    let productId = '';
                    if (productIdSource === 'product_links' && productIdFromLink) {
                        productId = element.getAttribute(productIdFromLink) || '';
                    } else if (productIdSource === 'main_page' && productIdSel) {
                        const parent = container ? element.closest(container) : element.closest('div');
                        const productIdElement = parent ? parent.querySelector(productIdSel) : null;
                        productId = productIdElement ? productIdElement.getAttribute(productIdAttr) : '';
                    } else if (productIdMethod === 'url') {
                        const pattern = new RegExp(productIdUrlPattern);
                        const match = fullUrl.match(pattern);
                        productId = match ? match[1] : '';
                        console.log(`Extracted product_id: "${productId}" from URL ${fullUrl} using pattern ${productIdUrlPattern}`);
                    }
                    linkData.push({ url: fullUrl, image, product_id: productId });
                    console.log(`Found link ${index + 1}: ${fullUrl}`);
                }
            });
            return linkData;
        }, {
            linkAttr: LINK_ATTRIBUTE,
            imageSel: IMAGE_SELECTOR,
            imageAttr: IMAGE_ATTRIBUTE,
            productIdSel: PRODUCT_ID_SELECTOR,
            productIdAttr: PRODUCT_ID_ATTRIBUTE,
            productIdFromLink: PRODUCT_ID_FROM_LINK,
            imageMethod: IMAGE_METHOD,
            productIdSource: PRODUCT_ID_SOURCE,
            productIdMethod: PRODUCT_ID_METHOD,
            urlFilter: URL_FILTER ? new RegExp(URL_FILTER) : null,
            container: CONTAINER,
            baseUrl: BASE_URL,
            productIdUrlPattern: PRODUCT_ID_URL_PATTERN
        });

        const newLinks = [];
        for (const link of links) {
            if (!seenLinks.has(link.url)) {
                seenLinks.add(link.url);
                newLinks.push(link);
                console.log(`Added new link: ${link.url}`);
            } else {
                console.log(`Skipped duplicate link: ${link.url}`);
                consoleLogs.push(`Skipped duplicate link on page ${pageNumber}: ${link.url}`);
            }
        }

        console.log(`Extracted ${newLinks.length} new product links from page ${pageNumber}.`);
        return newLinks;
    };

    const checkAndClickNextButton = async () => {
        console.log('Checking for "Next Page" button...');

        // ابتدا اسکرول کن تا محتوا بارگذاری شود
        await scrollPage();
        await page.waitForTimeout(2000);

        // چک کن آیا دکمه وجود دارد
        const nextButtonExists = await page.$(NEXT_BUTTON_SELECTOR);
        if (!nextButtonExists) {
            console.log('No "Next Page" button found. Stopping pagination.');
            return false;
        }

        // بررسی وضعیت دکمه
        const buttonInfo = await nextButtonExists.evaluate(el => {
            const style = window.getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            return {
                display: style.display,
                visibility: style.visibility,
                opacity: style.opacity,
                disabled: el.disabled,
                hidden: el.hidden,
                offsetHeight: el.offsetHeight,
                offsetWidth: el.offsetWidth,
                boundingBox: {
                    width: rect.width,
                    height: rect.height,
                    top: rect.top,
                    left: rect.left
                }
            };
        });

        console.log('Button info:', JSON.stringify(buttonInfo));

        // اگر دکمه مخفی است، سعی کن محتوای بیشتری بارگذاری کنی
        if (buttonInfo.display === 'none' || buttonInfo.visibility === 'hidden' ||
            buttonInfo.opacity === '0' || buttonInfo.offsetHeight === 0) {

            console.log('Button is hidden, attempting to trigger content loading...');

            // اسکرول بیشتر کن
            await page.evaluate(() => {
                window.scrollTo(0, document.body.scrollHeight);
            });
            await page.waitForTimeout(3000);

            // منتظر درخواست‌های شبکه باش
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {
                console.log('Network idle timeout, continuing...');
            });

            // دوباره چک کن
            const updatedButton = await page.$(NEXT_BUTTON_SELECTOR);
            if (!updatedButton) {
                console.log('Button disappeared after scroll. No more content.');
                return false;
            }

            const updatedButtonInfo = await updatedButton.evaluate(el => {
                const style = window.getComputedStyle(el);
                return {
                    display: style.display,
                    visibility: style.visibility,
                    opacity: style.opacity,
                    offsetHeight: el.offsetHeight
                };
            });

            console.log('Updated button info:', JSON.stringify(updatedButtonInfo));

            if (updatedButtonInfo.display === 'none' || updatedButtonInfo.visibility === 'hidden' ||
                updatedButtonInfo.opacity === '0' || updatedButtonInfo.offsetHeight === 0) {
                console.log('Button still hidden after scroll. No more content available.');
                return false;
            }
        }

        // اگر دکمه غیرفعال است
        if (buttonInfo.disabled) {
            console.log('Next button is disabled. Stopping pagination.');
            return false;
        }

        // تلاش برای کلیک کردن
        console.log('Attempting to click "Next Page" button...');

        for (let attempt = 1; attempt <= 3; attempt++) {
            try {
                // دریافت مرجع جدید دکمه
                const freshNextButton = await page.$(NEXT_BUTTON_SELECTOR);
                if (!freshNextButton) {
                    console.log(`Next button not found on attempt ${attempt}`);
                    return false;
                }

                // اسکرول به دکمه و مرئی کردن آن
                await freshNextButton.scrollIntoViewIfNeeded();
                await page.waitForTimeout(1000);

                // مجبور کردن دکمه به نمایش (در صورت نیاز)
                await freshNextButton.evaluate(el => {
                    if (el.style.display === 'none') {
                        el.style.display = 'block';
                    }
                    if (el.style.visibility === 'hidden') {
                        el.style.visibility = 'visible';
                    }
                    if (el.style.opacity === '0') {
                        el.style.opacity = '1';
                    }
                });
                await page.waitForTimeout(500);

                // بررسی نوع دکمه
                const buttonType = await freshNextButton.evaluate(el => {
                    return {
                        tagName: el.tagName.toLowerCase(),
                        type: el.type,
                        hasOnClick: el.onclick !== null || el.getAttribute('onclick') !== null,
                        href: el.href
                    };
                });

                console.log(`Button type info:`, JSON.stringify(buttonType));

                // کلیک کردن بر اساس نوع دکمه
                if (buttonType.hasOnClick || buttonType.tagName === 'button' ||
                    (buttonType.tagName === 'input' && buttonType.type === 'button')) {

                    // دکمه AJAX - فقط کلیک کن و منتظر بارگذاری محتوا باش
                    await freshNextButton.click();
                    console.log('Clicked AJAX button, waiting for content...');

                    // منتظر بارگذاری محتوای جدید
                    await page.waitForTimeout(5000);

                    // منتظر آرام شدن شبکه
                    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {
                        console.log('Network idle timeout after AJAX click, continuing...');
                    });

                } else {
                    // دکمه ناوبری - منتظر تغییر صفحه
                    const navigationPromise = page.waitForNavigation({
                        waitUntil: 'domcontentloaded',
                        timeout: 60000
                    }).catch(() => {
                        console.log('Navigation timeout, might be AJAX...');
                    });

                    await freshNextButton.click();
                    await navigationPromise;
                    console.log('Navigation completed.');
                }

                console.log(`Successfully clicked next button on attempt ${attempt}`);
                return true;

            } catch (error) {
                console.log(`Attempt ${attempt} failed: ${error.message}`);

                if (attempt < 3) {
                    await page.waitForTimeout(3000 * attempt);

                    // تلاش برای بازنشانی وضعیت صفحه
                    try {
                        await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
                        await page.waitForTimeout(2000);
                    } catch (reloadError) {
                        console.log('Failed to reload page:', reloadError.message);
                    }
                }
            }
        }

        console.log('Failed to click next button after 3 attempts.');
        return false;
    };

    try {
        await initializeBrowser();

        while (pageNumber <= MAX_PAGES) {
            console.log(`Processing page ${pageNumber}...`);
            let pageUrl = PRODUCTS_URL;

            if (PAGINATION_METHOD === 'url') {
                pageUrl = buildPaginationUrl(PRODUCTS_URL, pageNumber);
                console.log(`Navigating to page: ${pageUrl}...`);

                let success = false;
                for (let attempt = 1; attempt <= 3; attempt++) {
                    try {
                        await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 180000 });
                        success = true;
                        break;
                    } catch (error) {
                        console.log(`Attempt ${attempt} failed to load page ${pageNumber}: ${error.message}`);
                        if (attempt === 3) {
                            console.log(`Failed to load page ${pageNumber} after 3 attempts`);
                            break;
                        }
                        await page.waitForTimeout(5000 * attempt);
                    }
                }

                if (!success) break;

                console.log('Page navigation completed.');
                allLinks.push(...await extractLinks());
                await page.waitForTimeout(5000);

            } else if (PAGINATION_METHOD === 'next_button') {

                if (pageNumber === 1) {
                    console.log(`Navigating to main page: ${pageUrl}...`);
                    let success = false;
                    for (let attempt = 1; attempt <= 3; attempt++) {
                        try {
                            await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 180000 });
                            success = true;
                            break;
                        } catch (error) {
                            console.log(`Attempt ${attempt} failed to load main page: ${error.message}`);
                            if (attempt === 3) {
                                console.log(`Failed to load main page after 3 attempts`);
                                break;
                            }
                            await page.waitForTimeout(5000 * attempt);
                        }
                    }
                    if (!success) break;
                    console.log('Main page navigation completed.');
                }

                // استخراج لینک‌ها
                allLinks.push(...await extractLinks());

                // بررسی و کلیک دکمه بعدی
                const clickSuccess = await checkAndClickNextButton();
                if (!clickSuccess) {
                    console.log('No more pages available. Stopping pagination.');
                    break;
                }

                // منتظر بارگذاری محتوای جدید
                await page.waitForTimeout(3000);
            }

            pageNumber++;
            pagesProcessed++;
        }

        console.log(`Total unique links extracted: ${allLinks.length}`);

    } catch (error) {
        console.error(`Error occurred: ${error.message}`);
        consoleLogs.push(`Error: ${error.message}`);
    } finally {
        await closeBrowser();
        console.log('Final result:', JSON.stringify({ links: allLinks, pagesProcessed, consoleLogs }));
    }
})();
JAVASCRIPT;

        $playwrightScript = str_replace(
            [
                'USER_AGENT', 'PRODUCTS_URL', 'MAX_PAGES', 'LINK_SELECTOR', 'LINK_ATTRIBUTE',
                'IMAGE_SELECTOR', 'IMAGE_ATTRIBUTE', 'PRODUCT_ID_SELECTOR', 'PRODUCT_ID_ATTRIBUTE',
                'PRODUCT_ID_FROM_LINK', 'IMAGE_METHOD', 'PRODUCT_ID_SOURCE', 'PRODUCT_ID_METHOD',
                'URL_FILTER', 'CONTAINER', 'BASE_URL', 'SCROLL_DELAY', 'PAGINATION_CONFIG',
                'PAGINATION_TYPE', 'PAGINATION_PARAM', 'PAGINATION_SEPARATOR', 'PAGINATION_SUFFIX',
                'USE_SAMPLE_URL', 'SAMPLE_URL', 'FORCE_TRAILING_SLASH', 'PAGINATION_METHOD',
                'NEXT_BUTTON_SELECTOR', 'PRODUCT_ID_URL_PATTERN'
            ],
            [
                json_encode($userAgent), json_encode($productUrl), $maxPages, json_encode($linkSelector),
                json_encode($linkAttribute), json_encode($imageSelector), json_encode($imageAttribute),
                json_encode($productIdSelector), json_encode($productIdAttribute), json_encode($productIdFromLink),
                json_encode($imageMethod), json_encode($productIdSource), json_encode($productIdMethod),
                json_encode($urlFilter), json_encode($container), json_encode($baseUrl), $scrollDelay,
                $paginationConfigJson, json_encode($paginationType), json_encode($paginationParam),
                json_encode($paginationSeparator), json_encode($paginationSuffix),
                $useSampleUrl ? 'true' : 'false', json_encode($sampleUrl), $forceTrailingSlash ? 'true' : 'false',
                json_encode($paginationMethod), json_encode($nextButtonSelector), json_encode($productIdUrlPattern)
            ],
            $playwrightScript
        );

        $tempFileBase = tempnam(sys_get_temp_dir(), 'playwright_method2_');
        $tempFile = $tempFileBase . '.cjs';
        rename($tempFileBase, $tempFile);
        file_put_contents($tempFile, $playwrightScript);
        $this->log("Temporary script file created at: $tempFile", self::COLOR_GREEN);

        $nodeModulesPath = base_path('node_modules');
        $this->log("Executing Playwright script: NODE_PATH=$nodeModulesPath node $tempFile", self::COLOR_GREEN);

        $command = "NODE_PATH=$nodeModulesPath node $tempFile 2>&1";
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->log("Failed to start Playwright script process.", self::COLOR_RED);
            unlink($tempFile);
            return ['links' => [], 'pages_processed' => 0];
        }

        $output = '';
        $errorOutput = '';
        $logFile = storage_path('logs/playwright_method2_' . date('Ymd_His') . '.log');

        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line !== false) {
                $output .= $line;
                if (!preg_match('/^\s*\{.*"links":.*\}/', $line)) {
                    $this->log("Playwright output: " . trim($line), self::COLOR_YELLOW);
                }
                if (file_exists(dirname($logFile))) {
                    file_put_contents($logFile, "[STDOUT] " . trim($line) . PHP_EOL, FILE_APPEND);
                }
            }
        }

        while (!feof($pipes[2])) {
            $errorLine = fgets($pipes[2]);
            if ($errorLine !== false) {
                $errorOutput .= $errorLine;
                $this->log("Playwright error: " . trim($errorLine), self::COLOR_RED);
                if (file_exists(dirname($logFile))) {
                    file_put_contents($logFile, "[STDERR] " . trim($errorLine) . PHP_EOL, FILE_APPEND);
                }
            }
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);

        $this->log("Playwright script execution completed with return code: $returnCode", self::COLOR_GREEN);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        if (!empty($errorOutput)) {
            $this->log("Playwright errors detected: {$errorOutput}", self::COLOR_RED);
            return ['links' => [], 'pages_processed' => 0];
        }

        preg_match('/Final result: ({.*})/', $output, $matches);
        if (!isset($matches[1])) {
            $this->log("Failed to parse Playwright output for {$productUrl}. Raw output: {$output}", self::COLOR_RED);
            return ['links' => [], 'pages_processed' => 0];
        }

        $result = json_decode($matches[1], true);
        if (!$result || !isset($result['links'])) {
            $this->log("Invalid Playwright output format for {$productUrl}.", self::COLOR_RED);
            return ['links' => [], 'pages_processed' => 0];
        }

        if (isset($result['console_logs']) && is_array($result['console_logs'])) {
            foreach ($result['console_logs'] as $log) {
                $this->log("Playwright console log: {$log}", self::COLOR_YELLOW);
            }
        }

        $links = array_map(function ($link) use ($productUrl) {
            $url = $this->makeAbsoluteUrl($link['url'], $productUrl);
            $productId = $link['product_id'] ?? '';

            if ($productId === '' && ($this->config['product_id_method'] ?? 'selector') === 'url') {
                $productId = $this->extractProductIdFromUrl($url);
                $this->log("Extracted product_id from URL: {$productId} for {$url}", self::COLOR_GREEN);
            }

            return [
                'url' => $url,
                'image' => $link['image'] ? $this->makeAbsoluteUrl($link['image'], $productUrl) : '',
                'product_id' => $productId
            ];
        }, $result['links']);

        $this->log("Found " . count($links) . " unique links for {$productUrl}.", self::COLOR_GREEN);

        return [
            'links' => array_unique($links, SORT_REGULAR),
            'pages_processed' => $result['pagesProcessed'] ?? 0
        ];
    }
    public function scrapeMethodThree(): array
    {
        $this->log("Starting scrapeMethodThree...", self::COLOR_GREEN);
        $allLinks = [];
        $totalPagesProcessed = 0;
        $processedUrls = [];

        if (!$this->config['method_settings']['method_3']['enabled']) {
            $this->log("Method 3 is not enabled in config.", self::COLOR_RED);
            return ['links' => [], 'pages_processed' => 0];
        }

        if (!$this->config['method_settings']['method_3']['navigation']['use_webdriver']) {
            $this->log("Method 3 requires a WebDriver (use_webdriver must be true).", self::COLOR_RED);
            return ['links' => [], 'pages_processed' => 0];
        }

        foreach ($this->config['products_urls'] as $index => $productsUrl) {
            $normalizedUrl = $this->normalizeUrl($productsUrl);
            if (in_array($normalizedUrl, $processedUrls)) {
                $this->log("Skipping duplicate products_url: $productsUrl", self::COLOR_YELLOW);
                continue;
            }
            $processedUrls[] = $normalizedUrl;

            $this->log("Processing products_url " . ($index + 1) . ": $productsUrl", self::COLOR_PURPLE);

            // تنظیمات پیکربندی
            $baseurl = json_encode($this->config['base_urls'][0] ?? '');
            $scrool = json_encode($this->config['scrool'] ?? '');
            $userAgent = json_encode($this->config['user_agent'][0] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124');
            $linkSelector = json_encode($this->config['selectors']['main_page']['product_links']['selector'] ?? 'a[href*="/product"]');
            $linkAttribute = json_encode($this->config['selectors']['main_page']['product_links']['attribute'] ?? 'href');
            $maxPages = $this->config['method_settings']['method_3']['navigation']['max_iterations'] ?? 5;
            $scrollDelay = $this->config['method_settings']['method_3']['navigation']['timing']['scroll_delay'] ?? 3000;
            $positiveKeywords = json_encode($this->config['availability_keywords']['positive'] ?? []);
            $negativeKeywords = json_encode($this->config['availability_keywords']['negative'] ?? []);

            // سلکتورهای صفحه محصول (اصلاح برای پشتیبانی از آرایه)
            $titleSelector = json_encode(is_array($this->config['selectors']['product_page']['title']['selector'] ?? [])
                ? ($this->config['selectors']['product_page']['title']['selector'][0] ?? '.styles__title___3F4_f')
                : ($this->config['selectors']['product_page']['title']['selector'] ?? '.styles__title___3F4_f'));
            $brandSelector = json_encode(is_array($this->config['selectors']['product_page']['brand']['selector'] ?? [])
                ? ($this->config['selectors']['product_page']['brand']['selector'][0] ?? '')
                : ($this->config['selectors']['product_page']['brand']['selector'] ?? ''));
            $priceSelector = json_encode(is_array($this->config['selectors']['product_page']['price']['selector'] ?? [])
                ? ($this->config['selectors']['product_page']['price']['selector'][0] ?? '.styles__final-price___1L1AM')
                : ($this->config['selectors']['product_page']['price']['selector'] ?? '.styles__final-price___1L1AM'));
            $availabilitySelector = json_encode(is_array($this->config['selectors']['product_page']['availability']['selector'] ?? [])
                ? ($this->config['selectors']['product_page']['availability']['selector'][0] ?? '#buy-button')
                : ($this->config['selectors']['product_page']['availability']['selector'] ?? '#buy-button'));
            $imageSelector = json_encode($this->config['selectors']['product_page']['image']['selector'] ?? '.dFxbiY > img:nth-child(1)');
            $imageAttribute = json_encode($this->config['selectors']['product_page']['image']['attribute'] ?? 'src');
            $categorySelector = json_encode(is_array($this->config['selectors']['product_page']['category']['selector'] ?? [])
                ? ($this->config['selectors']['product_page']['category']['selector'][0] ?? 'a.styles__bread-crumb-item___3xa5Q:nth-child(3)')
                : ($this->config['selectors']['product_page']['category']['selector'] ?? 'a.styles__bread-crumb-item___3xa5Q:nth-child(3)'));
            $guaranteeSelector = json_encode($this->config['selectors']['product_page']['guarantee']['selector'] ?? '');
            $productIdSelector = json_encode(is_array($this->config['selectors']['product_page']['product_id']['selector'] ?? [])
                ? ($this->config['selectors']['product_page']['product_id']['selector'][0] ?? 'head > meta:nth-child(9)')
                : ($this->config['selectors']['product_page']['product_id']['selector'] ?? 'head > meta:nth-child(9)'));
            $productIdAttribute = json_encode(is_array($this->config['selectors']['product_page']['product_id']['attribute'] ?? [])
                ? ($this->config['selectors']['product_page']['product_id']['attribute'][0] ?? 'content')
                : ($this->config['selectors']['product_page']['product_id']['attribute'] ?? 'content'));

            // اضافه کردن تنظیمات product_id_method
            $productIdMethod = json_encode($this->config['product_id_method'] ?? 'selector');
            $productIdSource = json_encode($this->config['product_id_source'] ?? 'selector');

            // تنظیمات صفحه‌بندی
            $paginationConfig = $this->config['method_settings']['method_3']['navigation']['pagination'] ?? [];
            $paginationMethod = json_encode($paginationConfig['method'] ?? 'next_button');
            $paginationConfigJson = json_encode($paginationConfig);

            // اعتبارسنجی
            if (empty($productsUrl)) {
                $this->log("Error: Products URL is empty for index $index.", self::COLOR_RED);
                continue;
            }
            if (empty(json_decode($linkSelector, true))) {
                $this->log("Error: Link selector is not defined in the config.", self::COLOR_RED);
                continue;
            }
            if (json_decode($paginationMethod) === 'next_button' && empty($paginationConfig['next_button']['selector'])) {
                $this->log("Error: Next button selector is required when pagination method is 'next_button'.", self::COLOR_RED);
                continue;
            }
            if (json_decode($paginationMethod) === 'url' && empty($paginationConfig['url'])) {
                $this->log("Error: URL pagination settings are required when pagination method is 'url'.", self::COLOR_RED);
                continue;
            }

            // اسکریپت Playwright برای Method 3
            $playwrightScript = <<<'JAVASCRIPT'
const { chromium } = require('playwright');

(async () => {
    let allLinks = [];
    let allProducts = [];
    let pagesProcessed = 0;
    let consoleLogs = [];
    let browser = null;
    let context = null;
    let page = null;
    let pageNumber = 1;
    const seenLinks = new Set();

    const initializeBrowser = async () => {
        console.log('Launching headless Chrome browser...');
        browser = await chromium.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-extensions']
        });

        console.log('Creating new browser context...');
        context = await browser.newContext({
            userAgent: USER_AGENT,
            viewport: { width: 1920, height: 1080 },
            bypassCSP: true
        });

        console.log('Creating new page...');
        page = await context.newPage();

        browser.on('disconnected', () => {
            console.log('Browser disconnected unexpectedly.');
            consoleLogs.push('Browser disconnected unexpectedly.');
        });
    };

    const closeBrowser = async () => {
        if (browser) {
            console.log('Closing browser...');
            await browser.close().catch((e) => console.log(`Error closing browser: ${e.message}`));
            browser = null;
            console.log('Browser closed.');
        }
    };

    const buildPaginationUrl = (baseUrl, pageNum) => {
        let url = baseUrl;
        const paginationConfig = PAGINATION_CONFIG;
        if (paginationConfig.method === 'url') {
            const urlConfig = paginationConfig.url;
            const param = urlConfig.parameter || 'page';
            const separator = urlConfig.separator || '=';
            const type = urlConfig.type || 'query';
            const suffix = urlConfig.suffix || '';
            const useSampleUrl = urlConfig.use_sample_url || false;
            const sampleUrl = urlConfig.sample_url || '';

            if (useSampleUrl && sampleUrl && pageNum > 1) {
                const pattern = sampleUrl.replace(new RegExp(`${param}${separator}\\d+`), `${param}${separator}${pageNum}`);
                return pattern;
            }

            baseUrl = baseUrl.replace(/\/$/, '');
            if (pageNum === 1 && !suffix) return baseUrl;

            if (type === 'query') {
                return `${baseUrl}?${param}${separator}${pageNum}${suffix}`;
            } else if (type === 'path') {
                return `${baseUrl}/${param}${separator}${pageNum}${suffix}`;
            }
        }
        return `${baseUrl}?page=${pageNum}`;
    };

    const scrollPage = async () => {
        for (let i = 0; i < 'SCROOL'; i++) {
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            console.log(`Scroll ${i + 1} completed.`);
            await page.waitForTimeout(SCROLL_DELAY);
        }
    };

    const extractLinks = async () => {
        console.log('Waiting for product links to appear (max 30s)...');
        await page.waitForSelector(LINK_SELECTOR, { timeout: 30000 }).catch((e) => {
            console.log(`Error waiting for links: ${e.message}`);
            consoleLogs.push(`Error waiting for links on page ${pageNumber}: ${e.message}`);
        });

        const links = await page.$$eval(LINK_SELECTOR, (elements, linkAttribute) => {
            const linkData = [];
            elements.forEach((element, index) => {
                let href = element.getAttribute(linkAttribute);
                if (href) {
                    if (href.endsWith(':')) {
                        href = href.slice(0, -1);
                    }
                    linkData.push(href);
                    console.log(`Found link ${index + 1}: ${href}`);
                }
            });
            return linkData;
        }, LINK_ATTRIBUTE);

        const newLinks = [];
        for (const link of links) {
            if (!seenLinks.has(link)) {
                seenLinks.add(link);
                newLinks.push(link);
                console.log(`Added new link: ${link}`);
            } else {
                console.log(`Skipped duplicate link: ${link}`);
                consoleLogs.push(`Skipped duplicate link on page ${pageNumber}: ${link}`);
            }
        }

        console.log(`Extracted ${newLinks.length} new product links from page ${pageNumber}.`);
        return newLinks;
    };

    const processProduct = async (link, index) => {
        const absoluteLink = link.startsWith('http') ? link : `${BASEURL}${link}`;
        console.log(`Processing link ${index + 1}: ${absoluteLink}`);

        let productData = {
            url: absoluteLink,
            title: '',
            price: '',
            availability: '',
            image: '',
            category: '',
            guarantee: '',
            product_id: '',
            raw_brand_text: '', // متن خام برند از selector
            brand: '', // برند نهایی (خالی می‌گذاریم تا در PHP پردازش شود)
            error: ''
        };

        try {
            console.log(`Navigating to ${absoluteLink}...`);
            await page.goto(absoluteLink, { waitUntil: 'domcontentloaded', timeout: 120000 });
            console.log('Product page navigation completed.');

            console.log('Waiting for title selector...');
            await page.waitForSelector(TITLE_SELECTOR, { timeout: 20000 }).catch((e) => {
                console.log(`Title selector not found: ${e.message}`);
            });
            productData.title = await page.evaluate((selector) => {
                const element = document.querySelector(selector);
                return element ? element.textContent.trim() : '';
            }, TITLE_SELECTOR);
            console.log(`Extracted title: ${productData.title}`);

            console.log('Waiting for price selector...');
            await page.waitForSelector(PRICE_SELECTOR, { timeout: 20000 }).catch((e) => {
                console.log(`Price selector not found: ${e.message}`);
            });
            productData.price = await page.evaluate((selector) => {
                const element = document.querySelector(selector);
                return element ? element.textContent.trim() : '';
            }, PRICE_SELECTOR);
            console.log(`Extracted price: ${productData.price}`);

            console.log('Waiting for availability selector...');
            let availabilityValue = 0;
            try {
                await page.waitForSelector(AVAILABILITY_SELECTOR, { timeout: 50000 });
                const availabilityText = await page.evaluate((selector) => {
                    const element = document.querySelector(selector);
                    return element ? element.textContent.trim() : '';
                }, AVAILABILITY_SELECTOR);

                console.log(`Raw availability text: ${availabilityText}`);
                const positiveKeywords = POSITIVE_KEYWORDS;
                const negativeKeywords = NEGATIVE_KEYWORDS;

                if (positiveKeywords.some(keyword => availabilityText.includes(keyword))) {
                    availabilityValue = 1;
                    console.log(`Product is available based on positive keyword: ${availabilityText}`);
                } else if (negativeKeywords.some(keyword => availabilityText.includes(keyword))) {
                    availabilityValue = 0;
                    console.log(`Product is out of stock based on negative keyword: ${availabilityText}`);
                } else {
                    console.log(`No matching availability keywords found for text: ${availabilityText}, defaulting to unavailable`);
                }
            } catch (e) {
                console.log(`Availability selector not found: ${e.message}`);
                availabilityValue = 0;
            }
            console.log(`Final availability value: ${availabilityValue}`);
            productData.availability = availabilityValue;

            console.log('Waiting for image selector...');
            await page.waitForSelector(IMAGE_SELECTOR, { timeout: 20000 }).catch((e) => {
                console.log(`Image selector not found: ${e.message}`);
            });
            productData.image = await page.evaluate(({ selector, attr }) => {
                const element = document.querySelector(selector);
                return element ? element.getAttribute(attr) : '';
            }, { selector: IMAGE_SELECTOR, attr: IMAGE_ATTRIBUTE });
            console.log(`Extracted image: ${productData.image}`);

            console.log('Waiting for category selector...');
            await page.waitForSelector(CATEGORY_SELECTOR, { timeout: 20000 }).catch((e) => {
                console.log(`Category selector not found: ${e.message}`);
            });
            productData.category = await page.evaluate((selector) => {
                const element = document.querySelector(selector);
                return element ? element.textContent.trim() : '';
            }, CATEGORY_SELECTOR);
            console.log(`Extracted category: ${productData.category}`);

            if (GUARANTEE_SELECTOR && GUARANTEE_SELECTOR.trim() !== '') {
                console.log('Waiting for guarantee selector...');
                await page.waitForSelector(GUARANTEE_SELECTOR, { timeout: 20000 }).catch((e) => {
                    console.log(`Guarantee selector not found: ${e.message}`);
                });
                productData.guarantee = await page.evaluate((selector) => {
                    const element = document.querySelector(selector);
                    return element ? element.textContent.trim() : '';
                }, GUARANTEE_SELECTOR);
                console.log(`Extracted guarantee: ${productData.guarantee}`);
            } else {
                console.log('No guarantee selector provided. Skipping guarantee extraction.');
                productData.guarantee = '';
            }

            console.log('Extracting product_id...');
            if (PRODUCT_ID_METHOD === 'url' || PRODUCT_ID_SOURCE === 'url') {
                console.log('Using URL method for product_id extraction...');
                const urlPattern = /product\/(\d+)/;
                const match = absoluteLink.match(urlPattern);
                productData.product_id = match ? match[1] : '';
                console.log(`Extracted product_id from URL: ${productData.product_id}`);
            } else if (PRODUCT_ID_SELECTOR && PRODUCT_ID_SELECTOR.trim() !== '') {
                console.log('Using selector method for product_id extraction...');
                console.log('Waiting for product_id selector...');
                await page.waitForSelector(PRODUCT_ID_SELECTOR, { timeout: 5000 }).catch((e) => {
                    console.log(`Product ID selector not found: ${e.message}`);
                });
                productData.product_id = await page.evaluate(({ selector, attr }) => {
                    const element = document.querySelector(selector);
                    return element ? element.getAttribute(attr) || element.textContent.trim() : '';
                }, { selector: PRODUCT_ID_SELECTOR, attr: PRODUCT_ID_ATTRIBUTE });
                console.log(`Extracted product_id from selector: ${productData.product_id}`);
            } else {
                console.log('No product_id method specified, trying URL fallback...');
                const urlPattern = /product\/(\d+)/;
                const match = absoluteLink.match(urlPattern);
                productData.product_id = match ? match[1] : '';
                console.log(`Extracted product_id from URL (fallback): ${productData.product_id}`);
            }

            // استخراج متن خام برند (بدون پردازش)
            console.log('Extracting raw brand text...');
            if (BRAND_SELECTOR && BRAND_SELECTOR.trim() !== '') {
                console.log('Waiting for brand selector...');
                await page.waitForSelector(BRAND_SELECTOR, { timeout: 20000 }).catch((e) => {
                    console.log(`Brand selector not found: ${e.message}`);
                });
                productData.raw_brand_text = await page.evaluate((selector) => {
                    const element = document.querySelector(selector);
                    return element ? element.textContent.trim() : '';
                }, BRAND_SELECTOR);
                console.log(`Extracted raw brand text: ${productData.raw_brand_text}`);
            } else {
                console.log('No brand selector provided. Raw brand text will be empty.');
                productData.raw_brand_text = '';
            }

            // Note: برند نهایی در PHP پردازش خواهد شد
            console.log('Brand processing will be done in PHP using BrandDetectionService');

            allProducts.push(productData);
            console.log(`Processed product ${index + 1}: ${absoluteLink}`);

            const randomDelay = Math.floor(Math.random() * (5000 - 3000 + 1)) + 3000;
            await page.waitForTimeout(randomDelay);

        } catch (error) {
            console.error(`Error processing ${absoluteLink}: ${error.message}`);
            consoleLogs.push(`Error processing ${absoluteLink}: ${error.message}`);
            productData.error = error.message;
            allProducts.push(productData);
            await closeBrowser();
            await initializeBrowser();
        }
    };

    try {
        await initializeBrowser();

        while (pageNumber <= MAX_PAGES) {
            console.log(`Processing page ${pageNumber}...`);

            let pageUrl = PRODUCTS_URL;
            if (PAGINATION_METHOD === 'url') {
                pageUrl = buildPaginationUrl(PRODUCTS_URL, pageNumber);
                console.log(`Navigating to page: ${pageUrl}...`);
                await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 120000 });
                console.log('Page navigation completed.');
                allLinks.push(...await extractLinks());
                await page.waitForTimeout(5000);
            } else if (PAGINATION_METHOD === 'next_button') {
                if (pageNumber === 1) {
                    console.log(`Navigating to main page: ${pageUrl}...`);
                    await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 120000 });
                    console.log('Main page navigation completed.');
                }
                await scrollPage();
                allLinks.push(...await extractLinks());

                console.log('Checking for "Next Page" button...');
                const nextButton = await page.$(NEXT_BUTTON_SELECTOR);
                if (!nextButton || !await nextButton.isVisible()) {
                    console.log('No "Next Page" button found or not visible. Stopping pagination.');
                    break;
                }

                console.log('Clicking "Next Page" button...');
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
                    nextButton.click()
                ]);
                console.log('Next page loaded successfully.');
                await page.waitForTimeout(5000);
            }

            pageNumber++;
            pagesProcessed++;
        }

        console.log(`Total unique links extracted: ${allLinks.length}`);

        for (let index = 0; index < allLinks.length; index++) {
            await processProduct(allLinks[index], index);
        }

        console.log('Final result:', JSON.stringify({ links: allLinks, products: allProducts, pages_processed: pagesProcessed, console_logs: consoleLogs }));

    } catch (error) {
        console.error(`Error occurred: ${error.message}`);
        consoleLogs.push(`Error: ${error.message}`);
    } finally {
        await closeBrowser();
        console.log('Final result:', JSON.stringify({ links: allLinks, products: allProducts, pages_processed: pagesProcessed, console_logs: consoleLogs }));
    }
})();
JAVASCRIPT;

            // جایگزینی placeholderها
            $playwrightScript = str_replace(
                [
                    'USER_AGENT', 'PRODUCTS_URL', 'MAX_PAGES', 'LINK_SELECTOR', 'LINK_ATTRIBUTE',
                    'NEXT_BUTTON_SELECTOR', 'SCROLL_DELAY', 'TITLE_SELECTOR', 'PRICE_SELECTOR',
                    'AVAILABILITY_SELECTOR', 'IMAGE_SELECTOR', 'IMAGE_ATTRIBUTE', 'CATEGORY_SELECTOR',
                    'GUARANTEE_SELECTOR', 'PRODUCT_ID_SELECTOR', 'PRODUCT_ID_ATTRIBUTE',
                    'PRODUCT_ID_METHOD', 'PRODUCT_ID_SOURCE', 'BRAND_SELECTOR', 'BASEURL', 'POSITIVE_KEYWORDS',
                    'NEGATIVE_KEYWORDS', 'PAGINATION_METHOD', 'PAGINATION_CONFIG', 'SCROOL'
                ],
                [
                    $userAgent, json_encode($productsUrl), $maxPages, $linkSelector, $linkAttribute,
                    json_encode($paginationConfig['next_button']['selector'] ?? 'a.next-page'),
                    $scrollDelay, $titleSelector, $priceSelector, $availabilitySelector,
                    $imageSelector, $imageAttribute, $categorySelector, $guaranteeSelector,
                    $productIdSelector, $productIdAttribute, $productIdMethod, $productIdSource,
                    $brandSelector, $baseurl, $positiveKeywords,
                    $negativeKeywords, $paginationMethod, $paginationConfigJson, $scrool
                ],
                $playwrightScript
            );

            // ذخیره اسکریپت موقت
            $tempFileBase = tempnam(sys_get_temp_dir(), 'playwright_method3_');
            $tempFile = $tempFileBase . '.cjs';
            rename($tempFileBase, $tempFile);
            file_put_contents($tempFile, $playwrightScript);

            $this->log("Temporary script file created at: $tempFile", self::COLOR_GREEN);

            // اجرای اسکریپت
            $nodeModulesPath = base_path('node_modules');
            $this->log("Executing Playwright script: NODE_PATH=$nodeModulesPath node $tempFile", self::COLOR_GREEN);

            $command = "NODE_PATH=$nodeModulesPath node $tempFile 2>&1";
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = proc_open($command, $descriptors, $pipes);

            if (!is_resource($process)) {
                $this->log("Failed to start Playwright script process.", self::COLOR_RED);
                unlink($tempFile);
                continue;
            }

            $output = '';
            $logFile = storage_path('logs/playwright_method3_' . date('Ymd_His') . '.log');
            while (!feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line !== false) {
                    $output .= $line;
                    $this->log("Playwright output: " . trim($line), self::COLOR_YELLOW);
                    file_put_contents($logFile, "[STDOUT] " . trim($line) . PHP_EOL, FILE_APPEND);
                }
            }

            while (!feof($pipes[2])) {
                $errorLine = fgets($pipes[2]);
                if ($errorLine !== false) {
                    $this->log("Playwright error: " . trim($errorLine), self::COLOR_RED);
                    file_put_contents($logFile, "[STDERR] " . trim($errorLine) . PHP_EOL, FILE_APPEND);
                }
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);

            $this->log("Playwright script execution completed with return code: $returnCode", self::COLOR_GREEN);

            // تجزیه خروجی
            preg_match('/Final result: ({.*})/', $output, $matches);
            if (!isset($matches[1])) {
                $this->log("Failed to parse Playwright output.", self::COLOR_RED);
                $this->log("Raw output: $output", self::COLOR_RED);
                unlink($tempFile);
                continue;
            }

            $result = json_decode($matches[1], true);
            if (!$result || !isset($result['products'])) {
                $this->log("Invalid Playwright output format.", self::COLOR_RED);
                unlink($tempFile);
                continue;
            }

            if (isset($result['console_logs']) && is_array($result['console_logs'])) {
                foreach ($result['console_logs'] as $log) {
                    $this->log("Playwright console log: $log", self::COLOR_YELLOW);
                }
            }

            // پردازش محصولات و ذخیره با استفاده از وابستگی‌ها
            $links = [];
            $successfulProducts = 0;
            $failedProducts = 0;

            foreach ($result['products'] as $productData) {
                $this->log("Processing product: {$productData['url']}", self::COLOR_YELLOW);

                if (!empty($productData['error'])) {
                    $this->log("Error processing product {$productData['url']}: {$productData['error']}", self::COLOR_RED);
                    $failedProducts++;
                    $this->startController->saveFailedLink($productData['url'], "Error: {$productData['error']}");
                    continue;
                }

                if (empty($productData['title']) && empty($productData['price'])) {
                    $this->log("Product has no title or price: {$productData['url']}", self::COLOR_RED);
                    $failedProducts++;
                    $this->startController->saveFailedLink($productData['url'], "Failed to extract product data");
                    continue;
                }

                // پردازش داده‌های محصول با استفاده از وابستگی‌ها
                $processedData = [
                    'page_url' => $productData['url'],
                    'url' => $productData['url'],
                    'title' => $productData['title'] ?? '',
                    'price' => $this->config['keep_price_format'] ?? false
                        ? $this->productProcessor->cleanPriceWithFormat($productData['price'] ?? '')
                        : (string)$this->productProcessor->cleanPrice($productData['price'] ?? ''),
                    'availability' => isset($productData['availability']) ? (int)$productData['availability'] : 0,
                    'image' => $this->makeAbsoluteUrl($productData['image'] ?? ''),
                    'category' => ($this->config['category_method'] ?? 'selector') === 'title' && !empty($productData['title'])
                        ? $this->extractCategoryFromTitle($productData['title'], $this->config['category_word_count'] ?? 1)
                        : $productData['category'] ?? '',
                    'guarantee' => $this->productProcessor->cleanGuarantee($productData['guarantee'] ?? ''),
                    'brand' => '', // ابتدا خالی می‌گذاریم
                    'product_id' => $productData['product_id'] ?? '',
                    'off' => (int)$this->productProcessor->cleanOff($productData['off'] ?? '0')
                ];

                // 🔥 پردازش برند با سیستم تشخیص هوشمند
                $rawBrandText = $productData['raw_brand_text'] ?? '';
                $detectedBrand = '';

                // اولویت اول: تشخیص از متن خام برند (اگر وجود دارد)
                if (!empty($rawBrandText)) {
                    $this->log("🏷️ Processing brand from selector text: $rawBrandText", self::COLOR_BLUE);
                    $detectedBrand = $this->productProcessor->detectBrandFromTitle($rawBrandText);
                    if ($detectedBrand) {
                        $this->log("✅ Brand detected from selector: $detectedBrand", self::COLOR_GREEN);
                    } else {
                        $this->log("⚠️ No brand matched from selector text: $rawBrandText", self::COLOR_YELLOW);
                    }
                }

                // اولویت دوم: اگر برند از selector پیدا نشد، از عنوان محصول تشخیص بده
                if (empty($detectedBrand) && !empty($processedData['title'])) {
                    $this->log("🔍 No brand found in selector, attempting to detect from title", self::COLOR_BLUE);
                    $detectedBrand = $this->productProcessor->detectBrandFromTitle($processedData['title']);
                    if ($detectedBrand) {
                        $this->log("✅ Brand detected from title: $detectedBrand", self::COLOR_GREEN);
                    } else {
                        $this->log("❌ No brand detected from title", self::COLOR_YELLOW);
                    }
                }

                // تنظیم برند نهایی
                $processedData['brand'] = $detectedBrand;

                // لاگ نتیجه نهایی تشخیص برند
                if (!empty($processedData['brand'])) {
                    $this->log("🏆 Final brand result: {$processedData['brand']} for {$productData['url']}", self::COLOR_GREEN);
                } else {
                    $this->log("⚠️ No brand detected for: {$productData['url']}", self::COLOR_YELLOW);
                }

                // validation با استفاده از ProductDataProcessor
                if ($this->productProcessor->validateProductData($processedData)) {
                    $this->log("✅ Product processed successfully: {$productData['url']}", self::COLOR_GREEN);
                    $this->successfulLinks[] = $productData['url'];
                    $this->productProcessor->saveProductToDatabase($processedData);
                    $successfulProducts++;
                } else {
                    $maxRetries = $this->config['max_retries'] ?? 2;
                    $this->log("🔄 لینک ناموفق آپدیت شد (تلاش #$maxRetries): {$productData['url']}", self::COLOR_YELLOW);
                    $this->log("  └─ خطا: Failed to extract product data", self::COLOR_RED);
                    $this->startController->saveFailedLink($productData['url'], "Failed to extract product data");
                    $failedProducts++;
                }
            }

            $this->log("Products processing summary - Successful: $successfulProducts, Failed: $failedProducts", self::COLOR_PURPLE);
            $allLinks = array_merge($allLinks, $links);
            $totalPagesProcessed += $result['pages_processed'] ?? 0;

            unlink($tempFile);
        }

        $this->log("All products_urls processed successfully. Total links: " . count($allLinks) . ", Total pages: $totalPagesProcessed", self::COLOR_GREEN);

        return [
            'links' => array_unique($allLinks, SORT_REGULAR),
            'pages_processed' => $totalPagesProcessed
        ];
    }

    private function detectBrandFromTitle(string $title): string
    {
        if (empty($title)) {
            return '';
        }

        $this->log("🔍 Attempting to detect brand from title: " . substr($title, 0, 50) . "...", self::COLOR_BLUE);

        // استفاده از BrandDetectionService از ProductDataProcessor
        $detectedBrand = $this->productProcessor->detectBrandFromTitle($title);

        if ($detectedBrand) {
            $this->log("✅ Brand detected from title: $detectedBrand", self::COLOR_GREEN);
            return $detectedBrand;
        } else {
            $this->log("❌ No brand detected from title", self::COLOR_YELLOW);
            return '';
        }
    }

    private function extractCategoryFromTitle(string $title, $wordCount = 1): string
    {
        // استفاده از ProductDataProcessor برای این عملیات نیز
        // اما چون این متد خاص LinkScraper است، آن را به صورت محلی تعریف می‌کنیم
        $cleanTitle = $this->cleanCategoryText($title);
        $words = preg_split('/\s+/', trim($cleanTitle), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) return '';

        $categories = [];

        if (is_array($wordCount)) {
            foreach ($wordCount as $count) {
                if (is_numeric($count) && $count > 0 && $count <= count($words)) {
                    $categoryWords = array_slice($words, 0, $count);
                    $category = implode(' ', $categoryWords);
                    if (!empty($category)) {
                        $categories[] = $category;
                    }
                }
            }
        } else {
            if (is_numeric($wordCount) && $wordCount > 0) {
                $categoryWords = array_slice($words, 0, min($wordCount, count($words)));
                $category = implode(' ', $categoryWords);
                if (!empty($category)) {
                    $categories[] = $category;
                }
            }
        }

        $categories = array_filter(array_unique($categories), function ($cat) {
            return !empty(trim($cat));
        });

        return implode(', ', $categories);
    }

    private function cleanCategoryText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-_,]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

// متد extractProductIdFromUrl که در همین کلاس نیاز است
    private function extractProductIdFromUrl(string $url): string
    {
        if (($this->config['product_id_method'] ?? 'selector') !== 'url') {
            return '';
        }

        $pattern = $this->config['product_id_url_pattern'] ?? 'products/(\d+)';
        try {
            if (preg_match("#$pattern#", $url, $matches)) {
                $this->log("Extracted product_id: {$matches[1]} from URL: {$url}", self::COLOR_GREEN);
                return $matches[1];
            } else {
                $this->log("No product_id matched for URL: {$url} with pattern: {$pattern}", self::COLOR_YELLOW);
            }
        } catch (\Exception $e) {
            $this->log("Invalid pattern for product_id extraction: {$e->getMessage()}", self::COLOR_RED);
        }

        // فال‌بک اضافی برای URL‌های استاندارد
        $path = parse_url($url, PHP_URL_PATH);
        $parts = array_filter(explode('/', trim($path, '/')));
        $parts = array_values($parts); // بازنشانی ایندکس‌ها
        $productIndex = array_search('products', $parts);
        if ($productIndex !== false && isset($parts[$productIndex + 1])) {
            $potentialId = $parts[$productIndex + 1];
            if (is_numeric($potentialId)) {
                $this->log("Extracted fallback product_id: {$potentialId} from URL: {$url}", self::COLOR_GREEN);
                return $potentialId;
            }
        }

        $this->log("No product_id extracted from URL: {$url}", self::COLOR_YELLOW);
        return '';
    }

    // Helper methods
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

    public function exponentialBackoff(int $attempt): int
    {
        return (int)(100 * pow(2, $attempt - 1));
    }

    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (!$parsed) {
            return $url;
        }

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'https://';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        $path = rtrim($path, '/') . '/';
        $path = preg_replace('/\/+/', '/', $path);
        $queryPart = $query ? '?' . $query : '';

        $normalizedUrl = $scheme . $host . $path . $queryPart;
        $this->log("Normalized URL: $url -> $normalizedUrl", self::COLOR_YELLOW);
        return $normalizedUrl;
    }

    private function buildPaginationUrl(string $baseUrl, int $page, array $pagination): string
    {
        $param = $pagination['parameter'] ?? 'page';
        $type = $pagination['type'] ?? 'query';
        $separator = $pagination['separator'] ?? '=';
        $suffix = $pagination['suffix'] ?? '';
        $useSampleUrl = $pagination['use_sample_url'] ?? false;
        $sampleUrl = $pagination['sample_url'] ?? '';
        $forceTrailingSlash = $pagination['force_trailing_slash'] ?? false;

        if ($useSampleUrl && $sampleUrl && $page > 1) {
            try {
                $pattern = $this->extractPaginationPatternFromSample($sampleUrl, $pagination);
                $url = str_replace('{page}', $page, $pattern);
                $this->log("Built pagination URL from sample: $url", self::COLOR_GREEN);
                return $url;
            } catch (\Exception $e) {
                $this->log("Failed to build URL from sample: {$e->getMessage()}. Falling back to standard logic.", self::COLOR_YELLOW);
            }
        }

        $baseUrl = rtrim($baseUrl, '/?');
        if ($forceTrailingSlash) {
            $baseUrl .= '/';
        }

        if ($page === 1 && !$suffix) {
            return $baseUrl;
        }

        if ($type === 'query') {
            return $baseUrl . "?{$param}{$separator}{$page}{$suffix}";
        } elseif ($type === 'path') {
            return $baseUrl . "/{$param}{$separator}{$page}" . ($suffix ? "/{$suffix}" : '');
        } else {
            throw new \Exception("Invalid pagination type: $type. Use 'query' or 'path'.");
        }
    }

    private function extractPaginationPatternFromSample(string $sampleUrl, array $pagination): string
    {
        $param = $pagination['parameter'] ?? 'page';
        $separator = $pagination['separator'] ?? '=';
        $escapedParam = preg_quote($param, '/');
        $escapedSeparator = preg_quote($separator, '/');
        $pattern = "/{$escapedParam}{$escapedSeparator}(\\d+)/";

        if (!preg_match($pattern, $sampleUrl, $matches)) {
            throw new \Exception("Could not extract page number from sample URL: $sampleUrl");
        }

        $pageNumber = $matches[1];
        $basePart = preg_replace($pattern, "{$param}{$separator}{page}", $sampleUrl);

        if (strpos($basePart, "صفحه-$pageNumber") !== false) {
            $basePart = str_replace("صفحه-$pageNumber", "صفحه-{page}", $basePart);
        }

        return $basePart;
    }

    private function isUnwantedDomain(string $url): bool
    {
        $unwantedDomains = [
            'telegram.me',
            't.me',
            'wa.me',
            'whatsapp.com',
            'aparat.com',
            'rubika.ir',
            'sapp.ir',
            'igap.net',
            'bale.ai',
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

    private function makeAbsoluteUrl(string $href): string
    {
        if (empty($href) || $href === '#' || stripos($href, 'javascript:') === 0) {
            return '';
        }

        if (stripos($href, 'http://') === 0 || stripos($href, 'https://') === 0) {
            return urldecode($href);
        }

        $baseUrl = $this->config['base_urls'][0] ?? '';
        if (empty($baseUrl)) {
            $this->log("No base_url defined, cannot create absolute URL for: $href", self::COLOR_RED);
            return $href;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $href = ltrim($href, '/');

        $fullUrl = "$baseUrl/$href";
        return urldecode($fullUrl);
    }

    private function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $formattedMessage);
        } else {
            echo $formattedMessage . PHP_EOL;
        }
    }
}
