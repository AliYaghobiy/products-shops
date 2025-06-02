<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;

class LinkScraper
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_BLUE = "\033[1;94m";

    private array $config;
    private Client $httpClient;
    private $outputCallback = null;

    public function __construct(array $config, Client $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
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
                        $allLinks[] = [
                            'url' => $url,
                            'sourceUrl' => $productUrl
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

                $response = $this->httpClient->get($url, [
                    'allow_redirects' => [
                        'track_redirects' => true,
                        'max' => 5
                    ],
                    'verify' => $this->config['verify_ssl'] ?? false,
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'headers' => [
                        'User-Agent' => $userAgent,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.5',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Referer' => $this->config['base_urls'][0] ?? '',
                        'Connection' => 'keep-alive',
                        'Cache-Control' => 'no-cache',
                    ],
                ]);

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

                $lowercaseBody = strtolower(substr($body, 0, 1000));
                $suspiciousPatterns = ['cloudflare', 'captcha', 'access denied', 'blocked', 'forbidden'];

                foreach ($suspiciousPatterns as $pattern) {
                    if (strpos($lowercaseBody, $pattern) !== false) {
                        $this->log("🚨 Suspicious pattern detected: '$pattern' in response", self::COLOR_RED);
                    }
                }

                $this->log("✅ Successfully fetched content from $url", self::COLOR_GREEN);
                return $body;

            } catch (RequestException $e) {
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
                $responseBody = $e->hasResponse() ? substr((string)$e->getResponse()->getBody(), 0, 200) : 'No response';

                $this->log("❌ Request failed (Attempt $attempt): " . $e->getMessage(), self::COLOR_RED);
                $this->log("📊 Status: $statusCode, Response: $responseBody", self::COLOR_RED);

                if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
                    $this->log("🔌 Connection error - Check network/firewall/DNS", self::COLOR_RED);
                } elseif ($e instanceof \GuzzleHttp\Exception\ClientException) {
                    $this->log("👤 Client error (4xx) - Possible blocking/authentication issue", self::COLOR_RED);
                } elseif ($e instanceof \GuzzleHttp\Exception\ServerException) {
                    $this->log("🖥️ Server error (5xx) - Target server issue", self::COLOR_RED);
                }

                if ($attempt < $maxRetries) {
                    $delay = $this->exponentialBackoff($attempt);
                    $this->log("⏳ Retrying after $delay ms...", self::COLOR_YELLOW);
                    usleep($delay * 1000);
                }
                $attempt++;

            } catch (\Exception $e) {
                $this->log("💥 Unexpected error: " . $e->getMessage(), self::COLOR_RED);
                $this->log("📍 Exception type: " . get_class($e), self::COLOR_RED);
                return null;
            }
        }

        $this->log("🔴 FAILED to fetch $url after $maxRetries attempts", self::COLOR_RED);
        return null;
    }

    public function scrapeMethodOneForUrl(string $baseUrl): array
    {
        if ($this->config['method_settings']['method_1']['pagination']['use_webdriver']) {
            return $this->scrapeWithPlaywright(1);
        }

        $links = [];
        $currentPage = 1;
        $hasMorePages = true;
        $pagesProcessed = 0;
        $consecutiveEmptyPages = 0;

        while ($hasMorePages && $currentPage <= $this->config['method_settings']['method_1']['pagination']['max_pages']) {
            $pageUrl = $this->buildPaginationUrl($baseUrl, $currentPage, $this->config['method_settings']['method_1']['pagination']);
            $this->log("Fetching page: $pageUrl", self::COLOR_GREEN);
            $body = $this->fetchPageContent($pageUrl, false);

            if ($body === null) {
                $consecutiveEmptyPages++;
                $this->log("Failed to fetch page $currentPage for $baseUrl or page was redirected. Treating as empty page. Consecutive empty pages: $consecutiveEmptyPages", self::COLOR_YELLOW);
                $pagesProcessed++;

                if ($consecutiveEmptyPages >= 3) {
                    $this->log("Stopping pagination: 3 consecutive empty pages (including failed fetches) for $baseUrl.", self::COLOR_YELLOW);
                    $hasMorePages = false;
                    break;
                }

                $currentPage++;
                continue;
            }

            $crawler = new Crawler($body);
            $linkSelector = $this->config['selectors']['main_page']['product_links']['selector'];
            $imageSelector = $this->config['selectors']['main_page']['image']['selector'] ?? '';
            $productIdSelector = $this->config['selectors']['main_page']['product_id']['selector'] ?? '';
            $productIdAttribute = $this->config['selectors']['main_page']['product_id']['attribute'] ?? 'data-product_id';
            $productIdFromLink = $this->config['selectors']['main_page']['product_links']['product_id'] ?? false;
            $productIdSource = $this->config['product_id_source'] ?? 'main_page';
            $linksFound = $crawler->filter($linkSelector)->count();
            $this->log("page$currentPage -> $linksFound link find", self::COLOR_GREEN);

            if ($linksFound === 0) {
                $consecutiveEmptyPages++;
                $this->log("No products found on page $currentPage for $baseUrl. Consecutive empty pages: $consecutiveEmptyPages", self::COLOR_YELLOW);
                $htmlSnippet = substr($body, 0, 500);
                $this->log("HTML snippet of page $currentPage: $htmlSnippet", self::COLOR_YELLOW);

                if ($consecutiveEmptyPages >= 3) {
                    $this->log("Stopping pagination: 3 consecutive pages with no products found for $baseUrl.", self::COLOR_YELLOW);
                    $hasMorePages = false;
                    break;
                }

                $currentPage++;
                $pagesProcessed++;
                continue;
            }

            $consecutiveEmptyPages = 0;

            $crawler->filter($linkSelector)->each(function (Crawler $node, $index) use (&$links, $crawler, $imageSelector, $productIdSelector, $productIdAttribute, $productIdFromLink, $productIdSource) {
                $href = $node->attr($this->config['selectors']['main_page']['product_links']['attribute']);
                if ($this->isInvalidLink($href)) {
                    $this->log("Invalid link skipped: $href", self::COLOR_YELLOW);
                    return;
                }

                $fullUrl = $this->makeAbsoluteUrl($href);
                if ($this->isUnwantedDomain($fullUrl)) {
                    $this->log("Unwanted domain skipped: $fullUrl", self::COLOR_YELLOW);
                    return;
                }

                $linkData = ['url' => $fullUrl, 'image' => '', 'product_id' => ''];

                try {
                    $parentNode = $node->ancestors()->first();
                    if (!$parentNode->count()) {
                        $this->log("No parent node found for link: $fullUrl", self::COLOR_YELLOW);
                    } else {
                        $this->log("Parent node found for link: $fullUrl", self::COLOR_GREEN);
                    }

                    if ($imageSelector) {
                        $this->log("Trying image selector: $imageSelector", self::COLOR_YELLOW);
                        try {
                            $parentNodeHtml = $parentNode->count() ? $parentNode->html() : 'No parent node';
                            $this->log("Parent node HTML: " . substr($parentNodeHtml, 0, 500), self::COLOR_YELLOW);
                            $imageElement = $parentNode->filter($imageSelector);
                            $this->log("Image elements found: {$imageElement->count()}", self::COLOR_YELLOW);
                            if ($imageElement->count() > 0) {
                                $image = $imageElement->attr($this->config['selectors']['main_page']['image']['attribute'] ?? 'src');
                                $this->log("Raw image URL: $image", self::COLOR_YELLOW);
                                $linkData['image'] = $this->makeAbsoluteUrl($image);
                                $this->log("Extracted image from main page: {$linkData['image']} for $fullUrl", self::COLOR_GREEN);
                            } else {
                                $this->log("No image found with selector '$imageSelector' for $fullUrl", self::COLOR_YELLOW);
                            }
                        } catch (\Exception $e) {
                            $this->log("Error extracting image for $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                        }
                    }

                    // Product ID extraction logic...
                    if ($productIdSource === 'product_links' && $productIdFromLink) {
                        try {
                            $productId = $node->attr($productIdFromLink);
                            $this->log("Raw product_id extracted from product_links: '$productId' for $fullUrl", self::COLOR_YELLOW);
                            if ($productId) {
                                $linkData['product_id'] = $productId;
                                $this->log("Extracted product_id from product_links: {$linkData['product_id']} for $fullUrl", self::COLOR_GREEN);
                            } else {
                                $this->log("No product_id found in product_links attribute '$productIdFromLink' for $fullUrl", self::COLOR_YELLOW);
                            }
                        } catch (\Exception $e) {
                            $this->log("Error extracting product_id from product_links for $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                        }
                    } elseif ($productIdSource === 'main_page') {
                        if ($productIdFromLink) {
                            try {
                                $productId = $node->attr($productIdFromLink);
                                if ($productId) {
                                    $linkData['product_id'] = $productId;
                                    $this->log("Extracted product_id from link attribute '$productIdFromLink': {$linkData['product_id']} for $fullUrl", self::COLOR_GREEN);
                                } else {
                                    $this->log("No product_id found with link attribute '$productIdFromLink' for $fullUrl", self::COLOR_YELLOW);
                                }
                            } catch (\Exception $e) {
                                $this->log("Error extracting product_id from link for $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                            }
                        }

                        if (!$linkData['product_id'] && $productIdSelector) {
                            try {
                                $productIdElements = $crawler->filter($productIdSelector);
                                if ($productIdElements->count() > 0) {
                                    $productId = $productIdElements->attr($productIdAttribute);
                                    if ($productId) {
                                        $linkData['product_id'] = $productId;
                                        $this->log("Extracted product_id from selector '$productIdSelector': {$linkData['product_id']} for $fullUrl", self::COLOR_GREEN);
                                    } else {
                                        $this->log("No product_id found with selector '$productIdSelector' for $fullUrl", self::COLOR_YELLOW);
                                    }
                                } else {
                                    $this->log("No elements found with product_id selector '$productIdSelector' for $fullUrl", self::COLOR_YELLOW);
                                    $ancestorWithId = $node->ancestors()->filter($productIdSelector)->first();
                                    if ($ancestorWithId->count() > 0) {
                                        $productId = $ancestorWithId->attr($productIdAttribute);
                                        $linkData['product_id'] = $productId;
                                        $this->log("Extracted product_id from ancestor: {$linkData['product_id']} for $fullUrl", self::COLOR_GREEN);
                                    } else {
                                        $this->log("No product_id found in ancestors with selector '$productIdSelector' for $fullUrl", self::COLOR_YELLOW);
                                    }
                                }
                            } catch (\Exception $e) {
                                $this->log("Error extracting product_id for $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                            }
                        }
                    }

                    $links[] = $linkData;
                    $this->log("Added link: $fullUrl", self::COLOR_GREEN);
                } catch (\Exception $e) {
                    $this->log("Error processing node for $fullUrl: {$e->getMessage()}", self::COLOR_RED);
                }
            });

            $pagesProcessed++;
            $currentPage++;
        }

        return [
            'links' => array_unique($links, SORT_REGULAR),
            'pages_processed' => $pagesProcessed
        ];
    }

    // Methods for Playwright scraping and other scraping methods...
    public function scrapeWithPlaywright(int $method, string $productUrl = ''): array
    {
        // Placeholder for Playwright scraping logic
        // This would contain the actual Playwright implementation
        $this->log("Playwright scraping not implemented in this refactored version", self::COLOR_YELLOW);
        return ['links' => [], 'pages_processed' => 0];
    }

    public function scrapeMethodThree(): array
    {
        // Placeholder for method 3 scraping logic
        $this->log("Method 3 scraping not implemented in this refactored version", self::COLOR_YELLOW);
        return ['links' => [], 'pages_processed' => 0];
    }

    // Helper methods
    private function randomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
            // ... more user agents
        ];
        return $agents[array_rand($agents)];
    }

    private function exponentialBackoff(int $attempt): int
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
