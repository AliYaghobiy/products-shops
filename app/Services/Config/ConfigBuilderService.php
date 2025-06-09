<?php

namespace App\Services\Config;

use Illuminate\Http\Request;

/**
 * سرویس ساخت کانفیگ‌ها
 */
class ConfigBuilderService
{
    /**
     * ساخت کانفیگ کامل از درخواست
     */
    public function buildConfig(Request $request): array
    {
        $method = (int)$request->input('method', 1);

        $config = $this->buildBaseConfig($request, $method);
        $config['selectors'] = $this->buildSelectors($request);

        // اضافه کردن set_category در صورت نیاز
        if (filter_var($request->input('use_set_category', false), FILTER_VALIDATE_BOOLEAN)) {
            $setCategory = trim($request->input('set_category', ''));
            if (!empty($setCategory)) {
                $config['set_category'] = $setCategory;
            }
        }

        // اضافه کردن product_id برای main_page
        if ($request->input('product_id_source') == 'main_page') {
            $config['selectors']['main_page']['product_id'] = [
                'type' => $request->input('selectors.main_page.product_id.type'),
                'selector' => $request->input('selectors.main_page.product_id.selector'),
                'attribute' => $request->input('selectors.main_page.product_id.attribute'),
            ];
        }

        // اضافه کردن title prefix rules
        $config = $this->addTitlePrefixRules($config, $request);

        // اضافه کردن تنظیمات متد
        $config = $this->addMethodSettings($config, $request, $method);

        return $config;
    }

    /**
     * ساخت تنظیمات پایه کانفیگ
     */
    private function buildBaseConfig(Request $request, int $method): array
    {
        return [
            'method' => $method,
            'processing_method' => $method == 3 ? 3 : 1,
            'base_urls' => $request->input('base_urls'),
            'products_urls' => $request->input('products_urls'),
            'request_delay_min' => (int)$request->input('request_delay_min', 1000),
            'request_delay_max' => (int)$request->input('request_delay_max', 1000),
            'timeout' => (int)$request->input('timeout', 60),
            'max_retries' => (int)$request->input('max_retries', 2),
            'concurrency' => (int)$request->input('concurrency', 10),
            'batch_size' => (int)$request->input('batch_size', 10),
            'user_agent' => $request->input('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124'),
            'verify_ssl' => filter_var($request->input('verify_ssl', false), FILTER_VALIDATE_BOOLEAN),
            'keep_price_format' => filter_var($request->input('keep_price_format', false), FILTER_VALIDATE_BOOLEAN),
            'image_method' => $request->input('image_method', 'product_page'),
            'product_id_method' => $request->input('product_id_method'),
            'product_id_source' => $request->input('product_id_source'),
            'availability_mode' => $request->input('availability_mode', 'priority_based'),
            'out_of_stock_button' => filter_var($request->input('out_of_stock_button', false), FILTER_VALIDATE_BOOLEAN),
            'run_method' => $request->input('run_method', 'new'),
            'database' => $request->input('database', 'clear'),
            'product_id_fallback_script_patterns' => [
                'product_id:\\s*\"(\\d+)\"',
                'product_id:\\s*(\\d+)'
            ],
            'category_method' => $request->input('category_method', 'selector'),
            'category_word_count' => $this->processCategoryWordCount($request),
            'guarantee_method' => $request->input('guarantee_method'),
            'guarantee_keywords' => $request->input('guarantee_keywords'),
            'availability_keywords' => [
                'positive' => $request->input('availability_keywords.positive'),
                'negative' => $request->input('availability_keywords.negative'),
            ],
            'price_keywords' => [
                'unpriced' => $request->input('price_keywords.unpriced'),
            ],
        ];
    }

