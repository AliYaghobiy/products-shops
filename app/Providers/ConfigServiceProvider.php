<?php

namespace App\Providers;

use App\Services\Config\ConfigFileService;
use App\Services\Config\ConfigValidationService;
use App\Services\Config\ConfigBuilderService;
use App\Services\Scraper\ScraperExecutionService;
use App\Services\Scraper\ScraperLogService;
use App\Services\Scraper\ScraperProcessService;
use Illuminate\Support\ServiceProvider;

/**
 * سرویس پروایدر کانفیگ برای تزریق وابستگی‌ها
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * ثبت سرویس‌ها در کانتینر
     */
    public function register(): void
    {
        // ثبت سرویس‌های کانفیگ
        $this->app->singleton(ConfigFileService::class, function ($app) {
            return new ConfigFileService();
        });

        $this->app->singleton(ConfigValidationService::class, function ($app) {
            return new ConfigValidationService();
        });

        $this->app->singleton(ConfigBuilderService::class, function ($app) {
            return new ConfigBuilderService();
        });

        // ثبت سرویس‌های اسکرپر
        $this->app->singleton(ScraperProcessService::class, function ($app) {
            return new ScraperProcessService();
        });

        $this->app->singleton(ScraperLogService::class, function ($app) {
            return new ScraperLogService();
        });

        $this->app->singleton(ScraperExecutionService::class, function ($app) {
            return new ScraperExecutionService($app->make(ScraperProcessService::class));
        });
    }

    /**
     * راه‌اندازی سرویس‌ها
     */
    public function boot(): void
    {
        // در صورت نیاز به راه‌اندازی خاص
    }

    /**
     * لیست سرویس‌هایی که این پروایدر ارائه می‌دهد
     */
    public function provides(): array
    {
        return [
            ConfigFileService::class,
            ConfigValidationService::class,
            ConfigBuilderService::class,
            ScraperExecutionService::class,
            ScraperLogService::class,
            ScraperProcessService::class,
        ];
    }
}
