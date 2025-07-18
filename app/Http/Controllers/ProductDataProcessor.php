<?php

namespace App\Http\Controllers;

use App\Models\FailedLink;
use App\Models\Product;
use Symfony\Component\DomCrawler\Crawler;
use App\Services\BrandDetectionService;

class ProductDataProcessor
{
    private const COLOR_GREEN = "\033[1;92m";
    private const COLOR_RED = "\033[1;91m";
    private const COLOR_YELLOW = "\033[1;93m";
    private const COLOR_BLUE = "\033[1;94m";
    private const COLOR_PURPLE = "\033[1;95m";
    private const COLOR_CYAN = "\033[1;36m";
    private const COLOR_GRAY = "\033[1;90m";

    private BrandDetectionService $brandDetectionService;
    private array $config;
    private $outputCallback = null;

    public function __construct(array $config)
    {
        $this->config = $config;

        // ایجاد سرویس تشخیص برند و تنظیم callback
        $this->brandDetectionService = new BrandDetectionService();
        $this->brandDetectionService->setOutputCallback([$this, 'log']);
    }

    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;

        // اطمینان از تنظیم callback برای BrandDetectionService نیز
        if (isset($this->brandDetectionService)) {
            $this->brandDetectionService->setOutputCallback([$this, 'log']);
        }
    }

    private function fixCorruptedText(string $text): string
    {
        if (empty($text)) {
            return $text;
        }

        // تشخیص متن خراب شده - الگوهای بیشتر
        if (strpos($text, 'Ø') !== false || strpos($text, 'Û') !== false ||
            strpos($text, 'Ù') !== false || strpos($text, 'Ú') !== false ||
            strpos($text, 'ÃÂ') !== false || preg_match('/Ù[Ø-Û]/', $text)) {

            // روش‌های مختلف تصحیح
            $methods = [
                // روش 1: فقط utf8_decode
                function($t) { return @utf8_decode($t); },

                // روش 2: دوبار decode
                function($t) { return @utf8_decode(utf8_decode($t)); },

                // روش 3: با iconv
                function($t) { return @iconv('UTF-8', 'ISO-8859-1//IGNORE', $t); },

                // روش 4: با mb_convert
                function($t) { return @mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8'); },

                // روش 5: برای دسته‌بندی خاص
                function($t) { return @iconv('ISO-8859-1', 'UTF-8', utf8_decode($t)); },
            ];

            foreach ($methods as $method) {
                $result = $method($text);
                if ($result && $this->isPersianText($result)) {
                    $this->log("🔧 Fixed corrupted text: '{$text}' → '{$result}'", self::COLOR_PURPLE);
                    return $result;
                }
            }
        }

        return $text;
    }

    /**
     * بررسی اینکه آیا متن فارسی معتبر است
     */
    private function isPersianText(string $text): bool
    {
        // اگر شامل حروف فارسی باشد و شامل کاراکترهای خراب نباشد
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) &&
            strpos($text, 'Ø') === false &&
            strpos($text, 'ÃÂ') === false;
    }

    private function filterUnwantedCategories(string $category): string
    {
        $unwantedCategories = [
            'دسته بندی نشده',
            'بدون دسته بندی',
            'بدون دستهبندی'
        ];

        // تبدیل به حروف کوچک برای مقایسه دقیق‌تر
        $categoryLower = mb_strtolower(trim($category));

        foreach ($unwantedCategories as $unwanted) {
            if ($categoryLower === mb_strtolower($unwanted)) {
                $this->log("Category '$category' filtered out as unwanted", self::COLOR_YELLOW);
                return ''; // برگردان رشته خالی
            }
        }

        return $category; // اگر مشکلی نبود همون دسته‌بندی رو برگردان
    }

    // تغییرات در متد extractProductData
    public function extractProductData(string $url, ?string $body = null, ?string $mainPageImage = null, ?string $mainPageProductId = null): ?array
    {
        $data = [
            'title' => '',
            'price' => $this->config['keep_price_format'] ?? false ? '' : '0',
            'product_id' => $mainPageProductId ?? '',
            'page_url' => $url,
            'availability' => null,
            'image' => $mainPageImage ?? '',
            'category' => '',
            'off' => 0,
            'guarantee' => '',
            'brand' => '',
            'description' => ''
        ];

        if ($body === null) {
            $this->log("Body content is required for product data extraction", self::COLOR_RED);
            $this->saveFailedLink($url, "No body content provided");
            return null;
        }

        if (empty($data['product_id']) && ($this->config['product_id_method'] ?? 'selector') === 'url') {
            $data['product_id'] = $this->extractProductIdFromUrl($url, '', new Crawler());
            $this->log("Extracted product_id: \"{$data['product_id']}\" for $url", self::COLOR_GREEN);
        }

        $crawler = new Crawler($body);
        $productSelectors = $this->config['selectors']['product_page'] ?? [];

        if (isset($this->config['set_category']) && !empty($this->config['set_category'])) {
            $data['category'] = $this->filterUnwantedCategories($this->config['set_category']);
            $this->log("Using preset category from config: {$data['category']}", self::COLOR_GREEN);
        }

        foreach ($productSelectors as $field => $selector) {
            if (!empty($selector['selector']) && array_key_exists($field, $data)) {
                $value = $this->extractData($crawler, $selector);
                $this->log("Raw $field extracted: '$value'", self::COLOR_YELLOW);

                if ($field === 'title') {
                    $cleanTitle = $this->fixCorruptedText($value);
                    $data[$field] = $this->applyTitlePrefix($cleanTitle, $url);
                }
                elseif ($field === 'price') {
                    $rawPrice = $this->extractPriceWithPriority($crawler, $selector);

                    if ($this->config['keep_price_format'] ?? false) {
                        $data[$field] = $this->cleanPriceWithFormat($rawPrice);
                    } else {
                        $data[$field] = (string)$this->cleanPrice($rawPrice);
                    }

                    if (empty($data[$field]) && !($this->config['keep_price_format'] ?? false)) {
                        $data[$field] = '0';
                    }
                }
                elseif ($field === 'description') {
                    $data[$field] = $this->processDescriptionField($crawler, $selector);
                }
                elseif ($field === 'availability') {
                    $outOfStockButton = $this->config['out_of_stock_button'] ?? false;
                    $outOfStockSelector = $this->config['selectors']['product_page']['out_of_stock'] ?? null;

                    if ($outOfStockButton && $outOfStockSelector) {
                        $this->log("Checking out_of_stock selector first due to out_of_stock_button=true", self::COLOR_CYAN);

                        $outOfStockResult = $this->checkOutOfStockWithPriority($crawler, $outOfStockSelector);
                        if ($outOfStockResult === 0) {
                            $this->log("Product marked as unavailable due to out_of_stock selector", self::COLOR_RED);
                            $data[$field] = 0;
                            continue;
                        }
                    }

                    $transform = $this->config['data_transformers'][$field] ?? null;
                    $data[$field] = $transform && method_exists($this, $transform) ? (int)$this->$transform($value, $crawler) : (!empty($value) ? 1 : 0);

                } elseif ($field === 'off') {
                    $transform = $this->config['data_transformers'][$field] ?? null;
                    $data[$field] = $transform && method_exists($this, $transform) ? $this->$transform($value) : (preg_match('/\d+/', $value, $matches) ? (int)$matches[0] : 0);
                } elseif ($field === 'guarantee') {
                    $data[$field] = $this->extractGuaranteeFromSelector($crawler, $selector, $data['title']);
                } elseif ($field === 'image') {
                    // **تغییر اصلی در اینجا**
                    $data[$field] = $this->processImageField($crawler, $selector);
                } elseif ($field === 'category' && ($this->config['category_method'] ?? 'selector') === 'selector' && !isset($this->config['set_category'])) {
                    $extractedCategory = $this->extractCategoriesFromSelectors($crawler, $selector);
                    $cleanCategory = $this->fixCorruptedText($extractedCategory);
                    $data[$field] = $this->filterUnwantedCategories($cleanCategory);
                } elseif ($field === 'brand') {
                    $data[$field] = $this->processBrandField($crawler, $selector, $data['title']);
                } else {
                    $transform = $this->config['data_transformers'][$field] ?? null;
                    $data[$field] = $transform && method_exists($this, $transform) ? (string)$this->$transform($value) : (string)$value;
                }

                $this->log("Extracted $field: \"{$data[$field]}\" for $url", self::COLOR_GREEN);
            }
        }

        // تشخیص برند از عنوان محصول (اگر هنوز پیدا نشده)
        if (empty($data['brand']) && !empty($data['title'])) {
            $this->log("🔍 No brand found in selectors, attempting to detect from title", self::COLOR_BLUE);
            $detectedBrand = $this->detectBrandFromTitle($data['title']);
            if ($detectedBrand) {
                $data['brand'] = $detectedBrand;
                $this->log("✅ Brand detected from title: {$detectedBrand}", self::COLOR_GREEN);
            } else {
                $this->log("❌ No brand detected from title", self::COLOR_YELLOW);
            }
        }

        if (!isset($this->config['set_category']) && ($this->config['category_method'] ?? 'selector') === 'title' && !empty($data['title'])) {
            $wordCount = $this->config['category_word_count'] ?? 1;
            $extractedCategory = $this->extractCategoryFromTitle($data['title'], $wordCount);
            $cleanCategory = $this->fixCorruptedText($extractedCategory);
            $data['category'] = $this->filterUnwantedCategories($cleanCategory);
        }

        // اگر availability هنوز null است، fallback را اجرا کن
        if ($data['availability'] === null) {
            $data['availability'] = $this->processAvailabilityFallback($crawler, $data);
        }

        $data['availability'] = (int)$data['availability'];

        foreach ($data as $key => $value) {
            if ($key !== 'availability' && $key !== 'off' && is_numeric($value)) {
                $data[$key] = (string)$value;
            }
        }

        if (!$this->validateProductData($data)) {
            $this->log("No valid data extracted for $url. Adding to failed links.", self::COLOR_RED);
            $this->saveFailedLink($url, "No valid data extracted");
            return null;
        }

        // تصحیح نهایی category فقط
        $data['category'] = $this->fixCorruptedText($data['category']);
        return $data;
    }

    public function detectBrandFromTitle(string $title): string
    {
        if (empty($title)) {
            return '';
        }

        $this->log("🔍 Attempting to detect brand from title: " . substr($title, 0, 50) . "...", self::COLOR_BLUE);

        $detectedBrand = $this->brandDetectionService->detectBrandFromText($title);

        if ($detectedBrand) {
            $this->log("✅ Brand detected from title: $detectedBrand", self::COLOR_GREEN);
            return $detectedBrand;
        } else {
            $this->log("❌ No brand detected from title", self::COLOR_YELLOW);
            return '';
        }
    }
    private function processImageField(Crawler $crawler, array $selector): string
    {
        $images = [];
        $selectors = is_array($selector['selector']) ? $selector['selector'] : [$selector['selector']];
        $attributes = isset($selector['attribute'])
            ? (is_array($selector['attribute']) ? $selector['attribute'] : [$selector['attribute']])
            : ['src']; // پیش‌فرض src

        foreach ($selectors as $index => $selectorString) {
            if (empty($selectorString)) {
                continue;
            }

            try {
                $elements = $selector['type'] === 'css'
                    ? $crawler->filter($selectorString)
                    : $crawler->filterXPath($selectorString);

                if ($elements->count() > 0) {
                    $currentAttribute = $attributes[$index] ?? $attributes[0] ?? 'src';

                    $elements->each(function (Crawler $element) use (&$images, $currentAttribute) {
                        $imageUrl = $element->attr($currentAttribute) ?? '';

                        if (!empty($imageUrl)) {
                            // تبدیل به URL مطلق
                            $absoluteUrl = $this->makeAbsoluteUrl($imageUrl);

                            if (!empty($absoluteUrl) && !in_array($absoluteUrl, $images)) {
                                $images[] = $absoluteUrl;
                            }
                        }
                    });
                }
            } catch (\Exception $e) {
                $this->log("خطا در استخراج تصویر از سلکتور '$selectorString': " . $e->getMessage(), self::COLOR_RED);
            }
        }

        // حذف تصاویر تکراری و خالی
        $images = array_filter(array_unique($images), function ($img) {
            return !empty(trim($img));
        });

        $result = implode(',', $images);

        if (!empty($result)) {
            $this->log("تصاویر استخراج شده: " . count($images) . " عدد", self::COLOR_GREEN);
        }

        return $result;
    }
    private function processDescriptionField(Crawler $crawler, array $selector): string
    {
        $descriptions = [];
        $selectors = is_array($selector['selector']) ? $selector['selector'] : [$selector['selector']];

        foreach ($selectors as $index => $selectorString) {
            if (empty($selectorString)) {
                continue;
            }

            try {
                $elements = $selector['type'] === 'css'
                    ? $crawler->filter($selectorString)
                    : $crawler->filterXPath($selectorString);

                if ($elements->count() > 0) {
                    $elements->each(function (Crawler $element) use (&$descriptions, $selector) {
                        $descriptionText = $selector['attribute'] ?? false
                            ? ($element->attr($selector['attribute']) ?? '')
                            : trim($element->text());

                        if (!empty($descriptionText)) {
                            // تصحیح متن خراب شده
                            $descriptionText = $this->fixCorruptedText($descriptionText);
                            // پاکسازی HTML tags اضافی
                            $descriptionText = $this->cleanDescriptionText($descriptionText);
                            if (!empty($descriptionText)) {
                                $descriptions[] = $descriptionText;
                            }
                        }
                    });
                }
            } catch (\Exception $e) {
                $this->log("Error extracting description from selector '$selectorString': " . $e->getMessage(), self::COLOR_RED);
            }
        }

        // حذف توضیحات تکراری و ترکیب آنها
        $descriptions = array_filter(array_unique($descriptions), function ($desc) {
            return !empty(trim($desc));
        });

        return implode(' ', $descriptions);
    }