    /**
     * ساخت سلکتورها
     */
    private function buildSelectors(Request $request): array
    {
        return [
            'main_page' => [
                'product_links' => [
                    'type' => $request->input('selectors.main_page.product_links.type', 'css'), // تغییر: پشتیبانی از xml
                    'selector' => $request->input('selectors.main_page.product_links.selector'),
                    'attribute' => $request->input('selectors.main_page.product_links.attribute'),
                ],
            ],
            'product_page' => [
                'title' => [
                    'type' => $request->input('selectors.product_page.title.type'),
                    'selector' => $request->input('selectors.product_page.title.selector'),
                ],
                'category' => [
                    'type' => $request->input('selectors.product_page.category.type'),
                    'selector' => $this->processCategorySelectors($request),
                    'attribute' => $request->input('selectors.product_page.category.attribute'),
                ],
                'availability' => [
                    'type' => $request->input('selectors.product_page.availability.type'),
                    'selector' => $request->input('selectors.product_page.availability.selector'),
                ],
                'out_of_stock' => [
                    'type' => $request->input('out_of_stock_button') ? $request->input('selectors.product_page.out_of_stock.type') : null,
                    'selector' => $request->input('out_of_stock_button') ? $request->input('selectors.product_page.out_of_stock.selector', []) : [],
                ],
                'price' => [
                    'type' => $request->input('selectors.product_page.price.type'),
                    'selector' => $request->input('selectors.product_page.price.selector'),
                ],
                'image' => [
                    'type' => $request->input('selectors.product_page.image.type'),
                    'selector' => $request->input('selectors.product_page.image.selector'),
                    'attribute' => $request->input('selectors.product_page.image.attribute'),
                ],
                'off' => [
                    'type' => $request->input('selectors.product_page.off.type'),
                    'selector' => $request->input('selectors.product_page.off.selector'),
                ],
                'guarantee' => [
                    'type' => $request->input('selectors.product_page.guarantee.type'),
                    'selector' => $request->input('selectors.product_page.guarantee.selector'),
                ],
                'product_id' => [
                    'type' => $request->input('selectors.product_page.product_id.type'),
                    'selector' => $this->processProductIdSelectors($request),
                    'attribute' => $this->processProductIdAttributes($request),
                ],
            ],
            'data_transformers' => [
                'price' => 'cleanPrice',
                'availability' => 'parseAvailability',
                'off' => 'cleanOff',
                'guarantee' => 'cleanGuarantee',
            ],
        ];
    }

    /**
     * پردازش سلکتورهای دسته‌بندی
     */
    private function processCategorySelectors(Request $request)
    {
        $selectorArray = $request->input('selectors.product_page.category.selector');
        if (is_array($selectorArray) && !empty($selectorArray)) {
            $validSelectors = array_filter($selectorArray, function ($selector) {
                return !empty(trim($selector));
            });

            if (!empty($validSelectors)) {
                return array_values($validSelectors);
            }
        }

        $singleSelector = $request->input('selectors.product_page.category.selector_single');
        if (!empty(trim($singleSelector))) {
            return $singleSelector;
        }

        return '';
    }

    /**
     * پردازش تعداد کلمات دسته‌بندی
     */
    private function processCategoryWordCount(Request $request)
    {
        $wordCountArray = $request->input('category_word_count');
        if (is_array($wordCountArray) && !empty($wordCountArray)) {
            $validCounts = array_filter($wordCountArray, function ($count) {
                return is_numeric($count) && $count > 0;
            });

            if (!empty($validCounts)) {
                return array_map('intval', array_values($validCounts));
            }
        }

        $singleWordCount = $request->input('category_word_count_single');
        if (is_numeric($singleWordCount) && $singleWordCount > 0) {
            return (int)$singleWordCount;
        }

        return 1;
    }

    /**
     * پردازش سلکتورهای product_id
     */
    private function processProductIdSelectors(Request $request)
    {
        if ($request->has('selectors.product_page.product_id.selector') &&
            is_array($request->input('selectors.product_page.product_id.selector'))) {

            $selectors = array_filter($request->input('selectors.product_page.product_id.selector'));
            return !empty($selectors) ? array_values($selectors) : null;
        }

        if ($request->has('selectors.product_page.product_id.selector_single')) {
            $singleSelector = trim($request->input('selectors.product_page.product_id.selector_single'));
            return !empty($singleSelector) ? $singleSelector : null;
        }

        return null;
    }

