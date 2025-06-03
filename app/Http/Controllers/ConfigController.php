<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ConfigController extends Controller
{
    protected $configPath;

    public function __construct()
    {
        $this->configPath = storage_path('app/private/');

        // ایجاد دایرکتوری‌های مورد نیاز
        $directories = [
            storage_path('app/private/'),
            storage_path('app/private/runs/'),
            storage_path('logs/scrapers/'),
        ];

        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }


    public function index()
    {
        $files = Storage::files('private');
        $configs = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $configData = [
                    'filename' => $filename,
                    'content' => json_decode(Storage::get($file), true),
                    'status' => 'stopped',
                    'type' => 'normal',
                    'started_at' => null,
                    'log_file' => null
                ];

                // بررسی وضعیت اجرا
                $runFilePath = 'private/runs/' . $filename . '.json';
                if (Storage::exists($runFilePath)) {
                    $runInfo = json_decode(Storage::get($runFilePath), true);

                    if (isset($runInfo['status'])) {
                        $configData['status'] = $runInfo['status'];
                    }

                    if (isset($runInfo['type'])) {
                        $configData['type'] = $runInfo['type'];
                    }

                    if (isset($runInfo['started_at'])) {
                        $configData['started_at'] = $runInfo['started_at'];
                    }

                    if (isset($runInfo['log_file'])) {
                        $configData['log_file'] = $runInfo['log_file'];
                    }

                    // بررسی واقعی بودن وضعیت running
                    if ($configData['status'] === 'running' && isset($runInfo['pid'])) {
                        if (!$this->isProcessRunning($runInfo['pid'])) {
                            // اگر PID وجود ندارد، بررسی کن که آیا پروسه با نام فایل وجود دارد
                            $processCommand = $configData['type'] === 'update'
                                ? "ps aux | grep 'scrape:start.*{$filename}.*--update' | grep -v grep"
                                : "ps aux | grep 'scrape:start.*{$filename}' | grep -v grep | grep -v -- '--update'";

                            exec($processCommand, $output);

                            if (empty($output)) {
                                $configData['status'] = 'crashed';

                                // به‌روزرسانی فایل run
                                $runInfo['status'] = 'crashed';
                                $runInfo['stopped_at'] = date('Y-m-d H:i:s');
                                Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            }
                        }
                    }
                }

                $configs[] = $configData;
            }
        }

        return view('configs.index', compact('configs'));
    }

    public function deleteAllLogs()
    {
        $logDirectory = storage_path('logs/');
        $logPatterns = ['scraper*', 'playwright_method3_*', 'playwright_*'];

        $deletedCount = 0;
        $errorCount = 0;

        if (file_exists($logDirectory)) {
            foreach ($logPatterns as $pattern) {
                $files = glob($logDirectory . '/' . $pattern . '.log');
                foreach ($files as $file) {
                    if (is_file($file) && unlink($file)) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
        }

        if ($deletedCount > 0 && $errorCount == 0) {
            return redirect()->route('configs.index')->with('success', "تعداد $deletedCount فایل لاگ با موفقیت حذف شدند.");
        } elseif ($deletedCount > 0 && $errorCount > 0) {
            return redirect()->route('configs.index')->with('warning', "تعداد $deletedCount فایل لاگ حذف شدند، اما $errorCount فایل حذف نشدند.");
        } elseif ($errorCount > 0) {
            return redirect()->route('configs.index')->with('error', 'خطا در حذف فایل‌های لاگ.');
        } else {
            return redirect()->route('configs.index')->with('info', 'هیچ فایل لاگ مرتبطی یافت نشد.');
        }
    }


    public function deleteLog($logfile)
    {
        $logPath = storage_path('logs/scrapers/' . $logfile);

        if (!file_exists($logPath)) {
            return redirect()->back()->with('error', 'فایل لاگ یافت نشد.');
        }

        // حذف فایل
        if (unlink($logPath)) {
            return redirect()->back()->with('success', 'فایل لاگ با موفقیت حذف شد.');
        } else {
            return redirect()->back()->with('error', 'خطا در حذف فایل لاگ.');
        }
    }

    public function deleteConfigLogs(Request $request)
    {
        $configFilename = $request->input('config_filename');
        $logDirectory = storage_path('logs/scrapers');

        $deletedCount = 0;
        $errorCount = 0;

        if (file_exists($logDirectory)) {
            $allFiles = scandir($logDirectory);

            foreach ($allFiles as $file) {
                if (strpos($file, $configFilename . '_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $filePath = $logDirectory . '/' . $file;
                    if (is_file($filePath) && unlink($filePath)) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
        }

        if ($deletedCount > 0 && $errorCount == 0) {
            return redirect()->back()->with('success', "تعداد $deletedCount فایل لاگ با موفقیت حذف شدند.");
        } elseif ($deletedCount > 0 && $errorCount > 0) {
            return redirect()->back()->with('warning', "تعداد $deletedCount فایل لاگ حذف شدند، اما $errorCount فایل حذف نشدند.");
        } elseif ($errorCount > 0) {
            return redirect()->back()->with('error', 'خطا در حذف فایل‌های لاگ.');
        } else {
            return redirect()->back()->with('info', 'هیچ فایل لاگ مرتبطی یافت نشد.');
        }
    }

    public function create()
    {
        return view('configs.create');
    }

    public function store(Request $request)
    {
        $validator = $this->getValidator($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $method = (int)$request->input('method', 1);
        $config = $this->buildConfig($request, $method);


        $siteName = $request->input('site_name');
        $filename = $siteName . '.json';

        Storage::put('private/' . $filename, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->route('configs.index')->with('success', 'کانفیگ با موفقیت ذخیره شد!');
    }


    public function edit($filename)
    {
        $filePath = "private/{$filename}.json";
        if (!Storage::exists($filePath)) {
            return redirect()->route('configs.index')->with('error', 'فایل کانفیگ یافت نشد.');
        }

        $content = json_decode(Storage::get($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->route('configs.index')->with('error', 'خطا در خواندن فایل کانفیگ.');
        }

        // بررسی وجود set_category برای فعال بودن چک‌باکس
        if (isset($content['set_category'])) {
            $content['use_set_category'] = true;
        }

        // بررسی وجود out_of_stock_button برای فعال بودن چک‌باکس
        if (isset($content['out_of_stock_button'])) {
            $content['use_out_of_stock_button'] = $content['out_of_stock_button'];
        } else {
            $content['use_out_of_stock_button'] = false;
        }

        // اطمینان از وجود availability_mode در محتوا
        if (!isset($content['availability_mode'])) {
            $content['availability_mode'] = 'priority_based';
        }

        // اصلاح مشکل سلکتورهای مختلف
        $this->fixCategorySelectors($content);
        $this->fixAvailabilitySelectors($content);
        $this->fixOutOfStockSelectors($content);
        $this->fixPriceSelectors($content);

        // جدید: اصلاح مشکل سلکتورهای product_id
        $this->fixProductIdSelectors($content);

        $this->fixOtherSelectors($content);

        return view('configs.edit', compact('content', 'filename'));
    }

    /**
     * اصلاح سلکتورهای دسته‌بندی برای نمایش در فرم ویرایش
     */
    private function fixCategorySelectors(&$content)
    {
        // بررسی و اصلاح category_word_count
        if (isset($content['category_word_count'])) {
            $wordCount = $content['category_word_count'];

            if (is_array($wordCount)) {
                // اگر آرایه است، آن را نگه دارید
                $content['category_word_count'] = $wordCount;
            } else {
                // اگر عدد واحد است، آن را در category_word_count_single قرار دهید
                $content['category_word_count_single'] = $wordCount;
                $content['category_word_count'] = [];
            }
        }

        // بررسی و اصلاح category selectors
        if (isset($content['selectors']['product_page']['category']['selector'])) {
            $categorySelector = $content['selectors']['product_page']['category']['selector'];

            if (is_array($categorySelector)) {
                // اگر آرایه است، آن را نگه دارید
                $content['selectors']['product_page']['category']['selector'] = $categorySelector;
            } else {
                // اگر رشته واحد است، آن را در selector_single قرار دهید
                $content['selectors']['product_page']['category']['selector_single'] = $categorySelector;
                $content['selectors']['product_page']['category']['selector'] = [];
            }
        }
    }

    /**
     * اصلاح سلکتورهای موجودی برای نمایش در فرم ویرایش
     */
    private function fixAvailabilitySelectors(&$content)
    {
        if (isset($content['selectors']['product_page']['availability']['selector'])) {
            $availabilitySelector = $content['selectors']['product_page']['availability']['selector'];

            // اگر سلکتور رشته واحد است، آن را به آرایه تبدیل کنید
            if (is_string($availabilitySelector)) {
                $content['selectors']['product_page']['availability']['selector'] = [$availabilitySelector];
            }
        }
    }

    private function fixPriceSelectors(&$content)
    {
        if (isset($content['selectors']['product_page']['price']['selector'])) {
            $priceSelector = $content['selectors']['product_page']['price']['selector'];

            if (is_array($priceSelector)) {
                // اگر آرایه است، آن را نگه دارید
                $content['selectors']['product_page']['price']['selector'] = $priceSelector;
            } else {
                // اگر رشته واحد است، آن را در selector_single قرار دهید
                $content['selectors']['product_page']['price']['selector_single'] = $priceSelector;
                $content['selectors']['product_page']['price']['selector'] = [];
            }
        }
    }

    /**
     * اصلاح سلکتورهای ناموجودی برای نمایش در فرم ویرایش
     */
    private function fixOutOfStockSelectors(&$content)
    {
        if (isset($content['selectors']['product_page']['out_of_stock']['selector'])) {
            $outOfStockSelector = $content['selectors']['product_page']['out_of_stock']['selector'];

            // اطمینان از اینکه سلکتور ناموجودی آرایه باشد
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
     * اصلاح سایر سلکتورها که ممکن است مشکل داشته باشند
     */
    private function fixOtherSelectors(&$content)
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


    public function update(Request $request, $filename)
    {
        $validator = $this->getValidator($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $method = (int)$request->input('method', 1);
        $config = $this->buildConfig($request, $method);

        // به‌روزرسانی فایل کانفیگ
        Storage::put('private/' . $filename . '.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->route('configs.index')->with('success', 'کانفیگ با موفقیت به‌روزرسانی شد!');
    }

    public function destroy($filename)
    {
        $filePath = "private/{$filename}.json";
        if (!Storage::exists($filePath)) {
            return redirect()->route('configs.index')->with('error', 'فایل کانفیگ یافت نشد.');
        }

        Storage::delete($filePath);
        return redirect()->route('configs.index')->with('success', 'کانفیگ با موفقیت حذف شد!');
    }

    private function getValidator(Request $request)
    {
        $rules = [
            'site_name' => 'required|string|max:255',
            'method' => 'required|in:1,2,3',
            'base_urls' => 'required|array|min:1',
            'base_urls.*' => 'required|url',
            'products_urls' => 'required|array|min:1',
            'products_urls.*' => 'required|url',
            'keep_price_format' => 'boolean',
            'product_id_method' => 'required|in:selector,url',
            'product_id_source' => 'required|in:product_page,url,main_page',
            'guarantee_method' => 'required|in:selector,title',
            'guarantee_keywords' => 'required|array|min:1',
            'guarantee_keywords.*' => 'required|string',
            'availability_mode' => 'required|in:priority_based,keyword_based',
            'availability_keywords.positive' => 'required|array|min:1',
            'availability_keywords.positive.*' => 'required|string',
            'availability_keywords.negative' => 'required|array|min:1',
            'availability_keywords.negative.*' => 'required|string',
            'price_keywords.unpriced' => 'required|array|min:1',
            'price_keywords.unpriced.*' => 'required|string',
            'selectors.main_page.product_links.type' => 'required|string',
            'selectors.main_page.product_links.selector' => 'required|string',
            'selectors.main_page.product_links.attribute' => 'required|string',
            'out_of_stock_button' => 'boolean',
            'selectors.product_page.out_of_stock.type' => 'required_if:out_of_stock_button,1|string|in:css,xpath',
            'selectors.product_page.out_of_stock.selector' => 'required_if:out_of_stock_button,1|array|min:1',
            'selectors.product_page.out_of_stock.selector.*' => 'required_if:out_of_stock_button,1|string',

            // اعتبارسنجی برای category_method
            'category_method' => 'required|in:selector,title',
            'category_word_count' => 'nullable|array',
            'category_word_count.*' => 'required|integer|min:1',
            'category_word_count_single' => 'nullable|integer|min:1',

            // اعتبارسنجی برای سلکتورهای دسته‌بندی چندگانه
            'selectors.product_page.category.selector' => 'nullable|array',
            'selectors.product_page.category.selector.*' => 'required|string',
            'selectors.product_page.category.selector_single' => 'nullable|string',

            // جدید: اعتبارسنجی برای product_id چندگانه
            'selectors.product_page.product_id.selector' => 'nullable|array',
            'selectors.product_page.product_id.selector.*' => 'required|string',
            'selectors.product_page.product_id.selector_single' => 'nullable|string',
            'selectors.product_page.product_id.attribute' => 'nullable|array',
            'selectors.product_page.product_id.attribute.*' => 'required|string',
            'selectors.product_page.product_id.attribute_single' => 'nullable|string',
        ];

        // اعتبارسنجی‌های خاص برای متد
        $method = (int)$request->input('method', 1);

        if ($method == 1) {
            $rules['pagination.type'] = 'required|in:query,path';
            $rules['pagination.parameter'] = 'required|string';
            $rules['pagination.separator'] = 'required|string';
            $rules['pagination.max_pages'] = 'required|integer|min:1';
            $rules['pagination.use_sample_url'] = 'nullable|boolean';
            $rules['pagination.sample_url'] = 'nullable|required_if:pagination.use_sample_url,1|url';
        } else {
            if ($method == 2) {
                $rules['share_product_id_from_method_2'] = 'boolean';
                $rules['container'] = 'required|string';
                $rules['scrool'] = 'integer|min:1';
            } else if ($method == 3) {
                $rules['container'] = 'nullable|string';
                $rules['scrool'] = 'integer|min:1';
            }

            if (in_array($method, [2, 3])) {
                $rules['pagination_method'] = 'required|in:next_button,url';
                if ($request->input('pagination_method') == 'next_button') {
                    $rules['pagination_next_button_selector'] = 'required|string';
                } else {
                    $rules['pagination_url_type'] = 'required|in:query,path';
                    $rules['pagination_url_parameter'] = 'required|string';
                    $rules['pagination_url_separator'] = 'required|string';
                    $rules['pagination_max_pages'] = 'required|integer|min:1';
                    $rules['pagination_use_sample_url'] = 'nullable|boolean';
                    $rules['pagination_sample_url'] = 'nullable|required_if:pagination_use_sample_url,1|url';
                }
            }
        }

        $rules['run_method'] = 'required|in:new,continue';
        $rules['database'] = 'required|in:clear,continue';
        $rules['use_set_category'] = 'boolean';
        $rules['set_category'] = 'nullable|required_if:use_set_category,1|string';
        $rules['title_prefix_rules.url.*'] = 'nullable|url';
        $rules['title_prefix_rules.prefix.*'] = 'nullable|string|max:255';

        if ($request->input('product_id_source') == 'main_page') {
            $rules['selectors.main_page.product_id.type'] = 'required|string';
            $rules['selectors.main_page.product_id.selector'] = 'required|string';
            $rules['selectors.main_page.product_id.attribute'] = 'required|string';
        }

        return Validator::make($request->all(), $rules);
    }

    private function buildConfig(Request $request, $method)
    {
        $config = [
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
            'selectors' => [
                'main_page' => [
                    'product_links' => [
                        'type' => $request->input('selectors.main_page.product_links.type'),
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

                    // جدید: پیکربندی product_id چندگانه
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
            ],
        ];

        // فقط در صورتی که use_set_category تیک خورده باشد، set_category را اضافه کن
        if (filter_var($request->input('use_set_category', false), FILTER_VALIDATE_BOOLEAN)) {
            $setCategory = trim($request->input('set_category', ''));
            if (!empty($setCategory)) {
                $config['set_category'] = $setCategory;
            }
        }

        if ($request->input('product_id_source') == 'main_page') {
            $config['selectors']['main_page']['product_id'] = [
                'type' => $request->input('selectors.main_page.product_id.type'),
                'selector' => $request->input('selectors.main_page.product_id.selector'),
                'attribute' => $request->input('selectors.main_page.product_id.attribute'),
            ];
        }

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

        // سایر تنظیمات method ها...
        if ($method == 1) {
            $config['method_settings'] = [
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
        } else {
            if ($method == 2) {
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
            } elseif ($method == 3) {
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
            }
        }

        return $config;
    }

    private function processCategorySelectors(Request $request)
    {
        // اگر آرایه سلکتور ارسال شده باشد
        $selectorArray = $request->input('selectors.product_page.category.selector');
        if (is_array($selectorArray) && !empty($selectorArray)) {
            // فیلتر کردن سلکتورهای خالی
            $validSelectors = array_filter($selectorArray, function ($selector) {
                return !empty(trim($selector));
            });

            if (!empty($validSelectors)) {
                return array_values($validSelectors);
            }
        }

        // اگر سلکتور واحد ارسال شده باشد
        $singleSelector = $request->input('selectors.product_page.category.selector_single');
        if (!empty(trim($singleSelector))) {
            return $singleSelector;
        }

        // مقدار پیش‌فرض خالی
        return '';
    }

    // بهبود متد processCategoryWordCount
    private function processCategoryWordCount(Request $request)
    {
        // اگر آرایه ارسال شده باشد
        $wordCountArray = $request->input('category_word_count');
        if (is_array($wordCountArray) && !empty($wordCountArray)) {
            // فیلتر کردن مقادیر معتبر
            $validCounts = array_filter($wordCountArray, function ($count) {
                return is_numeric($count) && $count > 0;
            });

            if (!empty($validCounts)) {
                return array_map('intval', array_values($validCounts));
            }
        }

        // اگر عدد واحد ارسال شده باشد
        $singleWordCount = $request->input('category_word_count_single');
        if (is_numeric($singleWordCount) && $singleWordCount > 0) {
            return (int)$singleWordCount;
        }

        // مقدار پیش‌فرض
        return 1;
    }

    private function validateCategorySettings(Request $request): array
    {
        $errors = [];

        $categoryMethod = $request->input('category_method', 'selector');

        if ($categoryMethod === 'title') {
            $wordCount = $this->processCategoryWordCount($request);

            if (is_array($wordCount)) {
                foreach ($wordCount as $count) {
                    if (!is_numeric($count) || $count <= 0) {
                        $errors[] = "تمام مقادیر تعداد کلمات باید عدد مثبت باشند";
                        break;
                    }
                }
            } elseif (!is_numeric($wordCount) || $wordCount <= 0) {
                $errors[] = "تعداد کلمات باید عدد مثبت باشد";
            }
        } elseif ($categoryMethod === 'selector') {
            $selectors = $this->processCategorySelectors($request);

            if (empty($selectors)) {
                $errors[] = "حداقل یک سلکتور برای دسته‌بندی باید وارد شود";
            }
        }

        return $errors;
    }

    private function buildPaginationConfig(Request $request)
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

    private function buildNavigationConfig(Request $request)
    {
        $navigation = [
            'pagination' => [
                'method' => $request->input('pagination_method', 'next_button'),
            ],
        ];

        if ($request->input('pagination_method') == 'next_button') {
            $navigation['pagination']['next_button'] = [
                'selector' => $request->input('pagination_next_button_selector', '')
            ];
        } else {
            $navigation['pagination']['url'] = [
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

        return $navigation;
    }

    private function processProductIdSelectors(Request $request)
    {
        // اگر سلکتور چندگانه ارسال شده
        if ($request->has('selectors.product_page.product_id.selector') &&
            is_array($request->input('selectors.product_page.product_id.selector'))) {

            $selectors = array_filter($request->input('selectors.product_page.product_id.selector'));
            return !empty($selectors) ? array_values($selectors) : null;
        }

        // اگر سلکتور تکی ارسال شده
        if ($request->has('selectors.product_page.product_id.selector_single')) {
            $singleSelector = trim($request->input('selectors.product_page.product_id.selector_single'));
            return !empty($singleSelector) ? $singleSelector : null;
        }

        return null;
    }

    /**
     * پردازش attribute های product_id چندگانه
     */
    private function processProductIdAttributes(Request $request)
    {
        // اگر attribute چندگانه ارسال شده
        if ($request->has('selectors.product_page.product_id.attribute') &&
            is_array($request->input('selectors.product_page.product_id.attribute'))) {

            $attributes = array_filter($request->input('selectors.product_page.product_id.attribute'));
            return !empty($attributes) ? array_values($attributes) : null;
        }

        // اگر attribute تکی ارسال شده
        if ($request->has('selectors.product_page.product_id.attribute_single')) {
            $singleAttribute = trim($request->input('selectors.product_page.product_id.attribute_single'));
            return !empty($singleAttribute) ? $singleAttribute : null;
        }

        return null;
    }

    /**
     * اصلاح مشکل سلکتورهای product_id در edit
     */
    private function fixProductIdSelectors(&$content)
    {
        if (isset($content['selectors']['product_page']['product_id'])) {
            $productIdConfig = &$content['selectors']['product_page']['product_id'];

            // اگر selector آرایه است، به چندگانه تبدیل کن
            if (isset($productIdConfig['selector']) && is_array($productIdConfig['selector'])) {
                // اگر تک‌المان است، به تکی تبدیل کن
                if (count($productIdConfig['selector']) === 1) {
                    $content['product_id_selector_single'] = $productIdConfig['selector'][0];
                    unset($content['product_id_selector_multiple']);
                } else {
                    $content['product_id_selector_multiple'] = $productIdConfig['selector'];
                    unset($content['product_id_selector_single']);
                }
            } else {
                // اگر string است، به تکی تبدیل کن
                $content['product_id_selector_single'] = $productIdConfig['selector'] ?? '';
                unset($content['product_id_selector_multiple']);
            }

            // همین کار را برای attribute انجام بده
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


    public function runScraper($filename)
    {
        $configPath = $this->configPath . 'private/' . $filename . '.json';

        if (!file_exists($configPath)) {
            return redirect()->route('configs.index')->with('error', 'فایل کانفیگ یافت نشد!');
        }

        $runFileName = $filename . '.json';
        $runFilePath = 'private/runs/' . $runFileName;

        if (Storage::exists($runFilePath)) {
            $existingRun = json_decode(Storage::get($runFilePath), true);

            if (isset($existingRun['status']) && $existingRun['status'] === 'running' && isset($existingRun['pid'])) {
                if ($this->isProcessRunning($existingRun['pid'])) {
                    return redirect()->route('configs.index')->with('error', 'اسکرپر برای کانفیگ ' . $filename . ' در حال حاضر در حال اجراست! لطفاً ابتدا آن را متوقف کنید.');
                }

                $command = "ps aux | grep 'scrape:start.*{$filename}' | grep -v grep";
                exec($command, $output);

                if (!empty($output)) {
                    return redirect()->route('configs.index')->with('error', 'اسکرپر برای کانفیگ ' . $filename . ' در حال حاضر در حال اجراست! لطفاً ابتدا آن را متوقف کنید.');
                }

                $existingRun['status'] = 'crashed';
                $existingRun['stopped_at'] = date('Y-m-d H:i:s');
                Storage::put($runFilePath, json_encode($existingRun, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $logDirectory = storage_path('logs/scrapers');
        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        $logFileName = $filename . '_' . date('Y-m-d_H-i-s') . '.log';
        $logFile = $logDirectory . '/' . $logFileName;

        file_put_contents($logFile, "اجرای اسکرپر برای کانفیگ {$filename} در تاریخ " . date('Y-m-d H:i:s') . " شروع شد...\n");

        // تنظیم متغیرهای محیطی برای Playwright
        $envVars = [
            'PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright',
            'NODE_PATH=' . base_path('node_modules'),
            'HOME=' . env('HOME', '/var/www'),
            'USER=' . get_current_user(),
        ];

        $envString = implode(' ', $envVars);

        // اجرای دستور بدون sudo و با متغیرهای محیطی مناسب
        $cmd = sprintf(
            'nohup bash -c "%s php %s scrape:start --config=%s" >> %s 2>&1 & echo $!',
            $envString,
            base_path('artisan'),
            $configPath,
            $logFile
        );

        $pid = exec($cmd);

        if (empty($pid) || $pid == 0) {
            file_put_contents($logFile, "\n[" . date('Y-m-d H:i:s') . "] خطا در اجرای اسکرپر: PID نامعتبر\n", FILE_APPEND);
            return redirect()->route('configs.index')->with('error', 'خطا در اجرای اسکرپر. لطفاً لاگ‌ها را بررسی کنید.');
        }

        if (!Storage::exists('private/runs')) {
            Storage::makeDirectory('private/runs', 0755);
        }

        $runInfo = [];

        if (Storage::exists($runFilePath)) {
            $runInfo = json_decode(Storage::get($runFilePath), true);

            if (!isset($runInfo['history'])) {
                $runInfo['history'] = [];
            }

            if (isset($runInfo['started_at']) && isset($runInfo['log_file'])) {
                $previousRun = [
                    'started_at' => $runInfo['started_at'],
                    'log_file' => $runInfo['log_file']
                ];

                if (isset($runInfo['stopped_at'])) {
                    $previousRun['stopped_at'] = $runInfo['stopped_at'];
                }

                if (isset($runInfo['status'])) {
                    $previousRun['status'] = $runInfo['status'];
                }

                array_unshift($runInfo['history'], $previousRun);

                if (count($runInfo['history']) > 10) {
                    $runInfo['history'] = array_slice($runInfo['history'], 0, 10);
                }
            }
        }

        $runInfo['filename'] = $filename;
        $runInfo['log_file'] = $logFileName;
        $runInfo['started_at'] = date('Y-m-d H:i:s');
        $runInfo['pid'] = (int)$pid;
        $runInfo['status'] = 'running';

        if (isset($runInfo['stopped_at'])) {
            unset($runInfo['stopped_at']);
        }

        Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->route('configs.index')->with('success', 'اسکرپر برای کانفیگ ' . $filename . ' با موفقیت اجرا شد. می‌توانید لاگ‌ها را مشاهده کنید.');
    }

    public function updateScraper($filename)
    {
        $configPath = $this->configPath . 'private/' . $filename . '.json';

        if (!file_exists($configPath)) {
            return redirect()->route('configs.index')->with('error', 'فایل کانفیگ یافت نشد!');
        }

        $runFileName = $filename . '.json';
        $runFilePath = 'private/runs/' . $runFileName;

        // بررسی وجود پروسه در حال اجرا
        if (Storage::exists($runFilePath)) {
            $existingRun = json_decode(Storage::get($runFilePath), true);

            if (isset($existingRun['status']) && $existingRun['status'] === 'running' && isset($existingRun['pid'])) {
                if ($this->isProcessRunning($existingRun['pid'])) {
                    return redirect()->route('configs.index')->with('error', 'اسکرپر برای کانفیگ ' . $filename . ' در حال حاضر در حال اجراست! لطفاً ابتدا آن را متوقف کنید.');
                }

                // بررسی پروسه‌های اضافی با پارامتر update
                $command = "ps aux | grep 'scrape:start.*{$filename}.*--update' | grep -v grep";
                exec($command, $output);

                if (!empty($output)) {
                    return redirect()->route('configs.index')->with('error', 'اسکرپر اپدیت برای کانفیگ ' . $filename . ' در حال حاضر در حال اجراست! لطفاً ابتدا آن را متوقف کنید.');
                }

                // اگر پروسه crash شده، وضعیت را به‌روزرسانی کن
                $existingRun['status'] = 'crashed';
                $existingRun['stopped_at'] = date('Y-m-d H:i:s');
                Storage::put($runFilePath, json_encode($existingRun, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        // ایجاد دایرکتوری لاگ
        $logDirectory = storage_path('logs/scrapers');
        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        // ایجاد فایل لاگ با پسوند update
        $logFileName = $filename . '_update_' . date('Y-m-d_H-i-s') . '.log';
        $logFile = $logDirectory . '/' . $logFileName;

        file_put_contents($logFile, "اجرای اسکرپر اپدیت برای کانفیگ {$filename} در تاریخ " . date('Y-m-d H:i:s') . " شروع شد...\n");

        // تنظیم متغیرهای محیطی برای Playwright
        $envVars = [
            'PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright',
            'NODE_PATH=' . base_path('node_modules'),
            'HOME=' . env('HOME', '/var/www'),
            'USER=' . get_current_user(),
        ];

        $envString = implode(' ', $envVars);

        // اجرای دستور با پارامتر --update
        $cmd = sprintf(
            'nohup bash -c "%s php %s scrape:start --config=%s --update" >> %s 2>&1 & echo $!',
            $envString,
            base_path('artisan'),
            $configPath,
            $logFile
        );

        $pid = exec($cmd);

        if (empty($pid) || $pid == 0) {
            file_put_contents($logFile, "\n[" . date('Y-m-d H:i:s') . "] خطا در اجرای اسکرپر اپدیت: PID نامعتبر\n", FILE_APPEND);
            return redirect()->route('configs.index')->with('error', 'خطا در اجرای اسکرپر اپدیت. لطفاً لاگ‌ها را بررسی کنید.');
        }

        // ایجاد دایرکتوری runs در صورت عدم وجود
        if (!Storage::exists('private/runs')) {
            Storage::makeDirectory('private/runs', 0755);
        }

        $runInfo = [];

        // مدیریت تاریخچه اجراها
        if (Storage::exists($runFilePath)) {
            $runInfo = json_decode(Storage::get($runFilePath), true);

            if (!isset($runInfo['history'])) {
                $runInfo['history'] = [];
            }

            // اضافه کردن اجرای قبلی به تاریخچه
            if (isset($runInfo['started_at']) && isset($runInfo['log_file'])) {
                $previousRun = [
                    'started_at' => $runInfo['started_at'],
                    'log_file' => $runInfo['log_file'],
                    'type' => isset($runInfo['type']) ? $runInfo['type'] : 'normal'
                ];

                if (isset($runInfo['stopped_at'])) {
                    $previousRun['stopped_at'] = $runInfo['stopped_at'];
                }

                if (isset($runInfo['status'])) {
                    $previousRun['status'] = $runInfo['status'];
                }

                array_unshift($runInfo['history'], $previousRun);

                // نگه داشتن فقط 10 آخرین اجرا
                if (count($runInfo['history']) > 10) {
                    $runInfo['history'] = array_slice($runInfo['history'], 0, 10);
                }
            }
        }

        // تنظیم اطلاعات اجرای فعلی
        $runInfo['filename'] = $filename;
        $runInfo['log_file'] = $logFileName;
        $runInfo['started_at'] = date('Y-m-d H:i:s');
        $runInfo['pid'] = (int)$pid;
        $runInfo['status'] = 'running';
        $runInfo['type'] = 'update'; // مشخص کردن نوع اجرا

        // حذف stopped_at از اجرای قبلی
        if (isset($runInfo['stopped_at'])) {
            unset($runInfo['stopped_at']);
        }

        Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->route('configs.index')->with('success', 'اسکرپر اپدیت برای کانفیگ ' . $filename . ' با موفقیت اجرا شد. می‌توانید لاگ‌ها را مشاهده کنید.');
    }

    public function history()
    {
        $files = Storage::files('private');
        $configs = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $configData = [
                    'filename' => $filename,
                    'content' => json_decode(Storage::get($file), true)
                ];

                $runFilePath = 'private/runs/' . $filename . '.json';
                if (Storage::exists($runFilePath)) {
                    $runInfo = json_decode(Storage::get($runFilePath), true);
                    if (isset($runInfo['history'])) {
                        $configData['history'] = $runInfo['history'];
                    }
                }

                $configs[] = $configData;
            }
        }

        usort($configs, function ($a, $b) {
            $aTime = isset($a['history'][0]['started_at']) ? $a['history'][0]['started_at'] : '0000-00-00';
            $bTime = isset($b['history'][0]['started_at']) ? $b['history'][0]['started_at'] : '0000-00-00';
            return strcmp($bTime, $aTime);
        });

        return view('configs.history', compact('configs'));
    }

    public function showLogs($filename)
    {
        $logDirectory = storage_path('logs/scrapers');

        $logFiles = [];

        if (file_exists($logDirectory)) {
            $allFiles = scandir($logDirectory);

            foreach ($allFiles as $file) {
                if (strpos($file, $filename . '_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $datePart = str_replace($filename . '_', '', pathinfo($file, PATHINFO_FILENAME));

                    $logFiles[] = [
                        'filename' => $file,
                        'date' => $datePart,
                        'full_path' => $logDirectory . '/' . $file,
                        'size' => filesize($logDirectory . '/' . $file),
                        'last_modified' => filemtime($logDirectory . '/' . $file)
                    ];
                }
            }

            usort($logFiles, function ($a, $b) {
                return $b['last_modified'] - $a['last_modified'];
            });
        }

        return view('configs.logs', compact('logFiles', 'filename'));
    }


    public function getLogContent($logfile)
    {
        $logPath = storage_path('logs/scrapers/' . $logfile);

        if (!file_exists($logPath)) {
            return response('فایل لاگ یافت نشد.', 404);
        }

        $content = file_get_contents($logPath);

        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return response($content)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }


    public function stopScraper($filename)
    {
        $runFilePath = 'private/runs/' . $filename . '.json';

        if (!Storage::exists($runFilePath)) {
            return redirect()->route('configs.index')->with('error', 'هیچ اسکرپر در حال اجرایی برای این کانفیگ یافت نشد.');
        }

        $runInfo = json_decode(Storage::get($runFilePath), true);

        if (!isset($runInfo['pid']) || !isset($runInfo['status']) || $runInfo['status'] !== 'running') {
            return redirect()->route('configs.index')->with('error', 'اسکرپر در حال حاضر در حال اجرا نیست.');
        }

        $pid = $runInfo['pid'];
        $stopped = false;
        $scraperType = isset($runInfo['type']) ? $runInfo['type'] : 'normal';

        // متوقف کردن پروسه اصلی
        if ($this->isProcessRunning($pid)) {
            exec("kill -9 {$pid} 2>&1", $output, $result);

            if ($result === 0) {
                $stopped = true;
            }
        }

        // جستجو و متوقف کردن پروسه‌های معمولی
        $command = "ps aux | grep 'scrape:start.*{$filename}' | grep -v grep | grep -v -- '--update' | awk '{print $2}'";
        exec($command, $normalOutput);

        // جستجو و متوقف کردن پروسه‌های اپدیت
        $updateCommand = "ps aux | grep 'scrape:start.*{$filename}.*--update' | grep -v grep | awk '{print $2}'";
        exec($updateCommand, $updateOutput);

        // ترکیب خروجی‌ها
        $allPids = array_merge($normalOutput, $updateOutput);

        if (!empty($allPids)) {
            foreach ($allPids as $extraPid) {
                if (!empty($extraPid)) {
                    exec("kill -9 {$extraPid} 2>&1");
                    $stopped = true;
                }
            }
        }

        if ($stopped) {
            $runInfo['status'] = 'stopped';
            $runInfo['stopped_at'] = date('Y-m-d H:i:s');

            Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // لاگ کردن متوقف شدن
            if (isset($runInfo['log_file'])) {
                $logPath = storage_path('logs/scrapers/' . $runInfo['log_file']);
                if (file_exists($logPath)) {
                    $typeText = $scraperType === 'update' ? 'اپدیت' : 'معمولی';
                    file_put_contents($logPath, "\n[" . date('Y-m-d H:i:s') . "] اسکرپر {$typeText} به صورت دستی متوقف شد.\n", FILE_APPEND);
                }
            }

            $typeText = $scraperType === 'update' ? 'اپدیت' : '';
            return redirect()->route('configs.index')->with('success', "اسکرپر {$typeText} با موفقیت متوقف شد.");
        } else {
            return redirect()->route('configs.index')->with('error', 'پروسه اسکرپر یافت نشد، اما وضعیت آن به متوقف شده تغییر کرد.');
        }
    }

    private function isProcessRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }

        exec("ps -p {$pid}", $output, $result);
        return $result === 0;
    }

    public function singleProduct(Request $request)
    {
        Log::info('singleProduct method called', [
            'method' => $request->method(),
            'user' => auth()->user() ? auth()->user()->id : 'guest',
            'input' => $request->all(),
        ]);

        if ($request->isMethod('get')) {
            // نمایش فرم برای درخواست GET
            return view('configs.single_product');
        }

        // پردازش درخواست POST
        $request->validate([
            'product_url' => 'required|url',
            'title_selector' => 'required|string',
            'price_selector' => 'required|string',
            'category_selector' => 'nullable|string',
            'availability_selector' => 'nullable|string',
            'image_selector' => 'nullable|string',
            'product_id_selector' => 'nullable|string',
        ]);

        Log::info('Validation passed in singleProduct', ['input' => $request->all()]);

        // ساخت کانفیگ برای اسکریپینگ
        $config = [
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
            'out_of_stock_button' => true,
            'product_id_fallback_script_patterns' => [
                'product_id:\\s*\"(\\d+)\"',
                'product_id:\\s*(\\d+)'
            ],
            'category_method' => 'selector',
            'category_word_count' => 1,
            'guarantee_method' => 'selector',
            'guarantee_keywords' => ['گارانتی'],
            'availability_keywords' => [
                'positive' => ['موجود', 'افزودن به سبد خرید'],
                'negative' => ['ناموجود', 'در حال حاضر این محصول در انبار موجود نیست و در دسترس نمی باشد.']
            ],
            'price_keywords' => [
                'unpriced' => ['تماس بگیرید']
            ],
            'selectors' => [
                'product_page' => [
                    'title' => [
                        'type' => 'css',
                        'selector' => $request->input('title_selector'),
                    ],
                    'price' => [
                        'type' => 'css',
                        'selector' => [$request->input('price_selector')],
                    ],
                    'category' => [
                        'type' => 'css',
                        'selector' => $request->input('category_selector') ? [$request->input('category_selector')] : [],
                        'attribute' => null,
                    ],
                    'availability' => [
                        'type' => 'css',
                        'selector' => $request->input('availability_selector') ? [$request->input('availability_selector')] : [],
                    ],
                    'out_of_stock' => [
                        'type' => null,
                        'selector' => [],
                    ],
                    'image' => [
                        'type' => 'css',
                        'selector' => $request->input('image_selector') ? $request->input('image_selector') : null,
                        'attribute' => 'href',
                    ],
                    'guarantee' => [
                        'type' => 'css',
                        'selector' => null,
                    ],
                    'product_id' => [
                        'type' => 'css',
                        'selector' => $request->input('product_id_selector') ? [$request->input('product_id_selector')] : [],
                        'attribute' => ['value'],
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

        try {
            Log::info('Starting scrapeMultiple in singleProduct', ['config' => $config]);

            // ایجاد نمونه StartController
            $startController = new StartController($config);

            // تنظیم callback برای جمع‌آوری لاگ‌ها
            $logs = [];
            $startController->setOutputCallback(function ($message) use (&$logs) {
                $logs[] = $message;
            });

            // اجرای اسکریپینگ
            $result = $startController->scrapeMultiple();

            Log::info('scrapeMultiple completed', ['result' => $result, 'logs_count' => count($logs)]);

            // ترکیب لاگ‌ها و نتیجه برای نمایش در ویو
            return view('configs.single_product', [
                'result' => $result,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in singleProduct scrape: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return view('configs.single_product', [
                'result' => [
                    'status' => 'error',
                    'message' => 'خطا در اجرای تست: ' . $e->getMessage(),
                    'products' => [],
                ],
                'logs' => [],
            ]);
        }
    }

}