// 3. متد جدید برای پاکسازی متن توضیحات
    private function cleanDescriptionText(string $text): string
    {
        // حذف HTML tags
        $text = strip_tags($text);

        // حذف کاراکترهای اضافی
        $text = preg_replace('/\s+/', ' ', $text);

        // حذف فاصله‌های اضافی از ابتدا و انتها
        $text = trim($text);

        // حذف کاراکترهای کنترلی
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);

        return $text;
    }

    private function processBrandField(Crawler $crawler, array $selector, ?string $title = null): string
    {
        $brandMethod = $this->config['brand_method'] ?? 'selector';

        if ($brandMethod === 'selector' && !empty($selector['selector'])) {
            // استخراج برند از سلکتور
            $elements = $this->getElements($crawler, $selector);
            if ($elements->count() > 0) {
                $brandText = trim($elements->text());
                if (!empty($brandText)) {
                    // تشخیص برند از متن استخراج شده
                    $detectedBrand = $this->brandDetectionService->detectBrandFromText($brandText);
                    if ($detectedBrand) {
                        $this->log("🏷️ Brand detected from selector: $detectedBrand", self::COLOR_GREEN);
                        return $detectedBrand;
                    }
                    $this->log("⚠️ No brand matched from selector text: $brandText", self::COLOR_YELLOW);
                }
            }
        } elseif ($brandMethod === 'title' && $title) {
            // استخراج برند از عنوان محصول
            return $this->detectBrandFromTitle($title);
        }

        return '';
    }

    // باقی متدها بدون تغییر...
    public function validateProductData(array $productData): bool
    {
        if (empty($productData['title'])) {
            $this->log("Validation failed: title is empty for URL: {$productData['page_url']}", self::COLOR_RED);
            return false;
        }

        if ($productData['availability'] == 0) {
            return true;
        }
        if (!empty($productData['price'])) {
            $cleanedPrice = str_replace([',', ' ', 'تومان', 'ریال'], '', $productData['price']);
            if (!is_numeric($cleanedPrice)) {
                $this->log("Validation failed: price '$cleanedPrice' is not numeric for URL: {$productData['page_url']}", self::COLOR_RED);
                return false;
            }
        } else {
            $this->log("Warning: price is empty for available product, but product will be saved for URL: {$productData['page_url']}", self::COLOR_YELLOW);
            return true;
        }

        if (empty($productData['price']) || $productData['price'] === '0') {
            $this->log("Warning: price is empty for available product, but product will be saved for URL: {$productData['page_url']}", self::COLOR_YELLOW);
            return true;
        }

        $this->log("Product data validated successfully for URL: {$productData['page_url']}", self::COLOR_GREEN);
        return true;
    }

    public function saveProductToDatabase(array $productData): void
    {
        try {
            $data = [
                'product_id' => $productData['product_id'] ?? null,
                'title' => $productData['title'] ?? '',
                'price' => $productData['price'] ?? 0,
                'page_url' => $productData['page_url'] ?? '',
                'availability' => $productData['availability'] ?? 0,
                'off' => $productData['off'] ?? 0,
                'image' => $productData['image'] ?? '',
                'guarantee' => $productData['guarantee'] ?? '',
                'category' => $productData['category'] ?? '',
                'brand' => $productData['brand'] ?? '',
                'description' => $productData['description'] ?? '', // اضافه کردن فیلد جدید
                'updated_at' => now(),
            ];

            $existingProduct = Product::where('page_url', $data['page_url'])->first();

            if ($existingProduct) {
                $changes = $this->detectProductChanges($existingProduct, $data);
                if (!empty($changes)) {
                    $existingProduct->update($data);
                    $this->logProduct($productData, 'UPDATED', $changes);
                } else {
                    $this->log("⚡ محصول بدون تغییر: {$data['title']}", self::COLOR_GRAY);
                }
            } else {
                $data['created_at'] = now();
                Product::create($data);
                $this->logProduct($productData, 'NEW');
            }
        } catch (\Exception $e) {
            $this->log("💥 خطا در ذخیره محصول {$productData['title']}: {$e->getMessage()}", self::COLOR_RED);
            throw $e;
        }
    }

    // باقی متدها یکسان باقی می‌مانند...
    public function cleanPrice(string $price): int
    {
        if (empty(trim($price))) {
            return 0;
        }

        // حذف تمام کلمات واحد پولی (فارسی و انگلیسی)
        $price = preg_replace('/\b(تومان|ریال|درهم|دینار|toman|rial|dirham|dinar)\b/ui', '', $price);

        // تبدیل اعداد فارسی به انگلیسی
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $price = str_replace($persianNumbers, $englishNumbers, $price);

        // تبدیل اعداد عربی به انگلیسی
        $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $price = str_replace($arabicNumbers, $englishNumbers, $price);

        // حذف تمام کاراکترهای غیرضروری و نگهداری فقط اعداد و جداکننده‌ها
        $price = preg_replace('/[^\d.,٫]/u', '', $price);

        // حذف فاصله‌های اضافی
        $price = trim($price);

        // اگر خالی شد، صفر برگردان
        if (empty($price)) {
            return 0;
        }

        // تشخیص الگوی قیمت
        // اگر فقط یک نقطه یا کاما در انتها وجود دارد و بعدش سه رقم یا کمتر -> جداکننده هزارگان
        // اگر نقطه یا کاما در وسط و بعدش بیش از سه رقم -> احتمالاً اعشار

        // ابتدا تمام نقاط و کاماها را پیدا کنیم
        $lastDotPos = strrpos($price, '.');
        $lastCommaPos = strrpos($price, ',');
        $lastSeparatorPos = max($lastDotPos, $lastCommaPos);

        if ($lastSeparatorPos !== false) {
            $afterSeparator = substr($price, $lastSeparatorPos + 1);
            $beforeSeparator = substr($price, 0, $lastSeparatorPos);

            // اگر بعد از آخرین جداکننده 3 رقم یا کمتر باشد، احتمالاً جداکننده هزارگان است
            if (strlen($afterSeparator) <= 3) {
                // حذف تمام جداکننده‌های هزارگان
                $price = str_replace([',', '.', '٫'], '', $price);
            } else {
                // احتمالاً اعشار است - فقط آخرین جداکننده را حفظ می‌کنیم
                $beforeSeparator = str_replace([',', '.', '٫'], '', $beforeSeparator);
                $price = $beforeSeparator . '.' . $afterSeparator;
            }
        }

        // تبدیل به عدد صحیح
        return (int)floatval($price);
    }

    public function cleanPriceWithFormat(string $price): string
    {
        if (empty(trim($price))) {
            return '';
        }

        // تشخیص و تقسیم قیمت‌های محدوده‌ای (مثل: ۱۲۰۰۰ - ۱۵۰۰۰ تومان)
        $rangeSeparators = ['–', '-', 'تا', 'الی', 'to'];
        $foundSeparator = null;

        foreach ($rangeSeparators as $separator) {
            if (strpos($price, $separator) !== false) {
                $foundSeparator = $separator;
                break;
            }
        }

        if ($foundSeparator) {
            $priceRange = explode($foundSeparator, $price);
            $cleanedPrices = [];

            foreach ($priceRange as $pricePart) {
                $cleanedPrice = $this->cleanSinglePriceWithFormat(trim($pricePart));
                if (!empty($cleanedPrice) && $cleanedPrice !== '0') {
                    $cleanedPrices[] = $cleanedPrice;
                }
            }

            return count($cleanedPrices) > 1 ? implode(' - ', $cleanedPrices) :
                (count($cleanedPrices) === 1 ? $cleanedPrices[0] : '');
        }

        return $this->cleanSinglePriceWithFormat($price);
    }

    private function cleanSinglePriceWithFormat(string $price): string
    {
        if (empty(trim($price))) {
            return '';
        }

        // حذف واحدهای پولی
        $price = preg_replace('/\b(تومان|ریال|درهم|دینار|toman|rial|dirham|dinar)\b/ui', '', $price);

        // تبدیل اعداد فارسی و عربی به انگلیسی
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        $price = str_replace($persianNumbers, $englishNumbers, $price);
        $price = str_replace($arabicNumbers, $englishNumbers, $price);

        // حذف کاراکترهای اضافی ولی حفظ جداکننده‌ها برای فرمت
        $price = preg_replace('/[^\d.,٫\s]/u', '', $price);

        // حذف فاصله‌های اضافی
        $price = preg_replace('/\s+/', '', trim($price));

        // اگر فقط جداکننده باشد، خالی برگردان
        if (preg_match('/^[.,٫\s]*$/', $price)) {
            return '';
        }

        // حذف جداکننده‌های ابتدا و انتها
        $price = trim($price, '.,٫ ');

        // اگر خالی شد، خالی برگردان
        if (empty($price)) {
            return '';
        }

        // برای حفظ فرمت، فقط اعداد را تمیز می‌کنیم و جداکننده‌ها را حفظ می‌کنیم
        // اما اگر pattern مشخص هزارگان باشد (مثل 281.000) آن را اصلاح می‌کنیم

        // تشخیص الگوی هزارگان (عددی که هر سه رقم یک جداکننده دارد)
        if (preg_match('/^\d{1,3}([.,٫]\d{3})+$/', $price)) {
            // این یک عدد با فرمت هزارگان است - جداکننده‌ها را حذف می‌کنیم
            $price = str_replace([',', '.', '٫'], '', $price);

            // دوباره فرمت هزارگان اضافه می‌کنیم
            return number_format((int)$price);
        }

        return $price;
    }

    public function parseAvailability(string $value, Crawler $crawler): int
    {
        $outOfStockButton = $this->config['out_of_stock_button'] ?? false;
        $outOfStockSelector = $this->config['selectors']['product_page']['out_of_stock'] ?? null;
        $availabilitySelector = $this->config['selectors']['product_page']['availability'] ?? null;
        $positiveKeywords = $this->config['availability_keywords']['positive'] ?? ['موجود', 'افزودن به سبد خرید'];
        $negativeKeywords = $this->config['availability_keywords']['negative'] ?? ['ناموجود', 'اتمام موجودی'];
        $unpricedKeywords = $this->config['price_keywords']['unpriced'] ?? [];

        $this->log("Starting availability detection with value: '$value'", self::COLOR_CYAN);

        // Priority 0: Check unpriced keywords
        if (!empty($value)) {
            foreach ($unpricedKeywords as $keyword) {
                if (stripos($value, $keyword) !== false) {
                    $this->log("✅ Product available due to unpriced keyword: '$keyword' in availability text", self::COLOR_GREEN);
                    return 1;
                }
            }
        }

        // Priority 1: Check out-of-stock selector
        if ($outOfStockButton) {
            $outOfStockResult = $this->checkOutOfStockWithPriority($crawler, $outOfStockSelector);
            if ($outOfStockResult === 0) {
                $this->log("Final decision: Product unavailable due to out-of-stock selector", self::COLOR_RED);
                return 0;
            }
        }

        // Priority 2: Check availability selectors
        $availabilityStatus = $this->checkMultipleAvailabilitySelectors($crawler, $availabilitySelector, $positiveKeywords, $negativeKeywords, $unpricedKeywords);
        if ($availabilityStatus !== null) {
            $this->log("Final decision: Product availability set to " . ($availabilityStatus ? 'Available' : 'Unavailable'), $availabilityStatus ? self::COLOR_GREEN : self::COLOR_RED);
            return $availabilityStatus;
        }

        $fallback = $this->config['default_availability'] ?? 0;
        $this->log("No clear availability indicators found, using fallback: " . ($fallback ? 'Available' : 'Unavailable'), $fallback ? self::COLOR_GREEN : self::COLOR_RED);
        return $fallback;
    }

    public function cleanOff(string $text): string
    {
        $text = trim($text);

        if (empty($text)) {
            return '0';
        }

        if (strpos($text, '%') !== false) {
            preg_match('/(\d+)%/', $text, $matches);
            if (!empty($matches[1])) {
                return $matches[1];
            }
            return str_replace('%', '', $text);
        }

        preg_match('/\d+/', $text, $matches);
        if (!empty($matches)) {
            return $matches[0];
        }

        return '0';
    }

    public function cleanGuarantee(string $text): string
    {
        return trim($text);
    }

    private function extractPriceWithPriority(Crawler $crawler, array $selector): string
    {
        $selectors = is_array($selector['selector']) ? $selector['selector'] : [$selector['selector']];
        $foundSelectors = [];

        foreach ($selectors as $index => $sel) {
            $elements = $selector['type'] === 'css' ? $crawler->filter($sel) : $crawler->filterXPath($sel);

            if ($elements->count() > 0) {
                $extractedValue = $selector['attribute'] ?? false
                    ? ($elements->attr($selector['attribute']) ?? '')
                    : trim($elements->text());

                if (!empty($extractedValue)) {
                    $foundSelectors[$index] = [
                        'selector' => $sel,
                        'value' => $extractedValue
                    ];
                }
            }
        }

        if (empty($foundSelectors)) {
            return '';
        }

        $selectedIndex = max(array_keys($foundSelectors));
        $selectedData = $foundSelectors[$selectedIndex];

        return $selectedData['value'];
    }

    private function extractData(Crawler $crawler, array $selector, ?string $field = null): string
    {
        $selectors = is_array($selector['selector']) ? $selector['selector'] : [$selector['selector']];
        $attributes = isset($selector['attribute'])
            ? (is_array($selector['attribute']) ? $selector['attribute'] : [$selector['attribute']])
            : [null];

        $value = '';

        foreach ($selectors as $index => $sel) {
            // بررسی نوع selector
            if (isset($selector['type']) && $selector['type'] === 'xml') {
                // برای XML sitemap
                $elements = $crawler->filterXPath($sel);
            } elseif ($selector['type'] === 'css') {
                $elements = $crawler->filter($sel);
            } else {
                $elements = $crawler->filterXPath($sel);
            }

            if ($elements->count() > 0) {
                $currentAttribute = $attributes[$index] ?? $attributes[0] ?? null;

                if ($currentAttribute) {
                    $value = $elements->attr($currentAttribute) ?? '';
                } else {
                    $value = trim($elements->text());
                }

                if (!empty($value)) {
                    break;
                }
            }
        }

        return $value;
    }

    private function makeAbsoluteUrl(string $href): string
    {
        if (empty($href) || $href === '#' || stripos($href, 'javascript:') === 0) {
            return '';
        }

        if (stripos($href, 'http://') === 0 || stripos($href, 'https://') === 0) {
            return urldecode($href);
        }

        $baseUrl = $this->config['base_urls'][0] ?? '';
        if (empty($baseUrl)) {
            return $href;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $href = ltrim($href, '/');

        $fullUrl = "$baseUrl/$href";
        return urldecode($fullUrl);
    }

    private function extractCategoryFromTitle(string $title, $wordCount = 1): string
    {
        $cleanTitle = $this->cleanCategoryText($title);
        $words = preg_split('/\s+/', trim($cleanTitle), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return '';
        }

        $categories = [];

        if (is_array($wordCount)) {
            foreach ($wordCount as $count) {
                if (is_numeric($count) && $count > 0 && $count <= count($words)) {
                    $categoryWords = array_slice($words, 0, $count);
                    $category = implode(' ', $categoryWords);
                    if (!empty($category)) {
                        $categories[] = $category;
                    }
                }
            }
        } else {
            if (is_numeric($wordCount) && $wordCount > 0) {
                $categoryWords = array_slice($words, 0, min($wordCount, count($words)));
                $category = implode(' ', $categoryWords);
                if (!empty($category)) {
                    $categories[] = $category;
                }
            }
        }

        $categories = array_filter(array_unique($categories), function ($cat) {
            return !empty(trim($cat));
        });

        return implode(', ', $categories);
    }

    private function cleanCategoryText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-_,]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function extractGuaranteeFromSelector(Crawler $crawler, array $selector, ?string $title = null): string
    {
        $method = $this->config['guarantee_method'] ?? 'selector';
        $keywords = $this->config['guarantee_keywords'] ?? ['گارانتی', 'ضمانت'];

        if ($method === 'selector' && !empty($selector['selector'])) {
            $elements = $this->getElements($crawler, $selector);
            if ($elements->count() > 0) {
                $text = trim($elements->text());
                return $this->cleanGuarantee($text);
            }
            return '';
        } elseif ($method === 'title' && $title) {
            foreach ($keywords as $keyword) {
                if (strpos($title, $keyword) !== false) {
                    return $this->cleanGuarantee($title);
                }
            }
            return '';
        }

        return '';
    }

    private function extractCategoriesFromSelectors(Crawler $crawler, array $selector): string
    {
        $categories = [];
        $selectors = is_array($selector['selector']) ? $selector['selector'] : [$selector['selector']];

        foreach ($selectors as $index => $selectorString) {
            if (empty($selectorString)) {
                continue;
            }

            try {
                $elements = $selector['type'] === 'css'
                    ? $crawler->filter($selectorString)
                    : $crawler->filterXPath($selectorString);

                if ($elements->count() > 0) {
                    $elements->each(function (Crawler $element) use (&$categories, $selector) {
                        $categoryText = $selector['attribute'] ?? false
                            ? ($element->attr($selector['attribute']) ?? '')
                            : trim($element->text());

                        if (!empty($categoryText)) {
                            // اینجا فیلتر اضافه کن!
                            $categoryText = $this->fixCorruptedText($categoryText);
                            $categoryText = $this->cleanCategoryText($categoryText);
                            if (!empty($categoryText)) {
                                $categories[] = $categoryText;
                            }
                        }
                    });
                }
            } catch (\Exception $e) {
                // Log error but continue
            }
        }

        $categories = array_filter(array_unique($categories), function ($cat) {
            return !empty(trim($cat));
        });

        return implode(', ', $categories);
    }

    private function extractProductIdFromUrl(string $url, string $title, Crawler $crawler): string
    {
        if (($this->config['product_id_method'] ?? 'selector') === 'url') {
            $url = str_replace('\\/', '/', $url);
            $pattern = $this->config['product_id_url_pattern'] ?? 'products/(\d+)';

            try {
                if (preg_match("#$pattern#", $url, $matches)) {
                    return $matches[1];
                }
            } catch (\Exception $e) {
                // Pattern failed, try fallback
            }

            $path = parse_url($url, PHP_URL_PATH);
            $parts = explode('/', trim($path, '/'));
            $productIndex = array_search('products', $parts);
            if ($productIndex !== false && isset($parts[$productIndex + 1])) {
                $potentialId = $parts[$productIndex + 1];
                if (is_numeric($potentialId)) {
                    return $potentialId;
                }
            }
        }

        if (($this->config['product_id_source'] ?? 'product_page') === 'product_page') {
            $productIdConfig = $this->config['selectors']['product_page']['product_id'] ?? [];

            if (!empty($productIdConfig)) {
                if ($this->isNewProductIdFormat($productIdConfig)) {
                    return $this->extractProductIdWithNewFormat($crawler, $productIdConfig, $url);
                } else {
                    $value = $this->extractData($crawler, $productIdConfig, 'product_id');
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }

            $patterns = $this->config['product_id_fallback_script_patterns'] ?? [];
            if (!empty($patterns)) {
                $scripts = $crawler->filter('script')->each(function (Crawler $node) {
                    return $node->text();
                });
                foreach ($scripts as $script) {
                    foreach ($patterns as $pattern) {
                        if (preg_match("/$pattern/", $script, $matches)) {
                            return $matches[1] ?? '';
                        }
                    }
                }
            }
        }

        return '';
    }

    private function isNewProductIdFormat(array $config): bool
    {
        if (!isset($config['selector'])) {
            return true;
        }

        if (is_array($config['selector']) && isset($config['selector'][0]) && is_array($config['selector'][0])) {
            return true;
        }

        if (isset($config[0]) && is_array($config[0]) && isset($config[0]['type'])) {
            return true;
        }

        return false;
    }

    private function extractProductIdWithNewFormat(Crawler $crawler, array $config, string $url): string
    {
        $selectors = [];

        if (isset($config[0]) && is_array($config[0])) {
            $selectors = $config;
        } elseif (isset($config['selector']) && is_array($config['selector']) && is_array($config['selector'][0])) {
            $selectors = $config['selector'];
        }

        foreach ($selectors as $index => $selectorConfig) {
            $elements = $selectorConfig['type'] === 'css'
                ? $crawler->filter($selectorConfig['selector'])
                : $crawler->filterXPath($selectorConfig['selector']);

            if ($elements->count() > 0) {
                $value = isset($selectorConfig['attribute'])
                    ? ($elements->attr($selectorConfig['attribute']) ?? '')
                    : trim($elements->text());

                if (!empty($value)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function getElements(Crawler $crawler, array $selector): Crawler
    {
        $selectors = is_array($selector['selector']) ? $selector['selector'] : [$selector['selector']];
        $crawlerResult = new Crawler();

        if (isset($this->config['category_method']) && $this->config['category_method'] === 'title' && empty($selector['selector'])) {
            return $crawlerResult;
        }

        foreach ($selectors as $sel) {
            $elements = $selector['type'] === 'css' ? $crawler->filter($sel) : $crawler->filterXPath($sel);
            if ($elements->count() > 0) {
                return $elements;
            }
        }

        return $crawlerResult;
    }

    private function checkMultipleAvailabilitySelectors(Crawler $crawler, ?array $stockSelector, array $positiveKeywords, array $negativeKeywords, array $unpricedKeywords = []): ?int
    {
        if (!$stockSelector || empty($stockSelector['selector'])) {
            return null;
        }

        $selectors = is_array($stockSelector['selector']) ? $stockSelector['selector'] : [$stockSelector['selector']];

        foreach ($selectors as $index => $selector) {
            $elements = $this->getElements($crawler, ['selector' => $selector, 'type' => $stockSelector['type'] ?? 'css']);

            if ($elements->count() > 0) {
                $stockText = $this->extractData($crawler, ['selector' => $selector, 'type' => $stockSelector['type'] ?? 'css'], 'availability');

                if (!empty($stockText)) {
                    foreach ($unpricedKeywords as $keyword) {
                        if (stripos($stockText, $keyword) !== false) {
                            return 1;
                        }
                    }

                    foreach ($negativeKeywords as $keyword) {
                        if (stripos($stockText, $keyword) !== false) {
                            return 0;
                        }
                    }

                    foreach ($positiveKeywords as $keyword) {
                        if (stripos($stockText, $keyword) !== false) {
                            return 1;
                        }
                    }

                    return null;
                } else {
                    return null;
                }
            }
        }

        return null;
    }

    private function checkOutOfStockWithPriority(Crawler $crawler, ?array $outOfStockSelector): ?int
    {
        if (!$outOfStockSelector || empty($outOfStockSelector['selector'])) {
            return null;
        }

        $selectors = is_array($outOfStockSelector['selector']) ? $outOfStockSelector['selector'] : [$outOfStockSelector['selector']];

        foreach ($selectors as $index => $selector) {
            $elements = $this->getElements($crawler, ['selector' => $selector, 'type' => $outOfStockSelector['type'] ?? 'css']);

            if ($elements->count() > 0) {
                return 0; // Product is unavailable
            }
        }

        return null;
    }

    private function processAvailabilityFallback(Crawler $crawler, array $data): int
    {
        $addToCartSelector = $this->config['selectors']['product_page']['add_to_cart_button'] ?? null;
        $outOfStockSelector = $this->config['selectors']['product_page']['out_of_stock'] ?? null;

        if ($addToCartSelector && $crawler->filter($addToCartSelector)->count() > 0) {
            return 1;
        } elseif ($outOfStockSelector && $crawler->filter($outOfStockSelector)->count() > 0) {
            return 0;
        } elseif (!empty($data['price']) && $data['price'] != '0') {
            return 1;
        } else {
            return 0;
        }
    }

    private function detectProductChanges($existingProduct, array $newData): array
    {
        $changes = [];
        $fieldsToCheck = ['title', 'price', 'availability', 'off', 'image', 'guarantee', 'category', 'brand', 'description'];

        foreach ($fieldsToCheck as $field) {
            $oldValue = $existingProduct->$field;
            $newValue = $newData[$field] ?? null;

            if ($oldValue != $newValue) {
                $changes["$field تغییر"] = "$oldValue → $newValue";
            }
        }

        return $changes;
    }

// 6. تغییر در متد logProduct - اضافه کردن description به لاگ (اختیاری)
    public function logProduct(array $product, string $action = 'PROCESSED', array $extraInfo = []): void
    {
        $availability = (int)($product['availability'] ?? 0) ? 'موجود' : 'ناموجود';
        $guaranteeStatus = empty($product['guarantee']) ? 'ندارد' : $product['guarantee'];
        $discount = (int)($product['off'] ?? 0) > 0 ? $product['off'] . '%' : '0%';
        $productId = $product['product_id'] ?? 'N/A';
        $price = $product['price'] ?? 'N/A';
        $title = $product['title'] ?? 'N/A';
        $category = $product['category'] ?? 'N/A';
        $brand = $product['brand'] ?? 'N/A';
        $description = $product['description'] ?? 'N/A';

        // محاسبه تعداد تصاویر
        $imageCount = empty($product['image']) ? 0 : count(explode(',', $product['image']));
        $imageStatus = $imageCount > 0 ? "$imageCount تصویر" : 'ناموجود';

        $actionConfig = $this->getActionConfig($action);

        $this->log($actionConfig['message'] . " $title (ID: $productId)", $actionConfig['color']);

        if (!empty($extraInfo)) {
            foreach ($extraInfo as $key => $value) {
                $this->log("  └─ $key: $value", self::COLOR_GRAY);
            }
        }

        $headers = ['Product ID', 'Title', 'Price', 'Category', 'Brand', 'Availability', 'Discount', 'Images', 'Guarantee', 'Description'];
        $rows = [[
            $productId,
            mb_substr($title, 0, 30) . (mb_strlen($title) > 30 ? '...' : ''),
            $price,
            mb_substr($category, 0, 20) . (mb_strlen($category) > 20 ? '...' : ''),
            mb_substr($brand, 0, 15) . (mb_strlen($brand) > 15 ? '...' : ''),
            $availability,
            $discount,
            $imageStatus,
            mb_substr($guaranteeStatus, 0, 15) . (mb_strlen($guaranteeStatus) > 15 ? '...' : ''),
            mb_substr($description, 0, 30) . (mb_strlen($description) > 30 ? '...' : '')
        ]];

        $table = $this->generateAsciiTable($headers, $rows);
        $this->log($table, null);
        $this->log("", null);
    }
    private function getActionConfig(string $action): array
    {
        $configs = [
            'NEW' => [
                'message' => '🆕 محصول جدید اضافه شد:',
                'color' => self::COLOR_GREEN,
            ],
            'UPDATED' => [
                'message' => '🔄 محصول آپدیت شد:',
                'color' => self::COLOR_BLUE,
            ],
            'RETRY_SUCCESS' => [
                'message' => '✅ محصول از failed_links بازیابی شد:',
                'color' => self::COLOR_PURPLE,
            ],
            'FAILED' => [
                'message' => '❌ محصول ناموفق:',
                'color' => self::COLOR_RED,
            ],
            'PROCESSED' => [
                'message' => '📦 محصول پردازش شد:',
                'color' => self::COLOR_YELLOW,
            ]
        ];

        return $configs[$action] ?? $configs['PROCESSED'];
    }

    private function applyTitlePrefix(string $title, string $url): string
    {
        $title = trim($title);
        $prefixRules = $this->config['title_prefix_rules'] ?? [];
        $productsUrls = $this->config['products_urls'] ?? [];

        foreach ($productsUrls as $productUrl) {
            if (isset($prefixRules[$productUrl])) {
                $prefix = $prefixRules[$productUrl]['prefix'] ?? '';

                if (empty($prefix)) {
                    return $title;
                }

                if (!str_starts_with($title, $prefix)) {
                    return $prefix . ' ' . $title;
                } else {
                    return $title;
                }
            }
        }

        return $title;
    }

    private function generateAsciiTable(array $headers, array $rows): string
    {
        $widths = [];
        foreach ($headers as $header) {
            $widths[] = max(mb_strwidth($header, 'UTF-8'), 10);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $cellWidth = mb_strwidth((string)$cell, 'UTF-8');
                $widths[$i] = max($widths[$i], $cellWidth);
            }
        }

        $widths[1] = max($widths[1], 40);

        $separator = '+';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
        }
        $separator .= "\n";

        $table = $separator;
        $table .= '|';
        foreach ($headers as $i => $header) {
            $table .= ' ' . str_pad($header, $widths[$i], ' ', STR_PAD_BOTH) . ' |';
        }
        $table .= "\n" . $separator;

        foreach ($rows as $row) {
            $table .= '|';
            foreach ($row as $i => $cell) {
                $paddedCell = $this->mb_str_pad((string)$cell, $widths[$i], ' ', STR_PAD_BOTH);
                $table .= ' ' . $paddedCell . ' |';
            }
            $table .= "\n";
        }
        $table .= $separator;

        return $table;
    }

    private function mb_str_pad(string $input, int $pad_length, string $pad_string = ' ', int $pad_type = STR_PAD_RIGHT): string
    {
        $input_length = mb_strwidth($input, 'UTF-8');
        if ($pad_length <= $input_length) {
            return $input;
        }

        $padding = str_repeat($pad_string, $pad_length - $input_length);
        switch ($pad_type) {
            case STR_PAD_LEFT:
                return $padding . $input;
            case STR_PAD_RIGHT:
                return $input . $padding;
            case STR_PAD_BOTH:
                $left_padding = str_repeat($pad_string, floor(($pad_length - $input_length) / 2));
                $right_padding = str_repeat($pad_string, ceil(($pad_length - $input_length) / 2));
                return $left_padding . $input . $right_padding;
            default:
                return $input;
        }
    }

    private function saveFailedLink(string $url, string $errorMessage): void
    {
        try {
            $existingFailedLink = FailedLink::where('url', $url)->first();

            if ($existingFailedLink) {
                $oldAttempts = $existingFailedLink->attempts;
                $existingFailedLink->update([
                    'attempts' => $oldAttempts + 1,
                    'error_message' => $errorMessage,
                    'updated_at' => now()
                ]);
            } else {
                FailedLink::create([
                    'url' => $url,
                    'attempts' => 1,
                    'error_message' => $errorMessage,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if we can't save failed link
        }
    }

    public function log(string $message, ?string $color = null): void
    {
        $colorReset = "\033[0m";
        $formattedMessage = $color ? $color . $message . $colorReset : $message;

        $logFile = storage_path('logs/scraper_' . date('Ymd') . '.log');
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);

        $cleanMessage = preg_replace("/\033\[[0-9;]*m/", "", $message);
        $shouldDisplay = $this->shouldDisplayLog($cleanMessage);

        if ($shouldDisplay) {
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, $formattedMessage);
            } else {
                echo $formattedMessage . PHP_EOL;
            }
        }
    }

    private function shouldDisplayLog(string $cleanMessage): bool
    {
        $displayConditions = [
            str_contains($cleanMessage, '🆕') || str_contains($cleanMessage, '🔄') ||
            str_contains($cleanMessage, '✅') || str_contains($cleanMessage, '❌'),
            str_starts_with($cleanMessage, '+') && str_contains($cleanMessage, '|'),
            str_starts_with($cleanMessage, 'Extracted product_id') ||
            str_contains($cleanMessage, 'failed_links') ||
            str_contains($cleanMessage, 'Failed to extract') ||
            str_contains($cleanMessage, '═══') || str_contains($cleanMessage, '───'),
        ];

        return array_reduce($displayConditions, function ($carry, $condition) {
            return $carry || $condition;
        }, false);
    }
}
