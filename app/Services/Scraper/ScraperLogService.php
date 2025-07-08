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
    private int $maxFileSize;
    private int $maxLines;

    public function __construct()
    {
        $this->logDirectory = storage_path('logs/scrapers');
        $this->logPatterns = ['scraper*', 'playwright_method3_*', 'playwright_*'];
        $this->maxFileSize = 50 * 1024 * 1024; // 50MB
        $this->maxLines = 10000; // حداکثر 10 هزار خط
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
                    $filePath = $this->logDirectory . '/' . $file;
                    $fileSize = filesize($filePath);

                    $logFiles[] = [
                        'filename' => $file,
                        'date' => $datePart,
                        'full_path' => $filePath,
                        'size' => $fileSize,
                        'size_formatted' => $this->formatFileSize($fileSize),
                        'last_modified' => filemtime($filePath),
                        'is_large' => $fileSize > $this->maxFileSize
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
     * دریافت محتوای فایل لاگ با مدیریت حافظه
     */
    public function getLogContent(string $logfile): string
    {
        $logPath = $this->logDirectory . '/' . $logfile;

        if (!file_exists($logPath)) {
            throw new Exception('فایل لاگ یافت نشد.');
        }

        $fileSize = filesize($logPath);
        
        // بررسی اندازه فایل
        if ($fileSize > $this->maxFileSize) {
            return $this->getLogContentSafely($logPath, $fileSize);
        }

        // برای فایل‌های کوچک از روش معمولی استفاده کنید
        $content = file_get_contents($logPath);

        // حذف BOM در صورت وجود
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return $content;
    }

    /**
     * دریافت ایمن محتوای فایل‌های بزرگ
     */
    private function getLogContentSafely(string $logPath, int $fileSize): string
    {
        $handle = fopen($logPath, 'r');
        if (!$handle) {
            throw new Exception('خطا در باز کردن فایل لاگ.');
        }

        $content = '';
        $lineCount = 0;
        $totalLines = 0;
        
        // شمارش کل خطوط
        while (!feof($handle)) {
            fgets($handle);
            $totalLines++;
        }
        
        // بازگشت به ابتدای فایل
        rewind($handle);

        // اگر خطوط زیاد است، فقط آخرین خطوط را بخوانید
        if ($totalLines > $this->maxLines) {
            $skipLines = $totalLines - $this->maxLines;
            
            // پرش به خطوط مورد نظر
            for ($i = 0; $i < $skipLines; $i++) {
                fgets($handle);
            }
            
            $content = "⚠️ فایل لاگ بزرگ است - نمایش آخرین {$this->maxLines} خط از کل {$totalLines} خط\n";
            $content .= str_repeat("=", 80) . "\n\n";
        }

        // خواندن خطوط مورد نظر
        while (!feof($handle) && $lineCount < $this->maxLines) {
            $line = fgets($handle);
            if ($line !== false) {
                $content .= $line;
                $lineCount++;
            }
        }

        fclose($handle);

        // حذف BOM در صورت وجود
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        return $content;
    }

    /**
     * دریافت آمار کانفیگ از آخرین لاگ با مدیریت حافظه
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
        
        // برای آمار، فقط آخرین قسمت فایل را بخوانید
        $content = $this->getLogContentForStats($latestLogFile['full_path']);

        return $this->parseLogStats($content, $latestLogFile['date']);
    }

    /**
     * خواندن محتوای لاگ برای آمار (فقط آخرین قسمت)
     */
    private function getLogContentForStats(string $logPath): string
    {
        if (!file_exists($logPath)) {
            return '';
        }

        $fileSize = filesize($logPath);
        
        // برای فایل‌های کوچک
        if ($fileSize < $this->maxFileSize / 10) {
            return file_get_contents($logPath);
        }

        // برای فایل‌های بزرگ، فقط آخرین 1000 خط
        $handle = fopen($logPath, 'r');
        if (!$handle) {
            return '';
        }

        $lines = [];
        $maxStatsLines = 1000;

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $lines[] = $line;
                
                // نگه داشتن فقط آخرین خطوط
                if (count($lines) > $maxStatsLines) {
                    array_shift($lines);
                }
            }
        }

        fclose($handle);
        return implode('', $lines);
    }

    /**
     * فرمت کردن اندازه فایل
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
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
