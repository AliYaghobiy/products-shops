<?php

namespace App\Http\Controllers;

use App\Services\Config\ConfigBuilderService;
use App\Services\Config\ConfigFileService;
use App\Services\Config\ConfigValidationService;
use App\Services\Scraper\ScraperExecutionService;
use App\Services\Scraper\ScraperLogService;
use App\Services\Scraper\ScraperProcessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    private ConfigFileService $fileService;
    private ConfigValidationService $validationService;
    private ConfigBuilderService $builderService;
    private ScraperExecutionService $executionService;
    private ScraperLogService $logService;
    private ScraperProcessService $processService;

    public function __construct(
        ConfigFileService       $fileService,
        ConfigValidationService $validationService,
        ConfigBuilderService    $builderService,
        ScraperExecutionService $executionService,
        ScraperLogService       $logService,
        ScraperProcessService   $processService
    )
    {
        $this->fileService = $fileService;
        $this->validationService = $validationService;
        $this->builderService = $builderService;
        $this->executionService = $executionService;
        $this->logService = $logService;
        $this->processService = $processService;

        $this->fileService->ensureDirectoriesExist();
    }

    /**
     * نمایش لیست کانفیگ‌ها
     */
    public function index()
    {
        $configs = $this->fileService->getAllConfigs();

        // اضافه کردن آمار برای هر کانفیگ
        foreach ($configs as &$config) {
            $stats = $this->logService->getConfigStats($config['filename']);
            $config['stats'] = $stats;
        }

        return view('configs.index', compact('configs'));
    }

    /**
     * نمایش فرم ایجاد کانفیگ جدید
     */
    public function create()
    {
        return view('configs.create');
    }



    public function testDatabase(string $filename)
    {
        try {
            // پیدا کردن فایل کانفیگ با استفاده از Storage
            $configFilePath = 'private/' . $filename . '.json';

            if (!Storage::exists($configFilePath)) {
                return redirect()->back()->with('error', 'فایل کانفیگ یافت نشد: ' . $filename);
            }

            $config = json_decode(Storage::get($configFilePath), true);

            if (!$config) {
                return redirect()->back()->with('error', 'خطا در خواندن فایل کانفیگ.');
            }

            // استخراج نام دیتابیس دقیقاً مثل کد اصلی
            $baseUrl = $config['base_urls'][0] ?? '';
            if (empty($baseUrl)) {
                return redirect()->back()->with('error', 'URL پایه در کانفیگ تعریف نشده است.');
            }

            $host = parse_url($baseUrl, PHP_URL_HOST);
            if (!$host) {
                return redirect()->back()->with('error', 'URL پایه نامعتبر است: ' . $baseUrl);
            }

            // دقیقاً مثل کد اصلی
            $host = preg_replace('/^www\./', '', $host);
            $databaseName = str_replace('.', '_', $host);

            // اجرای کامند تست
            $process = new Process([
                'php',
                base_path('artisan'),
                'test:database',
                $databaseName
            ]);

            $process->setTimeout(60);
            $process->run();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            // بررسی موفقیت اجرا
            if ($process->isSuccessful()) {
                return view('configs.database-test', [
                    'filename' => $filename,
                    'database_name' => $databaseName,
                    'output' => $output,
                    'success' => true
                ]);
            } else {
                return view('configs.database-test', [
                    'filename' => $filename,
                    'database_name' => $databaseName,
                    'output' => $errorOutput ?: $output,
                    'success' => false
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'خطا در تست دیتابیس: ' . $e->getMessage());
        }
    }


    /**
     * ذخیره کانفیگ جدید
     */
    public function store(Request $request)
    {
        $validator = $this->validationService->getValidator($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $config = $this->builderService->buildConfig($request);
            $filename = $request->input('site_name') . '.json';

            $this->fileService->saveConfig($filename, $config);

            return redirect()->route('configs.index')
                ->with('success', 'کانفیگ با موفقیت ذخیره شد!');
        } catch (\Exception $e) {
            Log::error('خطا در ذخیره کانفیگ: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'خطا در ذخیره کانفیگ')
                ->withInput();
        }
    }

    /**
     * نمایش فرم ویرایش کانفیگ
     */
    public function edit($filename)
    {
        try {
            $content = $this->fileService->getConfig($filename);
            $content = $this->builderService->prepareForEdit($content);

            return view('configs.edit', compact('content', 'filename'));
        } catch (\Exception $e) {
            return redirect()->route('configs.index')
                ->with('error', 'خطا در خواندن فایل کانفیگ.');
        }
    }

    /**
     * به‌روزرسانی کانفیگ
     */
    public function update(Request $request, $filename)
    {
        $validator = $this->validationService->getValidator($request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $config = $this->builderService->buildConfig($request);
            $this->fileService->updateConfig($filename, $config);

            return redirect()->route('configs.index')
                ->with('success', 'کانفیگ با موفقیت به‌روزرسانی شد!');
        } catch (\Exception $e) {
            Log::error('خطا در به‌روزرسانی کانفیگ: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'خطا در به‌روزرسانی کانفیگ')
                ->withInput();
        }
    }

    /**
     * حذف کانفیگ
     */
    public function destroy($filename)
    {
        try {
            $this->fileService->deleteConfig($filename);
            return redirect()->route('configs.index')
                ->with('success', 'کانفیگ با موفقیت حذف شد!');
        } catch (\Exception $e) {
            return redirect()->route('configs.index')
                ->with('error', 'فایل کانفیگ یافت نشد.');
        }
    }

    /**
     * اجرای اسکرپر برای کانفیگ
     */
    public function runScraper($filename)
    {
        try {
            $this->executionService->runScraper($filename);
            return redirect()->route('configs.index')
                ->with('success', "اسکرپر برای کانفیگ {$filename} با موفقیت اجرا شد.");
        } catch (\Exception $e) {
            return redirect()->route('configs.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * اجرای اسکرپر در حالت آپدیت
     */
    public function updateScraper($filename)
    {
        try {
            $this->executionService->runUpdateScraper($filename);
            return redirect()->route('configs.index')
                ->with('success', "اسکرپر اپدیت برای کانفیگ {$filename} با موفقیت اجرا شد.");
        } catch (\Exception $e) {
            return redirect()->route('configs.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * متوقف کردن اسکرپر
     */
    public function stopScraper($filename)
    {
        try {
            $result = $this->processService->stopScraper($filename);
            return redirect()->route('configs.index')
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            return redirect()->route('configs.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * نمایش تاریخچه اجراها
     */
    public function history()
    {
        $configs = $this->fileService->getConfigsWithHistory();
        return view('configs.history', compact('configs'));
    }

    /**
     * نمایش لاگ‌های کانفیگ
     */
    public function showLogs($filename)
    {
        $logFiles = $this->logService->getLogFiles($filename);
        return view('configs.logs', compact('logFiles', 'filename'));
    }

    /**
     * دریافت محتوای فایل لاگ
     */
    public function getLogContent($logfile)
    {
        try {
            $content = $this->logService->getLogContent($logfile);
            return response($content)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        } catch (\Exception $e) {
            return response('فایل لاگ یافت نشد.', 404);
        }
    }

    /**
     * حذف همه لاگ‌ها
     */
    public function deleteAllLogs()
    {
        $result = $this->logService->deleteAllLogs();
        return redirect()->route('configs.index')->with($result['type'], $result['message']);
    }

    /**
     * حذف لاگ‌های مربوط به کانفیگ خاص
     */
    public function deleteConfigLogs(Request $request)
    {
        $configFilename = $request->input('config_filename');
        $result = $this->logService->deleteConfigLogs($configFilename);
        return redirect()->back()->with($result['type'], $result['message']);
    }

    /**
     * حذف یک فایل لاگ خاص
     */
    public function deleteLog($logfile)
    {
        try {
            $this->logService->deleteLog($logfile);
            return redirect()->back()->with('success', 'فایل لاگ با موفقیت حذف شد.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'خطا در حذف فایل لاگ.');
        }
    }

    /**
     * تست محصول واحد
     */
    public function singleProduct(Request $request)
    {
        Log::info('singleProduct method called', [
            'method' => $request->method(),
            'user' => auth()->user() ? auth()->user()->id : 'guest',
            'input' => $request->all(),
        ]);

        // اگر درخواست AJAX باشد (یا هدر Accept: application/json داشته باشد)
        $isAjax = $request->wantsJson() || $request->ajax();

        if ($request->isMethod('get') && !$isAjax) {
            return view('configs.single_product');
        }

        // اعتبارسنجی درخواست
        $validator = $this->validationService->validateSingleProductRequest($request);
        if ($validator->fails()) {
            if ($isAjax) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'خطای اعتبارسنجی',
                    'errors' => $validator->errors()->all(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $config = $this->builderService->buildSingleProductConfig($request);
            $result = $this->executeSingleProductTest($config);

            if ($isAjax) {
                return response()->json([
                    'status' => 'success',
                    'result' => $result['result'],
                    'logs' => $result['logs'],
                ]);
            }

            return view('configs.single_product', [
                'result' => $result['result'],
                'logs' => $result['logs'],
            ]);
        } catch (\Exception $e) {
            Log::error('خطا در تست محصول واحد: ' . $e->getMessage());
            if ($isAjax) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'خطا در اجرای تست: ' . $e->getMessage(),
                    'products' => [],
                    'logs' => [],
                ], 500);
            }

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

    /**
     * اجرای تست محصول واحد
     */
    private function executeSingleProductTest(array $config): array
    {
        Log::info('شروع تست محصول واحد', ['config' => $config]);

        $startController = new StartController($config);
        $logs = [];

        $startController->setOutputCallback(function ($message) use (&$logs) {
            $logs[] = $message;
        });

        $result = $startController->scrapeMultiple();

        Log::info('تست محصول واحد تکمیل شد', [
            'result' => $result,
            'logs_count' => count($logs)
        ]);

        return ['result' => $result, 'logs' => $logs];
    }
}
