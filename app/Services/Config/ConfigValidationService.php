<?php

namespace App\Services\Config;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * سرویس اعتبارسنجی کانفیگ‌ها
 */
class ConfigValidationService
{
    /**
     * اعتبارسنجی کانفیگ اصلی
     */
    public function getValidator(Request $request): ValidatorInstance
    {
        $rules = $this->getBaseRules();
        $method = (int)$request->input('method', 1);

        // اضافه کردن قوانین مخصوص متد
        $rules = array_merge($rules, $this->getMethodSpecificRules($request, $method));

        return Validator::make($request->all(), $rules);
    }

    /**
     * قوانین پایه اعتبارسنجی
     */
    private function getBaseRules(): array
    {
        return [
            'site_name' => 'required|string|max:255',
            'method' => 'required|in:1,2,3',
            'base_urls' => 'required|array|min:1',
            'base_urls.*' => 'required|url',
            'products_urls' => 'required|array|min:1',
            'products_urls.*' => 'required|url',
            'keep_price_format' => 'boolean',
            'product_id_method' => 'required|in:selector,url',
            'product_id_source' => 'required|in:product_page,url,main_page',
            'guarantee_method' => 'required|in:selector,title',
            'guarantee_keywords' => 'required|array|min:1',
            'guarantee_keywords.*' => 'required|string',
            'availability_mode' => 'required|in:priority_based,keyword_based',
            'availability_keywords.positive' => 'required|array|min:1',
            'availability_keywords.positive.*' => 'required|string',
            'availability_keywords.negative' => 'required|array|min:1',
            'availability_keywords.negative.*' => 'required|string',
            'price_keywords.unpriced' => 'required|array|min:1',
            'price_keywords.unpriced.*' => 'required|string',
            'selectors.main_page.product_links.type' => 'required|string|in:css,xpath,xml', // تغییر: اضافه شدن xml
            'selectors.main_page.product_links.selector' => 'required|string',
            'selectors.main_page.product_links.attribute' => 'required|string',
            'out_of_stock_button' => 'boolean',
            'selectors.product_page.out_of_stock.type' => 'required_if:out_of_stock_button,1|string|in:css,xpath',
            'selectors.product_page.out_of_stock.selector' => 'required_if:out_of_stock_button,1|array|min:1',
            'selectors.product_page.out_of_stock.selector.*' => 'required_if:out_of_stock_button,1|string',
            'category_method' => 'required|in:selector,title',
            'category_word_count' => 'nullable|array',
            'category_word_count.*' => 'required|integer|min:1',
            'category_word_count_single' => 'nullable|integer|min:1',
            'selectors.product_page.category.selector' => 'nullable|array',
            'selectors.product_page.category.selector.*' => 'required|string',
            'selectors.product_page.category.selector_single' => 'nullable|string',
            'selectors.product_page.product_id.selector' => 'nullable|array',
            'selectors.product_page.product_id.selector.*' => 'required|string',
            'selectors.product_page.product_id.selector_single' => 'nullable|string',
            'selectors.product_page.product_id.attribute' => 'nullable|array',
            'selectors.product_page.product_id.attribute.*' => 'required|string',
            'selectors.product_page.product_id.attribute_single' => 'nullable|string',
            'run_method' => 'required|in:new,continue',
            'database' => 'required|in:clear,continue',
            'use_set_category' => 'boolean',
            'set_category' => 'nullable|required_if:use_set_category,1|string',
            'title_prefix_rules.url.*' => 'nullable|url',
            'title_prefix_rules.prefix.*' => 'nullable|string|max:255',
        ];
    }

    /**
     * قوانین خاص هر متد
     */
    private function getMethodSpecificRules(Request $request, int $method): array
    {
        $rules = [];

        if ($method == 1) {
            $rules = array_merge($rules, $this->getMethod1Rules());
        } else {
            $rules = array_merge($rules, $this->getMethod2And3Rules($request, $method));
        }

        // قوانین مخصوص main_page
        if ($request->input('product_id_source') == 'main_page') {
            $rules = array_merge($rules, $this->getMainPageRules());
        }

        return $rules;
    }

