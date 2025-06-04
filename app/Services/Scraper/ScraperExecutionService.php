<?php

namespace App\Services\Scraper;

use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * سرویس اجرای اسکرپر
 */
class ScraperExecutionService
{
    private string $configPath;
    private ScraperProcessService $processService;

    public function __construct(ScraperProcessService $processService)
    {
        $this->configPath = storage_path('app/private/');
        $this->processService = $processService;
    }

    /**
     * اجرای اسکرپر عادی
     */
    public function runScraper(string $filename): void
    {
        $configPath = $this->configPath . 'private/' . $filename . '.json';

        if (!file_exists($configPath)) {
            throw new Exception('فایل کانفیگ یافت نشد!');
        }

        $this->checkExistingProcess($filename, 'normal');

        $logInfo = $this->prepareLogFile($filename);
        $pid = $this->executeScraperCommand($configPath, $logInfo['logFile']);

        $this->saveRunInfo($filename, $logInfo['logFileName'], $pid, 'normal');
    }

    /**
     * اجرای اسکرپر در حالت آپدیت
     */
    public function runUpdateScraper(string $filename): void
    {
        $configPath = $this->configPath . 'private/' . $filename . '.json';

        if (!file_exists($configPath)) {
            throw new Exception('فایل کانفیگ یافت نشد!');
        }

        $this->checkExistingProcess($filename, 'update');

        $logInfo = $this->prepareLogFile($filename, 'update');
        $pid = $this->executeScraperCommand($configPath, $logInfo['logFile'], true);

        $this->saveRunInfo($filename, $logInfo['logFileName'], $pid, 'update');
    }

    /**
     * بررسی وجود پروسه در حال اجرا
     */
    private function checkExistingProcess(string $filename, string $type): void
    {
        $runFilePath = 'private/runs/' . $filename . '.json';

        if (Storage::exists($runFilePath)) {
            $existingRun = json_decode(Storage::get($runFilePath), true);

            if (isset($existingRun['status']) && $existingRun['status'] === 'running' && isset($existingRun['pid'])) {
                if ($this->processService->isProcessRunning($existingRun['pid'])) {
                    $typeText = $type === 'update' ? 'اپدیت' : '';
                    throw new Exception("اسکرپر {$typeText} برای کانفیگ {$filename} در حال حاضر در حال اجراست! لطفاً ابتدا آن را متوقف کنید.");
                }

                // بررسی پروسه‌های اضافی
                $processCommand = $type === 'update'
                    ? "ps aux | grep 'scrape:start.*{$filename}.*--update' | grep -v grep"
                    : "ps aux | grep 'scrape:start.*{$filename}' | grep -v grep | grep -v -- '--update'";

                exec($processCommand, $output);

                if (!empty($output)) {
                    $typeText = $type === 'update' ? 'اپدیت' : '';
                    throw new Exception("اسکرپر {$typeText} برای کانفیگ {$filename} در حال حاضر در حال اجراست! لطفاً ابتدا آن را متوقف کنید.");
                }

                // اگر پروسه crash شده، وضعیت را به‌روزرسانی کن
                $existingRun['status'] = 'crashed';
                $existingRun['stopped_at'] = date('Y-m-d H:i:s');
                Storage::put($runFilePath, json_encode($existingRun, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    /**
     * آماده‌سازی فایل لاگ
     */
    private function prepareLogFile(string $filename, string $type = 'normal'): array
    {
        $logDirectory = storage_path('logs/scrapers');
        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        $typePrefix = $type === 'update' ? '_update' : '';
        $logFileName = $filename . $typePrefix . '_' . date('Y-m-d_H-i-s') . '.log';
        $logFile = $logDirectory . '/' . $logFileName;

        $typeText = $type === 'update' ? 'اپدیت' : '';
        $startMessage = "اجرای اسکرپر {$typeText} برای کانفیگ {$filename} در تاریخ " . date('Y-m-d H:i:s') . " شروع شد...\n";
        file_put_contents($logFile, $startMessage);

        return [
            'logFile' => $logFile,
            'logFileName' => $logFileName
        ];
    }

    /**
     * اجرای دستور اسکرپر
     */
    private function executeScraperCommand(string $configPath, string $logFile, bool $isUpdate = false): int
    {
        // تنظیم متغیرهای محیطی برای Playwright
        $envVars = [
            'PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright',
            'NODE_PATH=' . base_path('node_modules'),
            'HOME=' . env('HOME', '/var/www'),
            'USER=' . get_current_user(),
        ];

        $envString = implode(' ', $envVars);
        $updateFlag = $isUpdate ? ' --update' : '';

        // اجرای دستور
        $cmd = sprintf(
            'nohup bash -c "%s php %s scrape:start --config=%s%s" >> %s 2>&1 & echo $!',
            $envString,
            base_path('artisan'),
            $configPath,
            $updateFlag,
            $logFile
        );

        $pid = exec($cmd);

        if (empty($pid) || $pid == 0) {
            $errorMessage = "\n[" . date('Y-m-d H:i:s') . "] خطا در اجرای اسکرپر: PID نامعتبر\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            throw new Exception('خطا در اجرای اسکرپر. لطفاً لاگ‌ها را بررسی کنید.');
        }

        return (int)$pid;
    }

    /**
     * ذخیره اطلاعات اجرا
     */
    private function saveRunInfo(string $filename, string $logFileName, int $pid, string $type): void
    {
        $runFilePath = 'private/runs/' . $filename . '.json';

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
        $runInfo['pid'] = $pid;
        $runInfo['status'] = 'running';
        $runInfo['type'] = $type;

        // حذف stopped_at از اجرای قبلی
        if (isset($runInfo['stopped_at'])) {
            unset($runInfo['stopped_at']);
        }

        Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
