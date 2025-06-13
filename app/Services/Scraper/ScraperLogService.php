<?php

namespace App\Services\Scraper;

use Exception;
use App\Helpers\PersianDateHelper;

/**
 * سرویس مدیریت لاگ‌های اسکرپر
 */
class ScraperLogService
{
    private string $logDirectory;
    private array $logPatterns;

    public function __construct()
    {
        $this->logDirectory = storage_path('logs/scrapers');
        $this->logPatterns = ['scraper*', 'playwright_method3_*', 'playwright_*'];
    }

    /**
     * دریافت لیست فایل‌های لاگ برای کانفیگ خاص
     */
    public function getLogFiles(string $filename): array
    {
        $logFiles = [];

        if (file_exists($this->logDirectory)) {
            $allFiles = scandir($this->logDirectory);

            foreach ($allFiles as $file) {
                if (strpos($file, $filename . '_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $datePart = str_replace($filename . '_', '', pathinfo($file, PATHINFO_FILENAME));

                    $logFiles[] = [
                        'filename' => $file,
                        'date' => $datePart,
                        'full_path' => $this->logDirectory . '/' . $file,
                        'size' => filesize($this->logDirectory . '/' . $file),
                        'last_modified' => filemtime($this->logDirectory . '/' . $file)
                    ];
                }
            }

            // مرتب‌سازی بر اساس آخرین تغییر
            usort($logFiles, function ($a, $b) {
                return $b['last_modified'] - $a['last_modified'];
            });
        }

        return $logFiles;
    }

    /**
     * دریافت محتوای فایل لاگ
     */
    public function getLogContent(string $logfile): string
    {
        $logPath = $this->logDirectory . '/' . $logfile;

        if (!file_exists($logPath)) {
            throw new Exception('فایل لاگ یافت نشد.');
        }

        $content = file_get_contents($logPath);

        // حذف BOM در صورت وجود
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return $content;
    }

    /**
     * حذف همه لاگ‌ها
     */
    public function deleteAllLogs(): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        if (file_exists(storage_path('logs'))) {
            foreach ($this->logPatterns as $pattern) {
                $files = glob(storage_path('logs') . '/' . $pattern . '.log');
                foreach ($files as $file) {
                    if (is_file($file) && unlink($file)) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
        }

        return $this->formatLogDeleteResult($deletedCount, $errorCount);
    }

    /**
     * حذف لاگ‌های مربوط به کانفیگ خاص
     */
    public function deleteConfigLogs(string $configFilename): array
    {
        $deletedCount = 0;
        $errorCount = 0;

        if (file_exists($this->logDirectory)) {
            $allFiles = scandir($this->logDirectory);

            foreach ($allFiles as $file) {
                if (strpos($file, $configFilename . '_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $filePath = $this->logDirectory . '/' . $file;
                    if (is_file($filePath) && unlink($filePath)) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
        }

        return $this->formatLogDeleteResult($deletedCount, $errorCount);
    }

    /**
     * حذف یک فایل لاگ خاص
     */
    public function deleteLog(string $logfile): void
    {
        $logPath = $this->logDirectory . '/' . $logfile;

        if (!file_exists($logPath)) {
            throw new Exception('فایل لاگ یافت نشد.');
        }

        if (!unlink($logPath)) {
            throw new Exception('خطا در حذف فایل لاگ.');
        }
    }

    /**
     * فرمت کردن نتیجه حذف لاگ‌ها
     */
    private function formatLogDeleteResult(int $deletedCount, int $errorCount): array
    {
        if ($deletedCount > 0 && $errorCount == 0) {
            return [
                'type' => 'success',
                'message' => "تعداد {$deletedCount} فایل لاگ با موفقیت حذف شدند."
            ];
        } elseif ($deletedCount > 0 && $errorCount > 0) {
            return [
                'type' => 'warning',
                'message' => "تعداد {$deletedCount} فایل لاگ حذف شدند، اما {$errorCount} فایل حذف نشدند."
            ];
        } elseif ($errorCount > 0) {
            return [
                'type' => 'error',
                'message' => 'خطا در حذف فایل‌های لاگ.'
            ];
        } else {
            return [
                'type' => 'info',
                'message' => 'هیچ فایل لاگ مرتبطی یافت نشد.'
            ];
        }
    }


// اضافه کردن این متدها به کلاس ScraperLogService

    /**
     * دریافت آمار کانفیگ از آخرین لاگ
     */
    public function getConfigStats(string $filename): array
    {
        $logFiles = $this->getLogFiles($filename);

        if (empty($logFiles)) {
            return [
                'total_products' => 0,
                'total_links' => 0,
                'last_run_duration' => null,
                'last_run_date' => null
            ];
        }

        // آخرین فایل لاگ
        $latestLogFile = $logFiles[0];
        $content = $this->getLogContent($latestLogFile['filename']);

        return $this->parseLogStats($content, $latestLogFile['date']);
    }

    /**
     * تجزیه آمار از محتوای لاگ
     */
    private function parseLogStats(string $content, string $date): array
    {
        $stats = [
            'total_products' => 0,
            'total_links' => 0,
            'last_run_duration' => null,
            'last_run_date' => $date
        ];

        $lines = explode("\n", $content);
        $startTime = null;
        $endTime = null;

        foreach ($lines as $line) {
            // استخراج تعداد محصولات
            if (preg_match('/محصول (\d+)/', $line, $matches)) {
                $stats['total_products'] = max($stats['total_products'], (int)$matches[1]);
            }

            // استخراج تعداد لینک‌ها
            if (preg_match('/تعداد لینک.*?(\d+)/', $line, $matches)) {
                $stats['total_links'] += (int)$matches[1];
            }

            // استخراج زمان شروع
            if (preg_match('/شروع اسکرپ/', $line) && preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $startTime = $matches[1];
            }

            // استخراج زمان پایان
            if (preg_match('/پایان اسکرپ|تکمیل شد/', $line) && preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $endTime = $matches[1];
            }
        }

        // محاسبه مدت زمان اجرا
        if ($startTime && $endTime) {
            $start = strtotime($startTime);
            $end = strtotime($endTime);
            $duration = $end - $start;
            $stats['last_run_duration'] = $this->formatDuration($duration);
        }

        return $stats;
    }

    /**
     * فرمت کردن مدت زمان
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d ساعت و %d دقیقه', $hours, $minutes);
        } elseif ($minutes > 0) {
            return sprintf('%d دقیقه و %d ثانیه', $minutes, $seconds);
        } else {
            return sprintf('%d ثانیه', $seconds);
        }
    }
}
