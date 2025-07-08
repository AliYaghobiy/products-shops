<?php

namespace App\Services\Config;

use Exception;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Helpers\PersianDateHelper;

/**
 * سرویس مدیریت فایل‌های کانفیگ
 */
class ConfigFileService
{
    private string $configPath;
    private array $directories;

    public function __construct()
    {
        $this->configPath = storage_path('app/private/');
        $this->directories = [
            storage_path('app/private/'),
            storage_path('app/private/runs/'),
            storage_path('logs/scrapers/'),
        ];
    }

    /**
     * اطمینان از وجود دایرکتوری‌های مورد نیاز
     */
    public function ensureDirectoriesExist(): void
    {
        foreach ($this->directories as $directory) {
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * دریافت تمام کانفیگ‌ها همراه با وضعیت اجرا
     */
    public function getAllConfigs(): array
    {
        try {
            $files = Storage::files('private');
            $configs = [];

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    
                    // بررسی اندازه فایل قبل از خواندن
                    $filePath = Storage::path($file);
                    $fileSize = filesize($filePath);
                    
                    // اگر فایل بزرگتر از 10MB باشد، آن را نادیده بگیریم یا به صورت ساده بخوانیم
                    if ($fileSize > 10 * 1024 * 1024) {
                        Log::warning("فایل کانفیگ بزرگ تشخیص داده شد: {$filename} ({$fileSize} bytes)");
                        continue;
                    }
                    
                    try {
                        $configData = $this->buildConfigData($filename, $file);
                        $configs[] = $configData;
                    } catch (Exception $e) {
                        Log::error("خطا در خواندن کانفیگ {$filename}: " . $e->getMessage());
                        // ادامه دادن به فایل بعدی
                        continue;
                    }
                }
            }

            // مرتب‌سازی بر اساس آخرین تاریخ ایجاد/ویرایش و سپس تاریخ اجرا
            usort($configs, function ($a, $b) {
                try {
                    // اولویت اول: فایل‌های تازه ایجاد شده یا ویرایش شده
                    $aFileTime = Storage::lastModified('private/' . $a['filename'] . '.json');
                    $bFileTime = Storage::lastModified('private/' . $b['filename'] . '.json');

                    // اگر تفاوت زمان ویرایش کمتر از 5 دقیقه باشد، بر اساس تاریخ اجرا مرتب کن
                    if (abs($aFileTime - $bFileTime) < 300) { // 5 دقیقه = 300 ثانیه
                        $aStarted = $a['started_at'] ?? '0000-00-00 00:00:00';
                        $bStarted = $b['started_at'] ?? '0000-00-00 00:00:00';
                        return strcmp($bStarted, $aStarted);
                    }

                    // در غیر این صورت بر اساس آخرین ویرایش
                    return $bFileTime <=> $aFileTime;
                } catch (Exception $e) {
                    Log::error("خطا در مرتب‌سازی کانفیگ‌ها: " . $e->getMessage());
                    return 0;
                }
            });

            return $configs;
            
        } catch (Exception $e) {
            Log::error("خطا در دریافت لیست کانفیگ‌ها: " . $e->getMessage());
            return [];
        }
    }

    public function getRunningConfigs()
    {
        $configs = $this->getAllConfigs();
        $runningConfigs = array_filter($configs, function ($config) {
            return $config['status'] === 'running';
        });

        return response()->json([
            'running_configs' => array_values($runningConfigs)
        ]);
    }

    /**
     * ساخت داده‌های کانفیگ با وضعیت اجرا
     */
    private function buildConfigData(string $filename, string $file): array
    {
        $configData = [
            'filename' => $filename,
            'content' => json_decode(Storage::get($file), true),
            'status' => 'stopped',
            'type' => 'normal',
            'started_at' => null,
            'log_file' => null,
            'last_run_date' => null
        ];

        $runFilePath = 'private/runs/' . $filename . '.json';
        if (Storage::exists($runFilePath)) {
            $configData = $this->updateConfigStatus($configData, $runFilePath, $filename);
        }

        // اضافه کردن منطق رنگ‌بندی
        $configData['dot_class'] = $this->getDotClass($configData);

        return $configData;
    }

