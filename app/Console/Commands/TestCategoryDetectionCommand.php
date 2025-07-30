<?php

namespace App\Console\Commands;

use App\Services\CategoryDetectionService;
use Illuminate\Console\Command;

class TestCategoryDetectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:category-detection
                            {text : متن ورودی برای تست تشخیص دسته‌بندی}
                            {--detailed : نمایش جزئیات کامل تطابق}
                            {--json : خروجی در فرمت JSON}
                            {--multiple : امکان تست چندین متن به‌صورت همزمان}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تست دقیق الگوریتم تشخیص دسته‌بندی بر اساس کلمات';

    private CategoryDetectionService $categoryDetectionService;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->categoryDetectionService = app(CategoryDetectionService::class);

        // تنظیم callback برای نمایش لاگ‌ها
        $this->categoryDetectionService->setOutputCallback(function ($message) {
            $this->line($message);
        });

        $inputText = $this->argument('text');
        $isDetailed = $this->option('detailed');
        $isJson = $this->option('json');
        $isMultiple = $this->option('multiple');

        $this->info("🚀 شروع تست تشخیص دسته‌بندی...\n");

        if ($isMultiple) {
            return $this->handleMultipleTests($inputText, $isDetailed, $isJson);
        }

        return $this->handleSingleTest($inputText, $isDetailed, $isJson);
    }

    /**
     * انجام تست تکی
     */
    private function handleSingleTest(string $text, bool $detailed, bool $json): int
    {
        $this->info("📝 متن ورودی: " . $text);
        $this->newLine();

        // تست کامل با جزئیات
        $testResult = $this->categoryDetectionService->testCategoryDetection($text);

        if ($json) {
            $this->line(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->displayTestResults($testResult, $detailed);

        // تست روش اصلی
        $this->info("\n" . str_repeat("=", 60));
        $this->info("🎯 نتیجه نهایی روش اصلی:");
        $this->info(str_repeat("=", 60));

        $finalResult = $this->categoryDetectionService->detectCategoriesFromText($text);

        if ($finalResult) {
            $this->info("✅ دسته‌بندی‌های تشخیص داده شده:");
            foreach ($finalResult as $index => $category) {
                $this->line("   " . ($index + 1) . ". " . $category);
            }
        } else {
            $this->warn("❌ هیچ دسته‌بندی تشخیص داده نشد");
        }

        return Command::SUCCESS;
    }

    /**
     * انجام تست چندگانه
     */
    private function handleMultipleTests(string $textsInput, bool $detailed, bool $json): int
    {
        $texts = array_filter(array_map('trim', explode('|', $textsInput)));

        if (empty($texts)) {
            $this->error("❌ متن‌های ورودی نامعتبر. از | برای جداسازی استفاده کنید.");
            return Command::FAILURE;
        }

        $allResults = [];

        foreach ($texts as $index => $text) {
            $this->info("\n" . str_repeat("=", 80));
            $this->info("🔍 تست شماره " . ($index + 1) . ": " . $text);
            $this->info(str_repeat("=", 80));

            $testResult = $this->categoryDetectionService->testCategoryDetection($text);
            $allResults[] = $testResult;

            if (!$json) {
                $this->displayTestResults($testResult, $detailed);

                // نتیجه نهایی
                $finalResult = $this->categoryDetectionService->detectCategoriesFromText($text);

                if ($finalResult) {
                    $this->info("✅ نتیجه نهایی: " . implode(', ', $finalResult));
                } else {
                    $this->warn("❌ نتیجه نهایی: هیچ دسته‌بندی یافت نشد");
                }
            }
        }

        if ($json) {
            $this->line(json_encode($allResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return Command::SUCCESS;
    }

    /**
     * نمایش نتایج تست
     */
    private function displayTestResults(array $testResult, bool $detailed): void
    {
        // اطلاعات پایه
        $this->info("📊 گزارش تحلیل:");
        $this->line("   🔤 کلمات استخراج شده: " . implode(', ', $testResult['extracted_words']));
        $this->line("   ⏱️  زمان پردازش: " . $testResult['processing_time'] . " میلی‌ثانیه");
        $this->line("   🗄️  وضعیت دیتابیس: " . ($testResult['database_available'] ? 'متصل' : 'قطع'));

        if (isset($testResult['error'])) {
            $this->error("❌ خطا: " . $testResult['error']);
            return;
        }

        // تطابق‌های دقیق
        if (!empty($testResult['exact_matches'])) {
            $this->info("\n🎯 تطابق‌های دقیق:");
            foreach ($testResult['exact_matches'] as $match) {
                $this->line("   ✅ '{$match['input']}' → '{$match['matched_category']}' (اطمینان: 100%)");
            }
        }

        // تطابق‌های کلمه‌ای
        if (!empty($testResult['word_based_matches'])) {
            $this->info("\n🔍 تطابق‌های کلمه‌ای:");
            foreach ($testResult['word_based_matches'] as $match) {
                $this->line("   ⭐ '{$match['input']}' → '{$match['matched_category']}' (امتیاز: {$match['score']})");

                if ($detailed && !empty($match['match_details'])) {
                    foreach ($match['match_details'] as $detail) {
                        $ratio = round($detail['match_ratio'] * 100, 1);
                        $words = implode(', ', $detail['matched_words']);
                        $this->line("      📍 فیلد {$detail['field']}: '{$detail['value']}' - کلمات مطابق: [{$words}] ({$ratio}%)");
                    }
                }
            }
        }

        // خلاصه نتیجه
        if (!empty($testResult['detected_categories'])) {
            $this->info("\n🏆 خلاصه دسته‌بندی‌های تشخیص داده شده:");
            foreach ($testResult['detected_categories'] as $index => $category) {
                $this->line("   " . ($index + 1) . ". " . $category);
            }
        } else {
            $this->warn("\n❌ هیچ دسته‌بندی تشخیص داده نشد");
        }
    }
}