    /**
     * پردازش attribute های product_id
     */
    private function processProductIdAttributes(Request $request)
    {
        if ($request->has('selectors.product_page.product_id.attribute') &&
            is_array($request->input('selectors.product_page.product_id.attribute'))) {

            $attributes = array_filter($request->input('selectors.product_page.product_id.attribute'));
            return !empty($attributes) ? array_values($attributes) : null;
        }

        if ($request->has('selectors.product_page.product_id.attribute_single')) {
            $singleAttribute = trim($request->input('selectors.product_page.product_id.attribute_single'));
            return !empty($singleAttribute) ? $singleAttribute : null;
        }

        return null;
    }

    /**
     * اضافه کردن قوانین prefix عنوان
     */
    private function addTitlePrefixRules(array $config, Request $request): array
    {
        $titlePrefixRules = [];
        $urls = $request->input('title_prefix_rules.url', []);
        $prefixes = $request->input('title_prefix_rules.prefix', []);

        foreach ($urls as $index => $url) {
            if (!empty($url) && !empty($prefixes[$index])) {
                $titlePrefixRules[$url] = [
                    'prefix' => $prefixes[$index],
                ];
            }
        }

        if (!empty($titlePrefixRules)) {
            $config['title_prefix_rules'] = $titlePrefixRules;
        }

        return $config;
    }

    /**
     * اضافه کردن تنظیمات متد
     */
    private function addMethodSettings(array $config, Request $request, int $method): array
    {
        if ($method == 1) {
            $config['method_settings'] = $this->buildMethod1Settings($request);
        } elseif ($method == 2) {
            $config = $this->addMethod2Settings($config, $request);
        } elseif ($method == 3) {
            $config = $this->addMethod3Settings($config, $request);
        }

        return $config;
    }

    /**
     * ساخت تنظیمات متد ۱
     */
    private function buildMethod1Settings(Request $request): array
    {
        return [
            'method_1' => [
                'enabled' => true,
                'pagination' => [
                    'type' => $request->input('pagination.type'),
                    'parameter' => $request->input('pagination.parameter'),
                    'separator' => $request->input('pagination.separator'),
                    'suffix' => $request->input('pagination.suffix', ''),
                    'max_pages' => (int)$request->input('pagination.max_pages'),
                    'use_sample_url' => filter_var($request->input('pagination.use_sample_url', false), FILTER_VALIDATE_BOOLEAN),
                    'sample_url' => $request->input('pagination.use_sample_url') ? $request->input('pagination.sample_url', '') : '',
                    'use_webdriver' => false,
                    'use_dynamic_pagination' => false,
                    'force_trailing_slash' => true,
                    'ignore_redirects' => true,
                ],
            ],
        ];
    }

    /**
     * اضافه کردن تنظیمات متد ۲
     */
    private function addMethod2Settings(array $config, Request $request): array
    {
        $config['share_product_id_from_method_2'] = filter_var($request->input('share_product_id_from_method_2', false), FILTER_VALIDATE_BOOLEAN);
        $config['container'] = $request->input('container', '');
        $config['scrool'] = (int)$request->input('scrool', 10);

        $pagination = $this->buildPaginationConfig($request);
        $config['method_settings'] = [
            'method_2' => [
                'enabled' => true,
                'navigation' => [
                    'use_webdriver' => true,
                    'pagination' => $pagination,
                    'max_pages' => (int)$request->input('pagination_max_pages', 3),
                    'scroll_delay' => (int)$request->input('scroll_delay', 5000)
                ],
            ],
        ];

        return $config;
    }

    /**
     * اضافه کردن تنظیمات متد ۳
     */
    private function addMethod3Settings(array $config, Request $request): array
    {
        $container = $request->input('container');
        if (!empty($container)) {
            $config['container'] = $container;
        }

        $config['scrool'] = (int)$request->input('scrool', 10);
        $pagination = $this->buildPaginationConfig($request);

        $config['method_settings'] = [
            'method_3' => [
                'enabled' => true,
                'navigation' => [
                    'use_webdriver' => true,
                    'pagination' => $pagination,
                    'max_iterations' => (int)$request->input('pagination_max_pages', 13),
                    'timing' => [
                        'scroll_delay' => (int)$request->input('scroll_delay', 5000)
                    ]
                ],
            ],
        ];

        return $config;
    }

