<?php
namespace App\Services\Scraper;

use Illuminate\Support\Facades\Storage;
use Exception;
use App\Helpers\PersianDateHelper;

/**
 * سرویس مدیریت پروسه‌های اسکرپر
 */
class ScraperProcessService
{
    /**
     * متوقف کردن اسکرپر
     */
    public function stopScraper(string $filename): array
    {
        $runFilePath = 'private/runs/' . $filename . '.json';

        if (!Storage::exists($runFilePath)) {
            throw new Exception('هیچ اسکرپر در حال اجرایی برای این کانفیگ یافت نشد.');
        }

        $runInfo = json_decode(Storage::get($runFilePath), true);

        if (!isset($runInfo['pid']) || !isset($runInfo['status']) || $runInfo['status'] !== 'running') {
            throw new Exception('اسکرپر در حال حاضر در حال اجرا نیست.');
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

        // جستجو و متوقف کردن پروسه‌های اضافی
        $stopped = $this->killAdditionalProcesses($filename) || $stopped;

        if ($stopped) {
            $this->updateStoppedStatus($runInfo, $runFilePath, $scraperType);
            $typeText = $scraperType === 'update' ? 'اپدیت' : '';
            return ['message' => "اسکرپر {$typeText} با موفقیت متوقف شد."];
        } else {
            throw new Exception('پروسه اسکرپر یافت نشد، اما وضعیت آن به متوقف شده تغییر کرد.');
        }
    }

    /**
     * بررسی اجرای پروسه
     */
    public function isProcessRunning(int $pid): bool
    {
        if (empty($pid)) {
            return false;
        }

        exec("ps -p {$pid}", $output, $result);
        return $result === 0;
    }

    /**
     * متوقف کردن پروسه‌های اضافی
     */
    private function killAdditionalProcesses(string $filename): bool
    {
        $stopped = false;

        // جستجو و متوقف کردن پروسه‌های معمولی
        $normalCommand = "ps aux | grep 'scrape:start.*{$filename}' | grep -v grep | grep -v -- '--update' | awk '{print $2}'";
        exec($normalCommand, $normalOutput);

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

        return $stopped;
    }

    /**
     * به‌روزرسانی وضعیت متوقف شده
     */
    private function updateStoppedStatus(array &$runInfo, string $runFilePath, string $scraperType): void
    {
        $runInfo['status'] = 'stopped';
        $runInfo['stopped_at'] = PersianDateHelper::now();

        // به‌روزرسانی تاریخچه
        if (!isset($runInfo['history'])) {
            $runInfo['history'] = [];
        }

        // اضافه کردن اجرای فعلی به تاریخچه
        $currentRun = [
            'started_at' => $runInfo['started_at'] ?? date('Y-m-d H:i:s'),
            'stopped_at' => $runInfo['stopped_at'],
            'status' => 'stopped',
            'type' => $runInfo['type'] ?? 'normal'
        ];

        // اضافه کردن به ابتدای آرایه تاریخچه
        array_unshift($runInfo['history'], $currentRun);

        // نگه داشتن فقط 10 اجرای آخر
        $runInfo['history'] = array_slice($runInfo['history'], 0, 10);

        Storage::put($runFilePath, json_encode($runInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // لاگ کردن متوقف شدن
        if (isset($runInfo['log_file'])) {
            $logPath = storage_path('logs/scrapers/' . $runInfo['log_file']);
            if (file_exists($logPath)) {
                $typeText = $scraperType === 'update' ? 'اپدیت' : 'معمولی';
                $stopMessage = "\n[" . PersianDateHelper::now() . "] اسکرپر {$typeText} به صورت دستی متوقف شد.\n";
                file_put_contents($logPath, $stopMessage, FILE_APPEND);
            }
        }
    }
}
