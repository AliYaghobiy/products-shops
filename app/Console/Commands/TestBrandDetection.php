<?php

namespace App\Console\Commands;

use App\Services\BrandDetectionService;
use Illuminate\Console\Command;

class TestBrandDetection extends Command
{
    protected $signature = 'brand:test {text}';
    protected $description = 'Test brand detection with given text';

    public function handle()
    {
        $text = $this->argument('text');

        $brandService = new BrandDetectionService();
        $brandService->setOutputCallback(function ($message) {
            $this->line($message);
        });

        $this->info("🚀 Testing brand detection for: $text");
        $this->line(str_repeat('=', 60));

        $result = $brandService->testBrandDetection($text);

        $this->info("📊 Test Results:");
        $this->line("Input: " . $result['input_text']);
        $this->line("Normalized: " . $result['normalized_text']);
        $this->line("Database Available: " . ($result['database_available'] ? 'Yes' : 'No'));
        $this->line("Brand Connection: " . ($result['brand_connection'] ?? 'N/A'));
        $this->line("Processing Time: " . $result['processing_time'] . 'ms');

        if (isset($result['error'])) {
            $this->error("Error: " . $result['error']);
            return;
        }

        if ($result['detected_brand']) {
            $this->info("✅ Detected Brand: " . $result['detected_brand']);
        } else {
            $this->warn("❌ No brand detected");
        }

        $this->line("\n📈 Top 5 Scores:");
        $topScores = array_slice($result['detailed_scores'], 0, 5);

        foreach ($topScores as $i => $score) {
            $mark = $i === 0 && $score['score'] >= 0.3 ? '🏆' : '📊';
            $this->line(sprintf(
                "%s %d. %s: %.4f",
                $mark,
                $i + 1,
                $score['brand_name'],
                $score['score']
            ));
        }

        // تست مستقیم هم بکن
        $detectedBrand = $brandService->detectBrandFromText($text);
        $this->line("\n🔍 Direct Detection Result: " . ($detectedBrand ?? 'None'));
    }
}