    /**
     * تعیین کلاس رنگ دایره بر اساس آخرین اجرا
     */
    private function getDotClass(array $configData): string
    {
        // اگر در حال اجرا است، سبز
        if ($configData['status'] === 'running') {
            return 'green';
        }

        // اگر تاریخ آخرین اجرا موجود نیست، قرمز
        if (!$configData['last_run_date']) {
            return 'red';
        }

        try {
            // استفاده از Helper شمسی
            if (PersianDateHelper::isToday($configData['last_run_date'])) {
                return 'green';
            }

            if (PersianDateHelper::isYesterday($configData['last_run_date'])) {
                return 'yellow';
            }

            return 'red';

        } catch (Exception $e) {
            return 'red';
        }
    }


    /**
     * به‌روزرسانی وضعیت کانفیگ
     */
    private function updateConfigStatus(array $configData, string $runFilePath, string $filename): array
    {
        $runInfo = json_decode(Storage::get($runFilePath), true);

        $configData['status'] = $runInfo['status'] ?? 'stopped';
        $configData['type'] = $runInfo['type'] ?? 'normal';
        $configData['started_at'] = $runInfo['started_at'] ?? null;
        $configData['log_file'] = $runInfo['log_file'] ?? null;

        // اضافه کردن تاریخ آخرین اجرا
        if (isset($runInfo['history']) && is_array($runInfo['history']) && !empty($runInfo['history'])) {
            // آخرین اجرا از تاریخچه
            $configData['last_run_date'] = $runInfo['history'][0]['started_at'] ?? null;
        } elseif (isset($runInfo['started_at'])) {
            // اجرای فعلی
            $configData['last_run_date'] = $runInfo['started_at'];
        }

        // بررسی واقعی بودن وضعیت running
        if ($configData['status'] === 'running' && isset($runInfo['pid'])) {
            if (!$this->isProcessRunning($runInfo['pid'])) {
                $configData = $this->checkAndUpdateCrashedProcess($configData, $runInfo, $runFilePath, $filename);
            }
        }

        return $configData;
    }

    /**
     * بررسی و به‌روزرسانی پروسه‌های crash شده
     */
    private function checkAndUpdateCrashedProcess(array $configData, array $runInfo, string $runFilePath, string $filename): array
    {
        $processCommand = $configData['type'] === 'update'
            ? "ps aux | grep 'scrape:start.*{$filename}.*--update' | grep -v grep"
            : "ps aux | grep 'scrape:start.*{$filename}' | grep -v grep | grep -v -- '--update'";

        exec($processCommand, $output);

        if (empty($output)) {
            $configData['status'] = 'stopped';
            $runInfo['status'] = 'stopped';
            $runInfo['stopped_at'] = date('Y-m-d H:i:s');
            Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $configData;
    }

    /**
     * بررسی اجرای پروسه
     */
    private function isProcessRunning(int $pid): bool
    {
        if (empty($pid)) {
            return false;
        }
        exec("ps -p {$pid}", $output, $result);
        return $result === 0;
    }

    /**
     * دریافت کانفیگ‌ها همراه تاریخچه
     */
    public function getConfigsWithHistory(): array
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

        // مرتب‌سازی بر اساس آخرین اجرا
        usort($configs, function ($a, $b) {
            $aTime = isset($a['history'][0]['started_at']) ? $a['history'][0]['started_at'] : '0000-00-00';
            $bTime = isset($b['history'][0]['started_at']) ? $b['history'][0]['started_at'] : '0000-00-00';
            return strcmp($bTime, $aTime);
        });

        return $configs;
    }

    /**
     * دریافت کانفیگ خاص
     */
    public function getConfig(string $filename): array
    {
        $filePath = "private/{$filename}.json";

        if (!Storage::exists($filePath)) {
            throw new Exception('فایل کانفیگ یافت نشد.');
        }

        $content = json_decode(Storage::get($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('خطا در خواندن فایل کانفیگ.');
        }

        return $content;
    }

    /**
     * ذخیره کانفیگ
     */
    public function saveConfig(string $filename, array $config): void
    {
        Storage::put('private/' . $filename, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * به‌روزرسانی کانفیگ
     */
    public function updateConfig(string $filename, array $config): void
    {
        Storage::put('private/' . $filename . '.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * حذف کانفیگ
     */
    public function deleteConfig(string $filename): void
    {
        $filePath = "private/{$filename}.json";

        if (!Storage::exists($filePath)) {
            throw new Exception('فایل کانفیگ یافت نشد.');
        }

        Storage::delete($filePath);
    }
}