    /**
     * ساخت تنظیمات pagination
     */
    private function buildPaginationConfig(Request $request): array
    {
        $pagination = [
            'method' => $request->input('pagination_method', 'next_button'),
        ];

        if ($request->input('pagination_method') == 'next_button') {
            $pagination['next_button'] = [
                'selector' => $request->input('pagination_next_button_selector', '')
            ];
        } else {
            $pagination['url'] = [
                'type' => $request->input('pagination_url_type', 'query'),
                'parameter' => $request->input('pagination_url_parameter', 'page'),
                'separator' => $request->input('pagination_url_separator', '='),
                'suffix' => $request->input('pagination_url_suffix', ''),
                'max_pages' => (int)$request->input('pagination_max_pages', 3),
                'use_sample_url' => filter_var($request->input('pagination_use_sample_url', false), FILTER_VALIDATE_BOOLEAN),
                'sample_url' => $request->input('pagination_use_sample_url') ? $request->input('pagination_sample_url', '') : '',
                'use_webdriver' => true
            ];
        }

        return $pagination;
    }

    /**
     * آماده‌سازی کانفیگ برای ویرایش
     */
    public function prepareForEdit(array $content): array
    {
        // بررسی وجود set_category برای فعال بودن چک‌باکس
        if (isset($content['set_category'])) {
            $content['use_set_category'] = true;
        }

        // بررسی وجود out_of_stock_button برای فعال بودن چک‌باکس
        $content['use_out_of_stock_button'] = $content['out_of_stock_button'] ?? false;

        // اطمینان از وجود availability_mode در محتوا
        if (!isset($content['availability_mode'])) {
            $content['availability_mode'] = 'priority_based';
        }

        // اصلاح مشکل سلکتورهای مختلف
        $this->fixCategorySelectors($content);
        $this->fixAvailabilitySelectors($content);
        $this->fixOutOfStockSelectors($content);
        $this->fixPriceSelectors($content);
        $this->fixProductIdSelectors($content);
        $this->fixOtherSelectors($content);

        return $content;
    }

    /**
     * اصلاح سلکتورهای دسته‌بندی برای نمایش در فرم ویرایش
     */
    private function fixCategorySelectors(array &$content): void
    {
        if (isset($content['category_word_count'])) {
            $wordCount = $content['category_word_count'];

            if (is_array($wordCount)) {
                $content['category_word_count'] = $wordCount;
            } else {
                $content['category_word_count_single'] = $wordCount;
                $content['category_word_count'] = [];
            }
        }

        if (isset($content['selectors']['product_page']['category']['selector'])) {
            $categorySelector = $content['selectors']['product_page']['category']['selector'];

            if (is_array($categorySelector)) {
                $content['selectors']['product_page']['category']['selector'] = $categorySelector;
            } else {
                $content['selectors']['product_page']['category']['selector_single'] = $categorySelector;
                $content['selectors']['product_page']['category']['selector'] = [];
            }
        }
    }

    /**
     * اصلاح سلکتورهای موجودی برای نمایش در فرم ویرایش
     */
    private function fixAvailabilitySelectors(array &$content): void
    {
        if (isset($content['selectors']['product_page']['availability']['selector'])) {
            $availabilitySelector = $content['selectors']['product_page']['availability']['selector'];

            if (is_string($availabilitySelector)) {
                $content['selectors']['product_page']['availability']['selector'] = [$availabilitySelector];
            }
        }
    }

    /**
     * اصلاح سلکتورهای قیمت
     */
    private function fixPriceSelectors(array &$content): void
    {
        if (isset($content['selectors']['product_page']['price']['selector'])) {
            $priceSelector = $content['selectors']['product_page']['price']['selector'];

            if (is_array($priceSelector)) {
                $content['selectors']['product_page']['price']['selector'] = $priceSelector;
            } else {
                $content['selectors']['product_page']['price']['selector_single'] = $priceSelector;
                $content['selectors']['product_page']['price']['selector'] = [];
            }
        }
    }

