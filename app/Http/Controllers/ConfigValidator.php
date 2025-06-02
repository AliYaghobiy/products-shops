<?php

namespace App\Http\Controllers;

class ConfigValidator
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_YELLOW = "\033[1;93m";

    private $outputCallback = null;

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    public function validateAndFixConfig(array &$config): void
    {
        $isProductTestMode = $config['product_test'] ?? false;
        if ($isProductTestMode) {
            $this->log("🧪 Product Test Mode - Using test-specific validation", self::COLOR_YELLOW);
            $this->validateProductTestConfig($config);
            return;
        }

        $this->log("Validating configuration...", self::COLOR_GREEN);

        // بررسی و اطمینان از وجود run_method
        if (!isset($config['run_method'])) {
            $config['run_method'] = 'new';
            $this->log("run_method was not set in config. Defaulting to 'new'", self::COLOR_YELLOW);
        }

        // تبدیل خودکار run_method به فرمت صحیح string
        $config['run_method'] = (string)$config['run_method'];

        // بررسی صحت مقدار run_method
        if (!in_array($config['run_method'], ['new', 'continue'])) {
            $this->log("WARNING: Invalid run_method '{$config['run_method']}' in config. Must be 'new' or 'continue'. Defaulting to 'new'", self::COLOR_RED);
            $config['run_method'] = 'new';
        }

        // بررسی set_category
        if (isset($config['set_category']) && !empty($config['set_category'])) {
            $this->log("Found set_category in config: '{$config['set_category']}'. Will use this value for all products.", self::COLOR_GREEN);
        }

        $this->log("Config validated. Using run_method: {$config['run_method']}", self::COLOR_GREEN);

        // بررسی وجود کلیدهای مهم دیگر
        if (!isset($config['method'])) {
            $this->log("WARNING: 'method' is not set in config. Defaulting to 1", self::COLOR_YELLOW);
            $config['method'] = 1;
        }

        if (!isset($config['processing_method']) && $config['run_method'] === 'continue') {
            $this->log("WARNING: 'processing_method' is not set for continue mode. Using method {$config['method']} instead", self::COLOR_YELLOW);
            $config['processing_method'] = $config['method'];
        }
    }

    public function validateConfig(array $config): void
    {
        $isProductTestMode = $config['product_test'] ?? false;
        if ($isProductTestMode) {
            $this->log("🧪 Product Test Mode - Using test-specific validation", self::COLOR_YELLOW);
            $this->validateProductTestConfig($config);
            return;
        }

        $requiredFields = [
            'base_urls' => 'Base URLs are required.',
            'products_urls' => 'Products URLs are required.',
            'method' => 'Scraping method is required (1, 2, or 3).',
            'selectors' => 'Selectors configuration is required.',
            'out_of_stock_button' => 'Out of stock button configuration is required.',
        ];

        foreach ($requiredFields as $field => $message) {
            if ($field === 'out_of_stock_button') {
                if (!isset($config[$field])) {
                    throw new \Exception("Validation Error: $message");
                }
            } else {
                if (empty($config[$field])) {
                    throw new \Exception("Validation Error: $message");
                }
            }
        }

        if (!is_array($config['base_urls']) || count($config['base_urls']) < 1) {
            throw new \Exception("Validation Error: At least one base_url is required.");
        }
        if (!is_array($config['products_urls']) || count($config['products_urls']) < 1) {
            throw new \Exception("Validation Error: At least one products_url is required.");
        }

        // بررسی set_category اگر وجود داشت
        if (isset($config['set_category'])) {
            if (!is_string($config['set_category']) || empty(trim($config['set_category']))) {
                throw new \Exception("Validation Error: set_category must be a non-empty string.");
            }
        }

        // اعتبارسنجی متدها
        if (!in_array($config['method'], [1, 2, 3])) {
            throw new \Exception('Validation Error: Invalid method value. Must be 1, 2, or 3.');
        }

        if (isset($config['processing_method']) && !in_array($config['processing_method'], [1, 2, 3])) {
            throw new \Exception('Validation Error: Invalid processing_method value. Must be 1, 2, or 3.');
        }

        // سایر اعتبارسنجی‌ها...
        $this->validateMethodSpecificConfig($config);
        $this->validateSelectors($config);
        $this->validateOutOfStockConfig($config);
        $this->validateTitlePrefixRules($config);

        $this->log('Configuration validated successfully.', self::COLOR_GREEN);
    }

    public function validateProductTestConfig(array $config): void
    {
        $this->log("Validating Product Test Mode configuration...", self::COLOR_GREEN);

        if (!isset($config['product_urls']) || empty($config['product_urls'])) {
            throw new \InvalidArgumentException("product_urls is required for Product Test Mode");
        }

        if (!is_array($config['product_urls'])) {
            throw new \InvalidArgumentException("product_urls must be an array");
        }

        foreach ($config['product_urls'] as $index => $url) {
            if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Invalid URL at index $index: $url");
            }
        }

        if (!isset($config['selectors']['product_page'])) {
            throw new \InvalidArgumentException("product_page selectors are required for Product Test Mode");
        }

        $requiredSelectors = ['title', 'price'];
        $productPageSelectors = $config['selectors']['product_page'];

        foreach ($requiredSelectors as $selector) {
            if (!isset($productPageSelectors[$selector]) || empty($productPageSelectors[$selector]['selector'])) {
                throw new \InvalidArgumentException("Required selector '$selector' is missing or empty in product_page");
            }
        }

        $this->log("✅ Product Test Mode configuration is valid", self::COLOR_GREEN);
        $this->log("📝 Testing " . count($config['product_urls']) . " product URLs", self::COLOR_GREEN);
    }

    private function validateMethodSpecificConfig(array $config): void
    {
        if ($config['method'] === 2) {
            if (!isset($config['method_settings']['method_2']['navigation']['pagination']['method'])) {
                throw new \Exception("Validation Error: 'method' is required in method_2.navigation.pagination.");
            }
            $paginationMethod = $config['method_settings']['method_2']['navigation']['pagination']['method'];
            if (!in_array($paginationMethod, ['url', 'next_button'])) {
                throw new \Exception("Validation Error: 'method' in method_2.navigation.pagination must be 'url' or 'next_button'.");
            }

            if ($paginationMethod === 'next_button') {
                if (empty($config['method_settings']['method_2']['navigation']['pagination']['next_button']['selector'])) {
                    throw new \Exception("Validation Error: 'next_button.selector' is required when pagination method is 'next_button'.");
                }
            } elseif ($paginationMethod === 'url') {
                $urlConfig = $config['method_settings']['method_2']['navigation']['pagination']['url'] ?? [];
                if ($urlConfig['use_sample_url'] && empty($urlConfig['sample_url'])) {
                    throw new \Exception("Validation Error: 'sample_url' is required when 'use_sample_url' is true in method_2.navigation.pagination.url.");
                }
            }
        }

        if (isset($config['processing_method']) && $config['processing_method'] === 3) {
            if (!$config['method_settings']['method_3']['enabled']) {
                throw new \Exception("Validation Error: Method 3 must be enabled when processing_method is set to 3.");
            }
            if (!$config['method_settings']['method_3']['navigation']['use_webdriver']) {
                throw new \Exception("Validation Error: Method 3 requires a WebDriver (use_webdriver must be true) when processing_method is set to 3.");
            }
        }
    }

    private function validateSelectors(array $config): void
    {
        if (!isset($config['selectors']['main_page']) || !isset($config['selectors']['product_page'])) {
            throw new \Exception("Validation Error: Both 'main_page' and 'product_page' selectors are required.");
        }

        if (isset($config['selectors']['main_page']['product_links']['product_id'])) {
            $productIdAttr = $config['selectors']['main_page']['product_links']['product_id'];
            if (empty($productIdAttr)) {
                throw new \Exception("Validation Error: 'product_id' attribute in product_links cannot be empty.");
            }
        }
    }

    private function validateOutOfStockConfig(array $config): void
    {
        if (!is_bool($config['out_of_stock_button'])) {
            throw new \Exception("Validation Error: 'out_of_stock_button' must be a boolean value (true or false).");
        }

        if ($config['out_of_stock_button'] === true) {
            if (!isset($config['selectors']['product_page']['out_of_stock']) ||
                empty($config['selectors']['product_page']['out_of_stock']['selector'])) {
                throw new \Exception("Validation Error: 'selectors.product_page.out_of_stock' must be defined with a non-empty 'selector' when 'out_of_stock_button' is true.");
            }
            $selector = $config['selectors']['product_page']['out_of_stock']['selector'];
            if (!is_string($selector) && !is_array($selector)) {
                throw new \Exception("Validation Error: 'selectors.product_page.out_of_stock.selector' must be a string or array.");
            }
            if (is_array($selector) && empty($selector)) {
                throw new \Exception("Validation Error: 'selectors.product_page.out_of_stock.selector' array cannot be empty.");
            }
            if (!isset($config['selectors']['product_page']['out_of_stock']['type']) ||
                !in_array($config['selectors']['product_page']['out_of_stock']['type'], ['css', 'xpath'])) {
                throw new \Exception("Validation Error: 'selectors.product_page.out_of_stock.type' must be 'css' or 'xpath'.");
            }
        }
    }

    private function validateTitlePrefixRules(array $config): void
    {
        if (isset($config['title_prefix_rules'])) {
            if (!is_array($config['title_prefix_rules'])) {
                throw new \Exception("Validation Error: 'title_prefix_rules' must be an array.");
            }
            $productsUrls = $config['products_urls'] ?? [];
            foreach ($config['title_prefix_rules'] as $url => $rule) {
                if (!is_string($url) || empty($url)) {
                    throw new \Exception("Validation Error: Each key in 'title_prefix_rules' must be a valid non-empty URL string.");
                }
                if (!in_array($url, $productsUrls)) {
                    throw new \Exception("Validation Error: URL '$url' in 'title_prefix_rules' must match one of the 'products_urls'.");
                }
                if (!isset($rule['prefix']) || !is_string($rule['prefix']) || empty($rule['prefix'])) {
                    throw new \Exception("Validation Error: 'prefix' in 'title_prefix_rules' for URL '$url' is required and must be a non-empty string.");
                }
            }
        }
    }

    private function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $formattedMessage);
        } else {
            echo $formattedMessage . PHP_EOL;
        }
    }
}