    /**
     * قوانین متد ۱
     */
    private function getMethod1Rules(): array
    {
        return [
            'pagination.type' => 'required|in:query,path',
            'pagination.parameter' => 'required|string',
            'pagination.separator' => 'required|string',
            'pagination.max_pages' => 'required|integer|min:1',
            'pagination.use_sample_url' => 'nullable|boolean',
            'pagination.sample_url' => 'nullable|required_if:pagination.use_sample_url,1|url',
        ];
    }

    /**
     * قوانین متد ۲ و ۳
     */
    private function getMethod2And3Rules(Request $request, int $method): array
    {
        $rules = [];

        if ($method == 2) {
            $rules['share_product_id_from_method_2'] = 'boolean';
            $rules['container'] = 'required|string';
            $rules['scrool'] = 'integer|min:1';
        } elseif ($method == 3) {
            $rules['container'] = 'nullable|string';
            $rules['scrool'] = 'integer|min:1';
        }

        if (in_array($method, [2, 3])) {
            $rules['pagination_method'] = 'required|in:next_button,url';

            if ($request->input('pagination_method') == 'next_button') {
                $rules['pagination_next_button_selector'] = 'required|string';
            } else {
                $rules = array_merge($rules, [
                    'pagination_url_type' => 'required|in:query,path',
                    'pagination_url_parameter' => 'required|string',
                    'pagination_url_separator' => 'required|string',
                    'pagination_max_pages' => 'required|integer|min:1',
                    'pagination_use_sample_url' => 'nullable|boolean',
                    'pagination_sample_url' => 'nullable|required_if:pagination_use_sample_url,1|url',
                ]);
            }
        }

        return $rules;
    }

    /**
     * قوانین صفحه اصلی
     */
    private function getMainPageRules(): array
    {
        return [
            'selectors.main_page.product_id.type' => 'required|string',
            'selectors.main_page.product_id.selector' => 'required|string',
            'selectors.main_page.product_id.attribute' => 'required|string',
        ];
    }

    /**
     * اعتبارسنجی درخواست تست محصول واحد
     */
    public function validateSingleProductRequest(Request $request): ValidatorInstance
    {
        $rules = [
            'product_url' => 'required|url',
            'title_selector' => 'required|string',

            // قوانین برای سلکتورهای چندگانه
            'price_selector' => 'required|array|min:1',
            'price_selector.*' => 'required|string',

            'category_selector' => 'nullable|array',
            'category_selector.*' => 'nullable|string',

            'availability_selector' => 'nullable|array',
            'availability_selector.*' => 'nullable|string',

            'out_of_stock_selector' => 'nullable|array',
            'out_of_stock_selector.*' => 'nullable|string',

            'product_id_selector' => 'nullable|array',
            'product_id_selector.*' => 'nullable|string',

            'product_id_attribute' => 'nullable|array',
            'product_id_attribute.*' => 'nullable|string',

            // سلکتورهای تکی
            'image_selector' => 'nullable|string',
            'image_attribute' => 'nullable|string',
            'off_selector' => 'nullable|string',
            'guarantee_selector' => 'nullable|string',

            // کلمات کلیدی
            'guarantee_keywords' => 'nullable|array',
            'guarantee_keywords.*' => 'nullable|string',

            'availability_keywords_positive' => 'nullable|array',
            'availability_keywords_positive.*' => 'nullable|string',

            'availability_keywords_negative' => 'nullable|array',
            'availability_keywords_negative.*' => 'nullable|string',

            'price_keywords_unpriced' => 'nullable|array',
            'price_keywords_unpriced.*' => 'nullable|string',

            // تنظیمات
            'out_of_stock_button' => 'boolean',
        ];

        // اعتبارسنجی شرطی برای out_of_stock
        if ($request->input('out_of_stock_button')) {
            $rules['out_of_stock_selector'] = 'required|array|min:1';
            $rules['out_of_stock_selector.*'] = 'required|string';
        }

        return Validator::make($request->all(), $rules);
    }
}