    /**
     * اصلاح سلکتورهای ناموجودی برای نمایش در فرم ویرایش
     */
    private function fixOutOfStockSelectors(array &$content): void
    {
        if (isset($content['selectors']['product_page']['out_of_stock']['selector'])) {
            $outOfStockSelector = $content['selectors']['product_page']['out_of_stock']['selector'];

            if (!is_array($outOfStockSelector)) {
                if (!empty($outOfStockSelector)) {
                    $content['selectors']['product_page']['out_of_stock']['selector'] = [$outOfStockSelector];
                } else {
                    $content['selectors']['product_page']['out_of_stock']['selector'] = [];
                }
            }
        }
    }

    /**
     * اصلاح مشکل سلکتورهای product_id در edit
     */
    private function fixProductIdSelectors(array &$content): void
    {
        if (isset($content['selectors']['product_page']['product_id'])) {
            $productIdConfig = &$content['selectors']['product_page']['product_id'];

            if (isset($productIdConfig['selector']) && is_array($productIdConfig['selector'])) {
                if (count($productIdConfig['selector']) === 1) {
                    $content['product_id_selector_single'] = $productIdConfig['selector'][0];
                    unset($content['product_id_selector_multiple']);
                } else {
                    $content['product_id_selector_multiple'] = $productIdConfig['selector'];
                    unset($content['product_id_selector_single']);
                }
            } else {
                $content['product_id_selector_single'] = $productIdConfig['selector'] ?? '';
                unset($content['product_id_selector_multiple']);
            }

            if (isset($productIdConfig['attribute']) && is_array($productIdConfig['attribute'])) {
                if (count($productIdConfig['attribute']) === 1) {
                    $content['product_id_attribute_single'] = $productIdConfig['attribute'][0];
                    unset($content['product_id_attribute_multiple']);
                } else {
                    $content['product_id_attribute_multiple'] = $productIdConfig['attribute'];
                    unset($content['product_id_attribute_single']);
                }
            } else {
                $content['product_id_attribute_single'] = $productIdConfig['attribute'] ?? '';
                unset($content['product_id_attribute_multiple']);
            }
        }
    }

    /**
     * اصلاح سایر سلکتورها که ممکن است مشکل داشته باشند
     */
    private function fixOtherSelectors(array &$content): void
    {
        // اصلاح سلکتورهای گارانتی (اگر آرایه باشند)
        if (isset($content['selectors']['product_page']['guarantee']['selector'])) {
            $guaranteeSelector = $content['selectors']['product_page']['guarantee']['selector'];
            if (is_array($guaranteeSelector)) {
                $content['selectors']['product_page']['guarantee']['selector'] = implode(', ', $guaranteeSelector);
            }
        }

        // اصلاح سلکتورهای قیمت (اگر آرایه باشند)
        if (isset($content['selectors']['product_page']['price']['selector'])) {
            $priceSelector = $content['selectors']['product_page']['price']['selector'];
            if (is_array($priceSelector)) {
                $content['selectors']['product_page']['price']['selector'] = implode(', ', $priceSelector);
            }
        }

        // اصلاح کلمات کلیدی گارانتی
        if (isset($content['guarantee_keywords']) && !is_array($content['guarantee_keywords'])) {
            $content['guarantee_keywords'] = [$content['guarantee_keywords']];
        }

        // اصلاح کلمات کلیدی موجودی
        if (isset($content['availability_keywords'])) {
            if (!isset($content['availability_keywords']['positive']) || !is_array($content['availability_keywords']['positive'])) {
                $content['availability_keywords']['positive'] = [];
            }
            if (!isset($content['availability_keywords']['negative']) || !is_array($content['availability_keywords']['negative'])) {
                $content['availability_keywords']['negative'] = [];
            }
        }

        // اصلاح کلمات کلیدی قیمت
        if (isset($content['price_keywords'])) {
            if (!isset($content['price_keywords']['unpriced']) || !is_array($content['price_keywords']['unpriced'])) {
                $content['price_keywords']['unpriced'] = [];
            }
        }
    }

    /**
     * ساخت کانفیگ برای تست محصول واحد
     */
    public function buildSingleProductConfig(Request $request): array
    {
        return [
            'product_test' => true,
            'product_urls' => [$request->input('product_url')],
            'request_delay_min' => 1000,
            'request_delay_max' => 1000,
            'timeout' => 60,
            'max_retries' => 2,
            'concurrency' => 1,
            'batch_size' => 1,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124',
            'verify_ssl' => false,
            'keep_price_format' => false,
            'image_method' => 'product_page',
            'product_id_method' => 'selector',
            'product_id_source' => 'product_page',
            'availability_mode' => 'priority_based',
            'out_of_stock_button' => filter_var($request->input('out_of_stock_button', false), FILTER_VALIDATE_BOOLEAN),
            'product_id_fallback_script_patterns' => [
                'product_id:\\s*\"(\\d+)\"',
                'product_id:\\s*(\\d+)'
            ],
            'category_method' => 'selector',
            'category_word_count' => 1,
            'guarantee_method' => 'selector',
            'guarantee_keywords' => $this->processKeywords($request->input('guarantee_keywords', ['گارانتی'])),
            'availability_keywords' => [
                'positive' => $this->processKeywords($request->input('availability_keywords_positive', ['موجود', 'افزودن به سبد خرید'])),
                'negative' => $this->processKeywords($request->input('availability_keywords_negative', ['ناموجود', 'در حال حاضر این محصول در انبار موجود نیست و در دسترس نمی باشد.']))
            ],
            'price_keywords' => [
                'unpriced' => $this->processKeywords($request->input('price_keywords_unpriced', ['تماس بگیرید']))
            ],
            'selectors' => [
                'product_page' => [
                    'title' => [
                        'type' => 'css',
                        'selector' => $request->input('title_selector'),
                    ],
                    'price' => [
                        'type' => 'css',
                        'selector' => $this->processMultipleSelectors($request->input('price_selector', [])),
                    ],
                    'category' => [
                        'type' => 'css',
                        'selector' => $this->processMultipleSelectors($request->input('category_selector', [])),
                        'attribute' => null,
                    ],
                    'availability' => [
                        'type' => 'css',
                        'selector' => $this->processMultipleSelectors($request->input('availability_selector', [])),
                    ],
                    'out_of_stock' => [
                        'type' => $request->input('out_of_stock_button') ? 'css' : null,
                        'selector' => $request->input('out_of_stock_button') ? $this->processMultipleSelectors($request->input('out_of_stock_selector', [])) : [],
                    ],
                    'image' => [
                        'type' => 'css',
                        'selector' => $request->input('image_selector') ?: null,
                        'attribute' => $request->input('image_attribute', 'href'),
                    ],
                    'off' => [
                        'type' => 'css',
                        'selector' => $request->input('off_selector') ?: null,
                    ],
                    'guarantee' => [
                        'type' => 'css',
                        'selector' => $request->input('guarantee_selector') ?: null,
                    ],
                    'product_id' => [
                        'type' => 'css',
                        'selector' => $this->processMultipleSelectors($request->input('product_id_selector', [])),
                        'attribute' => $this->processMultipleSelectors($request->input('product_id_attribute', ['value'])),
                    ],
                ],
                'data_transformers' => [
                    'price' => 'cleanPrice',
                    'availability' => 'parseAvailability',
                    'off' => 'cleanOff',
                    'guarantee' => 'cleanGuarantee',
                ],
            ],
        ];
    }

    /**
     * پردازش سلکتورهای چندگانه برای تست محصول
     */
    private function processMultipleSelectors($input): array
    {
        if (is_array($input)) {
            // فیلتر کردن مقادیر خالی
            $filtered = array_filter($input, function ($value) {
                return !empty(trim($value));
            });
            return array_values($filtered);
        }

        if (is_string($input) && !empty(trim($input))) {
            return [$input];
        }

        return [];
    }

    /**
     * پردازش کلمات کلیدی برای تست محصول
     */
    private function processKeywords($input): array
    {
        if (is_array($input)) {
            return array_filter($input, function ($value) {
                return !empty(trim($value));
            });
        }

        if (is_string($input) && !empty(trim($input))) {
            return [$input];
        }

        return [];
    }
}
