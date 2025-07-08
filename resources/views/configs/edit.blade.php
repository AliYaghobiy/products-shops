<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش کانفیگ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/vazirmatn/33.0.3/Vazirmatn-font-face.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f1f5f9;
            --danger: #ef4444;
            --success: #10b981;
            --text: #334155;
            --border: #e2e8f0;
            --bg: #ffffff;
            --bg-secondary: #f8fafc;
        }

        [data-theme="dark"] {
            --primary: #818cf8;
            --primary-dark: #6366f1;
            --secondary: #374151;
            --text: #f1f5f9;
            --border: #4b5563;
            --bg: #1f2937;
            --bg-secondary: #111827;
        }

        * {
            transition: all 0.2s ease;
        }

        body {
            font-family: "Vazirmatn", system-ui, sans-serif;
            background: var(--bg-secondary);
            color: var(--text);
        }

        .card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .input {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text);
            width: 100%;
        }

        .input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .tab {
            padding: 12px 20px;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            white-space: nowrap;
        }

        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
        }

        .method-card {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            text-align: center;
        }

        .method-card.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .hidden {
            display: none !important;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .required {
            color: var(--danger);
        }

        .flex-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .icon {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .tab-container {
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .tab-container::-webkit-scrollbar {
            display: none;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }

            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }

            .tab {
                padding: 8px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="card p-6 mb-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold flex items-center gap-3">
                <svg class="icon w-8 h-8 text-blue-600">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                ویرایش کانفیگ: {{ $filename }}
            </h1>
            <button id="theme-toggle" class="btn btn-secondary">
                <svg class="icon" id="theme-icon">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                </svg>
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="card p-6 mb-6 bg-red-50 border-red-300">
            <div class="flex items-center text-red-800">
                <svg class="icon w-6 h-6 ml-2 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <strong class="font-bold text-lg">خطا!</strong>
            </div>
            <ul class="list-disc mr-8 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('configs.update', $filename) }}" method="POST" id="config-form" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Navigation Tabs -->
        <div class="card">
            <div class="tab-container flex overflow-x-auto border-b border-gray-200">
                <div class="tab active" data-tab="basic">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                    </svg>
                    اطلاعات پایه
                </div>
                <div class="tab" data-tab="urls">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                    </svg>
                    آدرس‌ها
                </div>
                <div class="tab" data-tab="database">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                    </svg>
                    تنظیمات دیتابیس
                </div>
                <div class="tab" data-tab="price">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    تنظیمات قیمت
                </div>
                <div class="tab" data-tab="pagination">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                    </svg>
                    تنظیمات پیجینیشن
                </div>
                <div class="tab" data-tab="webdriver">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>
                    </svg>
                    تنظیمات وب درایور
                </div>
                <div class="tab" data-tab="keywords">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                    </svg>
                    کلمات کلیدی و شناسایی
                </div>
                <div class="tab" data-tab="selectors">
                    <svg class="icon inline ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>
                    </svg>
                    سلکتورها
                </div>
            </div>
        </div>

        <!-- Tab Contents -->

        <!-- Basic Info Tab -->
        <div id="tab-basic" class="tab-content fade-in">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-6 text-blue-600">اطلاعات پایه</h2>

                <div class="form-group">
                    <label class="label">نام سایت <span class="required">*</span></label>
                    <input type="text" name="site_name" class="input" placeholder="مثال: دیجی‌کالا" required
                           value="{{ old('site_name', $filename) }}">
                </div>

                <div class="form-group">
                    <label class="label">روش اسکرپ <span class="required">*</span></label>
                    <div class="grid-3 mt-4">
                        <div class="method-card {{ old('method', $content['method']) == 1 ? 'selected' : '' }}"
                             data-method="1">
                            <input type="radio" name="method" value="1"
                                   class="hidden" {{ old('method', $content['method']) == 1 ? 'checked' : '' }}>
                            <div class="text-4xl mb-3">📄</div>
                            <h3 class="font-bold">روش 1</h3>
                            <p class="text-sm text-gray-600 mt-1">صفحه‌بندی ساده</p>
                        </div>
                        <div class="method-card {{ old('method', $content['method']) == 2 ? 'selected' : '' }}"
                             data-method="2">
                            <input type="radio" name="method" value="2"
                                   class="hidden" {{ old('method', $content['method']) == 2 ? 'checked' : '' }}>
                            <div class="text-4xl mb-3">🌐</div>
                            <h3 class="font-bold">روش 2</h3>
                            <p class="text-sm text-gray-600 mt-1">وب درایور</p>
                        </div>
                        <div class="method-card {{ old('method', $content['method']) == 3 ? 'selected' : '' }}"
                             data-method="3">
                            <input type="radio" name="method" value="3"
                                   class="hidden" {{ old('method', $content['method']) == 3 ? 'checked' : '' }}>
                            <div class="text-4xl mb-3">⚡</div>
                            <h3 class="font-bold">روش 3</h3>
                            <p class="text-sm text-gray-600 mt-1">وب درایور بهینه</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- URLs Tab -->
        <div id="tab-urls" class="tab-content hidden">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-6 text-blue-600">آدرس‌های پایه</h2>

                <div class="form-group">
                    <label class="label">آدرس‌های پایه (URL) <span class="required">*</span></label>
                    <div id="base-urls" class="space-y-3">
                        @foreach (old('base_urls', $content['base_urls'] ?? []) as $url)
                            <div class="flex-row">
                                <input type="url" name="base_urls[]" class="input" placeholder="https://example.com"
                                       required value="{{ $url }}">
                                <button type="button" class="btn btn-danger remove-url">−</button>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-primary add-url">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="label">آدرس‌های صفحات محصول <span class="required">*</span></label>
                    <div id="product-urls" class="space-y-3">
                        @foreach (old('products_urls', $content['products_urls'] ?? []) as $url)
                            <div class="flex-row">
                                <input type="url" name="products_urls[]" class="input"
                                       placeholder="https://example.com/product/123" required value="{{ $url }}">
                                <button type="button" class="btn btn-danger remove-product-url">−</button>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-primary add-product-url">+</button>
                    </div>
                </div>

                <!-- Title Prefix Rules -->
                <div class="form-group">
                    <label class="label">قوانین پیشوند عنوان <span class="text-gray-500">(اختیاری)</span></label>
                    <div id="title-prefix-rules" class="space-y-3">
                        @foreach (old('title_prefix_rules.url', $content['title_prefix_rules'] ?? []) as $url => $rule)
                            <div class="grid-2 gap-4">
                                <div class="flex-row">
                                    <input type="url" name="title_prefix_rules[url][]" class="input"
                                           placeholder="https://example.com/fa/book/" value="{{ $url }}">
                                    <button type="button" class="btn btn-danger remove-title-prefix">−</button>
                                </div>
                                <div class="flex-row">
                                    <input type="text" name="title_prefix_rules[prefix][]" class="input"
                                           placeholder="کتاب" value="{{ $rule['prefix'] ?? '' }}">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-primary add-title-prefix mt-3">
                        <svg class="icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        افزودن قانون پیشوند
                    </button>
                </div>
            </div>
        </div>

        <!-- Database Settings Tab -->
        <div id="tab-database" class="tab-content hidden">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-6 text-blue-600">تنظیمات دیتابیس و اجرا</h2>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="label">روش اجرا <span class="required">*</span></label>
                        <select name="run_method" class="input" required>
                            <option
                                value="new" {{ old('run_method', $content['run_method'] ?? 'new') === 'new' ? 'selected' : '' }}>
                                جدید
                            </option>
                            <option
                                value="continue" {{ old('run_method', $content['run_method'] ?? '') === 'continue' ? 'selected' : '' }}>
                                ادامه
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="label">وضعیت دیتابیس <span class="required">*</span></label>
                        <select name="database" class="input" required>
                            <option
                                value="clear" {{ old('database', $content['database'] ?? 'clear') === 'clear' ? 'selected' : '' }}>
                                پاک کردن
                            </option>
                            <option
                                value="continue" {{ old('database', $content['database'] ?? '') === 'continue' ? 'selected' : '' }}>
                                ادامه
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price Settings Tab -->
        <div id="tab-price" class="tab-content hidden">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-6 text-blue-600">تنظیمات قیمت</h2>
                <div class="space-y-4">
                    <label class="flex items-center gap-3">
                        <input type="hidden" name="keep_price_format" value="0">
                        <input type="checkbox" name="keep_price_format" value="1"
                               class="w-5 h-5" {{ old('keep_price_format', $content['keep_price_format'] ?? false) ? 'checked' : '' }}>
                        <span>حفظ فرمت قیمت</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="hidden" name="use_set_category" value="0">
                        <input type="checkbox" name="use_set_category" value="1" id="use-set-category"
                               class="w-5 h-5" {{ old('use_set_category', isset($content['set_category'])) ? 'checked' : '' }}>
                        <span>استفاده از دسته‌بندی ثابت</span>
                    </label>
                    <div id="set-category-field"
                         class="form-group {{ old('use_set_category', isset($content['set_category'])) ? '' : 'hidden' }}">
                        <label class="label">دسته‌بندی ثابت <span class="required">*</span></label>
                        <input type="text" name="set_category" class="input" placeholder="مثال: لوازم خانگی"
                               value="{{ old('set_category', $content['set_category'] ?? '') }}" {{ old('use_set_category', isset($content['set_category'])) ? 'required' : '' }}>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination Settings Tab -->
        <div id="tab-pagination" class="tab-content hidden">
            <div class="card p-6" id="pagination-settings">
                <h2 class="text-xl font-bold mb-6 text-blue-600">تنظیمات صفحه‌بندی</h2>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="label">نوع صفحه‌بندی <span class="required">*</span></label>
                        <select name="pagination[type]" class="input" required>
                            <option
                                value="query" {{ old('pagination.type', $content['method_settings']['method_1']['pagination']['type'] ?? 'query') == 'query' ? 'selected' : '' }}>
                                Query Parameter
                            </option>
                            <option
                                value="path" {{ old('pagination.type', $content['method_settings']['method_1']['pagination']['type'] ?? '') == 'path' ? 'selected' : '' }}>
                                Path Parameter
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="label">پارامتر صفحه‌بندی <span class="required">*</span></label>
                        <input type="text" name="pagination[parameter]"
                               value="{{ old('pagination.parameter', $content['method_settings']['method_1']['pagination']['parameter'] ?? 'page') }}"
                               class="input" required>
                    </div>
                    <div class="form-group">
                        <label class="label">جداکننده <span class="required">*</span></label>
                        <input type="text" name="pagination[separator]"
                               value="{{ old('pagination.separator', $content['method_settings']['method_1']['pagination']['separator'] ?? '=') }}"
                               class="input" required>
                    </div>
                    <div class="form-group">
                        <label class="label">حداکثر صفحات <span class="required">*</span></label>
                        <input type="number" name="pagination[max_pages]"
                               value="{{ old('pagination.max_pages', $content['method_settings']['method_1']['pagination']['max_pages'] ?? 10) }}"
                               min="1" class="input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="flex items-center gap-3">
                        <input type="hidden" name="pagination[use_sample_url]" value="0">
                        <input type="checkbox" name="pagination[use_sample_url]" value="1"
                               id="pagination-use-sample-url"
                               class="w-5 h-5" {{ old('pagination.use_sample_url', $content['method_settings']['method_1']['pagination']['use_sample_url'] ?? false) ? 'checked' : '' }}>
                        <span>استفاده از URL نمونه</span>
                    </label>
                </div>

                <div id="sample-url-container"
                     class="form-group {{ old('pagination.use_sample_url', $content['method_settings']['method_1']['pagination']['use_sample_url'] ?? false) ? '' : 'hidden' }}">
                    <label class="label">URL نمونه</label>
                    <input type="url" name="pagination[sample_url]" class="input"
                           placeholder="https://example.com/products?page=1"
                           value="{{ old('pagination.sample_url', $content['method_settings']['method_1']['pagination']['sample_url'] ?? '') }}">
                </div>
            </div>
        </div>

        <!-- WebDriver Settings Tab -->
        <div id="tab-webdriver" class="tab-content hidden">
            <div class="card p-6" id="webdriver-settings">
                <h2 class="text-xl font-bold mb-6 text-blue-600">تنظیمات وب درایور</h2>

                <!-- فیلد کانتینر برای متد 3 -->
                <div class="form-group method-3-field hidden">
                    <label class="label">کانتینر <span class="text-gray-500">(اختیاری)</span></label>
                    <input type="text" name="container" class="input"
                           value="{{ old('container', $content['container'] ?? '') }}">
                    <small class="text-gray-500">این فیلد برای متد 3 اختیاری است.</small>
                </div>
                <div class="form-group method-2-field hidden">
                    <label class="label">کانتینر <span class="text-gray-500">(اختیاری)</span></label>
                    <input type="text" name="container" class="input"
                           value="{{ old('container', $content['container'] ?? '') }}">
                    <small class="text-gray-500">این فیلد برای متد 3 اختیاری است.</small>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="label">تعداد اسکرول</label>
                        <input type="number" name="scrool" value="{{ old('scrool', $content['scrool'] ?? 10) }}" min="1"
                               class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">روش صفحه‌بندی <span class="required">*</span></label>
                        <select name="pagination_method" id="pagination-method" class="input">
                            <option
                                value="next_button" {{ old('pagination_method', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['method'] ?? 'next_button') == 'next_button' ? 'selected' : '' }}>
                                دکمه بعدی
                            </option>
                            <option
                                value="url" {{ old('pagination_method', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['method'] ?? '') == 'url' ? 'selected' : '' }}>
                                URL
                            </option>
                        </select>
                    </div>
                </div>

                <div id="next-button-container" class="form-group">
                    <label class="label">سلکتور دکمه بعدی <span class="required">*</span></label>
                    <input type="text" name="pagination_next_button_selector" class="input"
                           placeholder=".next-page-button"
                           value="{{ old('pagination_next_button_selector', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['next_button']['selector'] ?? '') }}">
                </div>

                <div id="url-pagination-container" class="hidden">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="label">نوع پیجینیشن URL <span class="required">*</span></label>
                            <select name="pagination_url_type" class="input">
                                <option
                                    value="query" {{ old('pagination_url_type', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['type'] ?? 'query') == 'query' ? 'selected' : '' }}>
                                    Query Parameter
                                </option>
                                <option
                                    value="path" {{ old('pagination_url_type', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['type'] ?? '') == 'path' ? 'selected' : '' }}>
                                    Path Parameter
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">پارامتر <span class="required">*</span></label>
                            <input type="text" name="pagination_url_parameter"
                                   value="{{ old('pagination_url_parameter', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['parameter'] ?? 'page') }}"
                                   class="input">
                        </div>
                        <div class="form-group">
                            <label class="label">جداکننده <span class="required">*</span></label>
                            <input type="text" name="pagination_url_separator"
                                   value="{{ old('pagination_url_separator', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['separator'] ?? '=') }}"
                                   class="input">
                        </div>
                        <div class="form-group">
                            <label class="label">حداکثر صفحات <span class="required">*</span></label>
                            <input type="number" name="pagination_max_pages"
                                   value="{{ old('pagination_max_pages', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['max_pages'] ?? 3) }}"
                                   min="1" class="input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="flex items-center gap-3">
                            <input type="hidden" name="pagination_use_sample_url" value="0">
                            <input type="checkbox" name="pagination_use_sample_url" value="1"
                                   id="pagination-use-sample-url-webdriver"
                                   class="w-5 h-5" {{ old('pagination_use_sample_url', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['use_sample_url'] ?? false) ? 'checked' : '' }}>
                            <span>استفاده از URL نمونه</span>
                        </label>
                    </div>

                    <div id="pagination-sample-url-container"
                         class="form-group {{ old('pagination_use_sample_url', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['use_sample_url'] ?? false) ? '' : 'hidden' }}">
                        <label class="label">URL نمونه</label>
                        <input type="url" name="pagination_sample_url" class="input"
                               placeholder="https://example.com/products?page=1"
                               value="{{ old('pagination_sample_url', $content['method_settings'][old('method', $content['method']) == 2 ? 'method_2' : 'method_3']['navigation']['pagination']['url']['sample_url'] ?? '') }}">
                    </div>
                </div>

                <!-- تنظیمات روش 2 -->
                <div class="method-2-field hidden">

                    <div class="form-group">
                        <label class="flex items-center gap-3">
                            <input type="hidden" name="share_product_id_from_method_2" value="0">
                            <input type="checkbox" name="share_product_id_from_method_2" value="1"
                                   class="w-5 h-5" {{ old('share_product_id_from_method_2', $content['share_product_id_from_method_2'] ?? false) ? 'checked' : '' }}>
                            <span>اشتراک گذاری شناسه محصول از روش 2</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Keywords and Identification Tab -->
        <div id="tab-keywords" class="tab-content hidden">
            <div class="space-y-6">
                <!-- Identification Methods -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold mb-6 text-blue-600">روش‌های شناسایی</h2>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="label">روش شناسایی شناسه محصول <span class="required">*</span></label>
                            <select name="product_id_method" class="input" required>
                                <option
                                    value="selector" {{ old('product_id_method', $content['product_id_method'] ?? 'selector') == 'selector' ? 'selected' : '' }}>
                                    سلکتور
                                </option>
                                <option
                                    value="url" {{ old('product_id_method', $content['product_id_method'] ?? '') == 'url' ? 'selected' : '' }}>
                                    URL
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">منبع شناسایی شناسه محصول <span class="required">*</span></label>
                            <select name="product_id_source" id="product-id-source" class="input" required>
                                <option
                                    value="product_page" {{ old('product_id_source', $content['product_id_source'] ?? 'product_page') == 'product_page' ? 'selected' : '' }}>
                                    صفحه محصول
                                </option>
                                <option
                                    value="url" {{ old('product_id_source', $content['product_id_source'] ?? '') == 'url' ? 'selected' : '' }}>
                                    URL
                                </option>
                                <option
                                    value="main_page" {{ old('product_id_source', $content['product_id_source'] ?? '') == 'main_page' ? 'selected' : '' }}>
                                    صفحه اصلی
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">روش شناسایی گارانتی <span class="required">*</span></label>
                            <select name="guarantee_method" class="input" required>
                                <option
                                    value="selector" {{ old('guarantee_method', $content['guarantee_method'] ?? 'selector') == 'selector' ? 'selected' : '' }}>
                                    سلکتور
                                </option>
                                <option
                                    value="title" {{ old('guarantee_method', $content['guarantee_method'] ?? '') == 'title' ? 'selected' : '' }}>
                                    عنوان
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">حالت شناسایی موجودی <span class="required">*</span></label>
                            <select name="availability_mode" class="input" required>
                                <option
                                    value="priority_based" {{ old('availability_mode', $content['availability_mode'] ?? 'priority_based') === 'priority_based' ? 'selected' : '' }}>
                                    هوشمند
                                </option>
                                <option
                                    value="keyword_based" {{ old('availability_mode', $content['availability_mode'] ?? '') === 'keyword_based' ? 'selected' : '' }}>
                                    کلمه کلیدی
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">حالت شناسایی دسته بندی <span class="required">*</span></label>
                            <select name="category_method" class="input" required>
                                <option
                                    value="selector" {{ old('category_method', $content['category_method'] ?? 'selector') === 'selector' ? 'selected' : '' }}>
                                    سلکتور
                                </option>
                                <option
                                    value="title" {{ old('category_method', $content['category_method'] ?? '') === 'title' ? 'selected' : '' }}>
                                    عنوان
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Keywords -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold mb-6 text-purple-600">کلمات کلیدی</h2>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="label">کلمات کلیدی گارانتی <span class="required">*</span></label>
                            <div id="guarantee-keywords" class="space-y-2">
                                @foreach (old('guarantee_keywords', $content['guarantee_keywords'] ?? ['گارانتی']) as $keyword)
                                    <div class="flex-row">
                                        <input type="text" name="guarantee_keywords[]" value="{{ $keyword }}"
                                               class="input" required>
                                        <button type="button" class="btn btn-primary add-guarantee">+</button>
                                        <button type="button" class="btn btn-danger remove-guarantee">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="label">کلمات موجودی (مثبت) <span class="required">*</span></label>
                            <div id="availability-positive" class="space-y-2">
                                @foreach (old('availability_keywords.positive', $content['availability_keywords']['positive'] ?? ['موجود']) as $keyword)
                                    <div class="flex-row">
                                        <input type="text" name="availability_keywords[positive][]"
                                               value="{{ $keyword }}" class="input" required>
                                        <button type="button" class="btn btn-primary add-positive">+</button>
                                        <button type="button" class="btn btn-danger remove-positive">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="label">کلمات موجودی (منفی) <span class="required">*</span></label>
                            <div id="availability-negative" class="space-y-2">
                                @foreach (old('availability_keywords.negative', $content['availability_keywords']['negative'] ?? ['ناموجود']) as $keyword)
                                    <div class="flex-row">
                                        <input type="text" name="availability_keywords[negative][]"
                                               value="{{ $keyword }}" class="input" required>
                                        <button type="button" class="btn btn-primary add-negative">+</button>
                                        <button type="button" class="btn btn-danger remove-negative">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="label">کلمات قیمت (بدون قیمت) <span class="required">*</span></label>
                            <div id="price-unpriced" class="space-y-2">
                                @foreach (old('price_keywords.unpriced', $content['price_keywords']['unpriced'] ?? ['تماس بگیرید']) as $keyword)
                                    <div class="flex-row">
                                        <input type="text" name="price_keywords[unpriced][]" value="{{ $keyword }}"
                                               class="input" required>
                                        <button type="button" class="btn btn-primary add-unpriced">+</button>
                                        <button type="button" class="btn btn-danger remove-unpriced">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selectors Tab -->
        <div id="tab-selectors" class="tab-content hidden">
            <div class="space-y-6">
                <!-- Main Page Selectors -->
                <div class="card p-6">
                    <h3 class="text-lg font-bold mb-4 text-green-600">سلکتورهای صفحه اصلی</h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="label">نوع سلکتور لینک محصولات <span class="required">*</span></label>
                            <select name="selectors[main_page][product_links][type]" class="input" required>
                                <option
                                    value="css" {{ old('selectors.main_page.product_links.type', $content['selectors']['main_page']['product_links']['type'] ?? 'css') === 'css' ? 'selected' : '' }}>
                                    CSS Selector
                                </option>
                                <option
                                    value="xpath" {{ old('selectors.main_page.product_links.type', $content['selectors']['main_page']['product_links']['type'] ?? 'css') === 'xpath' ? 'selected' : '' }}>
                                    XPath
                                </option>
                                <option
                                    value="xml" {{ old('selectors.main_page.product_links.type', $content['selectors']['main_page']['product_links']['type'] ?? 'css') === 'xml' ? 'selected' : '' }}>
                                    XML (برای Sitemap)
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">سلکتور لینک محصولات <span class="required">*</span></label>
                            <input type="text" name="selectors[main_page][product_links][selector]" class="input"
                                   placeholder="مثال: .product-item a یا //loc" required
                                   value="{{ old('selectors.main_page.product_links.selector', $content['selectors']['main_page']['product_links']['selector'] ?? '') }}">
                            <small class="text-gray-500 text-xs mt-1">
                                برای CSS: .product-item a | برای XPath: //a[@class='product'] | برای XML Sitemap: //loc
                            </small>
                        </div>
                        <div class="form-group">
                            <label class="label">صفت لینک محصولات <span class="required">*</span></label>
                            <input type="text" name="selectors[main_page][product_links][attribute]"
                                   value="{{ old('selectors.main_page.product_links.attribute', $content['selectors']['main_page']['product_links']['attribute'] ?? 'href') }}"
                                   class="input" required>
                            <small class="text-gray-500 text-xs mt-1">
                                برای CSS/XPath: href | برای XML: false (متن عنصر را می‌گیرد)
                            </small>
                        </div>
                    </div>

                    <!-- شناسه محصول در صفحه اصلی (شرطی) -->
                    <div id="main-page-product-id-container"
                         class="grid-2 {{ old('product_id_source', $content['product_id_source'] ?? 'product_page') == 'main_page' ? '' : 'hidden' }}">
                        <div class="form-group">
                            <label class="label">سلکتور شناسه محصول <span class="required">*</span></label>
                            <input type="hidden" name="selectors[main_page][product_id][type]" value="css">
                            <input type="text" name="selectors[main_page][product_id][selector]" class="input"
                                   placeholder=".product-item .product-id"
                                   value="{{ old('selectors.main_page.product_id.selector', $content['selectors']['main_page']['product_id']['selector'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="label">صفت شناسه محصول <span class="required">*</span></label>
                            <input type="text" name="selectors[main_page][product_id][attribute]"
                                   value="{{ old('selectors.main_page.product_id.attribute', $content['selectors']['main_page']['product_id']['attribute'] ?? 'data-id') }}"
                                   class="input">
                        </div>
                    </div>
                </div>

                <!-- Product Page Selectors -->
                <div class="card p-6">
                    <h3 class="text-lg font-bold mb-4 text-purple-600">سلکتورهای صفحه محصول</h3>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="label">سلکتور عنوان</label>
                            <input type="hidden" name="selectors[product_page][title][type]" value="css">
                            <input type="text" name="selectors[product_page][title][selector]" class="input"
                                   placeholder=".product-title"
                                   value="{{ old('selectors.product_page.title.selector', $content['selectors']['product_page']['title']['selector'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="label">سلکتور برند</label>
                            <input type="hidden" name="selectors[product_page][brand][type]" value="css">
                            <input type="text" name="selectors[product_page][brand][selector]" class="input"
                                   placeholder=".product-brand"
                                   value="{{ old('selectors.product_page.brand.selector', $content['selectors']['product_page']['brand']['selector'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="label">سلکتور تصویر</label>
                            <input type="hidden" name="selectors[product_page][image][type]" value="css">
                            <input type="text" name="selectors[product_page][image][selector]" class="input"
                                   placeholder=".product-image"
                                   value="{{ old('selectors.product_page.image.selector', $content['selectors']['product_page']['image']['selector'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="label">صفت تصویر</label>
                            <input type="text" name="selectors[product_page][image][attribute]"
                                   value="{{ old('selectors.product_page.image.attribute', $content['selectors']['product_page']['image']['attribute'] ?? 'src') }}"
                                   class="input">
                        </div>
                        <div class="form-group">
                            <label class="label">سلکتور تخفیف</label>
                            <input type="hidden" name="selectors[product_page][off][type]" value="css">
                            <input type="text" name="selectors[product_page][off][selector]" class="input"
                                   placeholder=".product-discount"
                                   value="{{ old('selectors.product_page.off.selector', $content['selectors']['product_page']['off']['selector'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="label">سلکتور گارانتی</label>
                            <input type="hidden" name="selectors[product_page][guarantee][type]" value="css">
                            <input type="text" name="selectors[product_page][guarantee][selector]" class="input"
                                   placeholder=".product-guarantee"
                                   value="{{ old('selectors.product_page.guarantee.selector', $content['selectors']['product_page']['guarantee']['selector'] ?? '') }}">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Category Selectors -->
                        <div class="form-group">
                            <label class="label">سلکتورهای دسته‌بندی <span class="required">*</span></label>
                            <div id="category-selectors" class="space-y-2">
                                @php
                                    $categorySelectors = old('selectors.product_page.category.selector',
                                        isset($content['selectors']['product_page']['category']['selector']) && is_array($content['selectors']['product_page']['category']['selector'])
                                        ? $content['selectors']['product_page']['category']['selector']
                                        : (isset($content['selectors']['product_page']['category']['selector']) ? [$content['selectors']['product_page']['category']['selector']] : [''])
                                    );
                                    if (empty($categorySelectors)) $categorySelectors = [''];
                                @endphp
                                @foreach ($categorySelectors as $selector)
                                    <div class="flex-row">
                                        <input type="hidden" name="selectors[product_page][category][type]" value="css">
                                        <input type="text" name="selectors[product_page][category][selector][]"
                                               class="input" placeholder=".product-category" required
                                               value="{{ $selector }}">
                                        <button type="button" class="btn btn-primary add-category">+</button>
                                        <button type="button" class="btn btn-danger remove-category">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Price Selectors -->
                        <div class="form-group">
                            <label class="label">سلکتورهای قیمت <span class="required">*</span></label>
                            <div id="price-selectors" class="space-y-2">
                                @php
                                    $priceSelectors = old('selectors.product_page.price.selector',
                                        isset($content['selectors']['product_page']['price']['selector']) && is_array($content['selectors']['product_page']['price']['selector'])
                                        ? $content['selectors']['product_page']['price']['selector']
                                        : (isset($content['selectors']['product_page']['price']['selector']) ? [$content['selectors']['product_page']['price']['selector']] : [''])
                                    );
                                    if (empty($priceSelectors)) $priceSelectors = [''];
                                @endphp
                                @foreach ($priceSelectors as $selector)
                                    <div class="flex-row">
                                        <input type="hidden" name="selectors[product_page][price][type]" value="css">
                                        <input type="text" name="selectors[product_page][price][selector][]"
                                               class="input" placeholder=".product-price" required
                                               value="{{ $selector }}">
                                        <button type="button" class="btn btn-primary add-price">+</button>
                                        <button type="button" class="btn btn-danger remove-price">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Availability Selectors -->
                        <div class="form-group">
                            <label class="label">سلکتورهای موجودی <span class="required">*</span></label>
                            <div id="availability-selectors" class="space-y-2">
                                @php
                                    $availabilitySelectors = old('selectors.product_page.availability.selector',
                                        isset($content['selectors']['product_page']['availability']['selector']) && is_array($content['selectors']['product_page']['availability']['selector'])
                                        ? $content['selectors']['product_page']['availability']['selector']
                                        : (isset($content['selectors']['product_page']['availability']['selector']) ? [$content['selectors']['product_page']['availability']['selector']] : [''])
                                    );
                                    if (empty($availabilitySelectors)) $availabilitySelectors = [''];
                                @endphp
                                @foreach ($availabilitySelectors as $selector)
                                    <div class="flex-row">
                                        <input type="hidden" name="selectors[product_page][availability][type]"
                                               value="css">
                                        <input type="text" name="selectors[product_page][availability][selector][]"
                                               class="input" placeholder=".product-availability" required
                                               value="{{ $selector }}">
                                        <button type="button" class="btn btn-primary add-availability">+</button>
                                        <button type="button" class="btn btn-danger remove-availability">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Out of Stock Selectors -->
                        <div class="form-group">
                            <label class="flex items-center gap-3">
                                <input type="hidden" name="out_of_stock_button" value="0">
                                <input type="checkbox" name="out_of_stock_button" value="1" id="out-of-stock-button"
                                       class="w-5 h-5" {{ old('out_of_stock_button', $content['out_of_stock_button'] ?? false) ? 'checked' : '' }}>
                                <span>استفاده از دکمه ناموجود</span>
                            </label>
                        </div>

                        <div id="out-of-stock-container"
                             class="form-group {{ old('out_of_stock_button', $content['out_of_stock_button'] ?? false) ? '' : 'hidden' }}">
                            <label class="label">سلکتورهای ناموجودی</label>
                            <div id="out-of-stock-selectors" class="space-y-2">
                                @php
                                    $outOfStockSelectors = old('selectors.product_page.out_of_stock.selector',
                                        isset($content['selectors']['product_page']['out_of_stock']['selector']) && is_array($content['selectors']['product_page']['out_of_stock']['selector'])
                                        ? $content['selectors']['product_page']['out_of_stock']['selector']
                                        : (isset($content['selectors']['product_page']['out_of_stock']['selector']) ? [$content['selectors']['product_page']['out_of_stock']['selector']] : [''])
                                    );
                                    if (empty($outOfStockSelectors)) $outOfStockSelectors = [''];
                                @endphp
                                @foreach ($outOfStockSelectors as $selector)
                                    <div class="flex-row">
                                        <input type="hidden" name="selectors[product_page][out_of_stock][type]"
                                               value="css">
                                        <input type="text" name="selectors[product_page][out_of_stock][selector][]"
                                               class="input" placeholder=".out-of-stock" value="{{ $selector }}">
                                        <button type="button" class="btn btn-primary add-out-of-stock">+</button>
                                        <button type="button" class="btn btn-danger remove-out-of-stock">−</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Product ID Selectors -->
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="label">سلکتورهای شناسه محصول <span class="required">*</span></label>
                                <div id="product-id-selectors" class="space-y-2">
                                    @php
                                        $productIdSelectors = old('selectors.product_page.product_id.selector',
                                            isset($content['selectors']['product_page']['product_id']['selector']) && is_array($content['selectors']['product_page']['product_id']['selector'])
                                            ? $content['selectors']['product_page']['product_id']['selector']
                                            : (isset($content['selectors']['product_page']['product_id']['selector']) ? [$content['selectors']['product_page']['product_id']['selector']] : [''])
                                        );
                                        if (empty($productIdSelectors)) $productIdSelectors = [''];
                                    @endphp
                                    @foreach ($productIdSelectors as $selector)
                                        <div class="flex-row">
                                            <input type="hidden" name="selectors[product_page][product_id][type]"
                                                   value="css">
                                            <input type="text" name="selectors[product_page][product_id][selector][]"
                                                   class="input" placeholder=".product-id" required
                                                   value="{{ $selector }}">
                                            <button type="button" class="btn btn-primary add-product-id">+</button>
                                            <button type="button" class="btn btn-danger remove-product-id">−</button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="label">صفت‌های شناسه محصول <span class="required">*</span></label>
                                <div id="product-id-attributes" class="space-y-2">
                                    @php
                                        $productIdAttributes = old('selectors.product_page.product_id.attribute',
                                            isset($content['selectors']['product_page']['product_id']['attribute']) && is_array($content['selectors']['product_page']['product_id']['attribute'])
                                            ? $content['selectors']['product_page']['product_id']['attribute']
                                            : (isset($content['selectors']['product_page']['product_id']['attribute']) ? [$content['selectors']['product_page']['product_id']['attribute']] : ['data-id'])
                                        );
                                        if (empty($productIdAttributes)) $productIdAttributes = ['data-id'];
                                    @endphp
                                    @foreach ($productIdAttributes as $attribute)
                                        <div class="flex-row">
                                            <input type="text" name="selectors[product_page][product_id][attribute][]"
                                                   class="input" placeholder="data-id"
                                                   value="{{ $attribute }}">
                                            <button type="button" class="btn btn-primary add-product-id-attr">+</button>
                                            <button type="button" class="btn btn-danger remove-product-id-attr">−
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="text-sm text-gray-600 bg-gray-50 p-4 rounded-lg">
                            <p><strong>نکته:</strong> تعداد سلکتورها و صفت‌ها باید برابر باشد. سیستم هر سلکتور را با صفت
                                متناظرش تطبیق می‌دهد.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="text-center">
            <button type="submit" class="btn btn-primary text-lg px-8 py-3">
                <svg class="icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
                به‌روزرسانی کانفیگ
            </button>
            <a href="{{ route('configs.index') }}" class="btn btn-secondary text-lg px-8 py-3 mr-4">
                بازگشت
            </a>
        </div>
    </form>
</div>

<script>
    // Theme Management
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    let isDark = false;

    themeToggle.addEventListener('click', () => {
        isDark = !isDark;
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');

        themeIcon.innerHTML = isDark
            ? '<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>';
    });

    // Tab Management
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;

            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.add('hidden'));

            tab.classList.add('active');
            document.getElementById(`tab-${targetTab}`).classList.remove('hidden');
            document.getElementById(`tab-${targetTab}`).classList.add('fade-in');
        });
    });

    // Method Selection
    const methodCards = document.querySelectorAll('.method-card');
    const paginationSettings = document.getElementById('pagination-settings');
    const webdriverSettings = document.getElementById('webdriver-settings');

    methodCards.forEach(card => {
        card.addEventListener('click', () => {
            const method = card.dataset.method;

            methodCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            card.querySelector('input[type="radio"]').checked = true;

            // Show/hide method-specific fields
            document.querySelectorAll('.method-2-field, .method-3-field').forEach(field => {
                field.classList.add('hidden');
            });

            if (method === '1') {
                paginationSettings.classList.remove('hidden');
                webdriverSettings.classList.add('hidden');
            } else {
                paginationSettings.classList.add('hidden');
                webdriverSettings.classList.remove('hidden');

                if (method === '2') {
                    document.querySelectorAll('.method-2-field').forEach(field => {
                        field.classList.remove('hidden');
                    });
                } else if (method === '3') {
                    document.querySelectorAll('.method-3-field').forEach(field => {
                        field.classList.remove('hidden');
                    });
                }
            }
        });
    });

    // Product ID Source Change
    const productIdSource = document.getElementById('product-id-source');
    const mainPageContainer = document.getElementById('main-page-product-id-container');

    if (productIdSource && mainPageContainer) {
        productIdSource.addEventListener('change', () => {
            if (productIdSource.value === 'main_page') {
                mainPageContainer.classList.remove('hidden');
                mainPageContainer.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
            } else {
                mainPageContainer.classList.add('hidden');
                mainPageContainer.querySelectorAll('input').forEach(input => {
                    input.required = false;
                });
            }
        });
    }

    // Pagination Method Change
    const paginationMethod = document.getElementById('pagination-method');
    const nextButtonContainer = document.getElementById('next-button-container');
    const urlPaginationContainer = document.getElementById('url-pagination-container');

    if (paginationMethod) {
        paginationMethod.addEventListener('change', () => {
            if (paginationMethod.value === 'next_button') {
                nextButtonContainer.classList.remove('hidden');
                urlPaginationContainer.classList.add('hidden');
            } else {
                nextButtonContainer.classList.add('hidden');
                urlPaginationContainer.classList.remove('hidden');
            }
        });
    }

    // Sample URL toggles
    const paginationUseSampleUrl = document.getElementById('pagination-use-sample-url');
    const sampleUrlContainer = document.getElementById('sample-url-container');

    if (paginationUseSampleUrl) {
        paginationUseSampleUrl.addEventListener('change', () => {
            sampleUrlContainer.classList.toggle('hidden', !paginationUseSampleUrl.checked);
            const input = sampleUrlContainer.querySelector('input');
            if (input) {
                input.required = paginationUseSampleUrl.checked;
            }
        });
    }

    const paginationUseSampleUrlWebdriver = document.getElementById('pagination-use-sample-url-webdriver');
    const paginationSampleUrlContainer = document.getElementById('pagination-sample-url-container');

    if (paginationUseSampleUrlWebdriver) {
        paginationUseSampleUrlWebdriver.addEventListener('change', () => {
            paginationSampleUrlContainer.classList.toggle('hidden', !paginationUseSampleUrlWebdriver.checked);
            const input = paginationSampleUrlContainer.querySelector('input');
            if (input) {
                input.required = paginationUseSampleUrlWebdriver.checked;
            }
        });
    }

    // Set Category Toggle
    const useSetCategory = document.getElementById('use-set-category');
    const setCategoryField = document.getElementById('set-category-field');

    if (useSetCategory && setCategoryField) {
        useSetCategory.addEventListener('change', () => {
            setCategoryField.classList.toggle('hidden', !useSetCategory.checked);
            const input = setCategoryField.querySelector('input');
            if (input) {
                input.required = useSetCategory.checked;
            }
        });
    }

    // Out of Stock Toggle
    const outOfStockButton = document.getElementById('out-of-stock-button');
    const outOfStockContainer = document.getElementById('out-of-stock-container');

    if (outOfStockButton && outOfStockContainer) {
        outOfStockButton.addEventListener('change', () => {
            outOfStockContainer.classList.toggle('hidden', !outOfStockButton.checked);
        });
    }

    // Dynamic Field Management
    function createFieldManager(containerId, nameAttribute, placeholder = '', isRequired = false) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.addEventListener('click', (e) => {
            const target = e.target;

            // Add button clicked
            if (target.classList.contains('add-url') ||
                target.classList.contains('add-product-url') ||
                target.classList.contains('add-category') ||
                target.classList.contains('add-price') ||
                target.classList.contains('add-availability') ||
                target.classList.contains('add-out-of-stock') ||
                target.classList.contains('add-product-id') ||
                target.classList.contains('add-product-id-attr') ||
                target.classList.contains('add-guarantee') ||
                target.classList.contains('add-positive') ||
                target.classList.contains('add-negative') ||
                target.classList.contains('add-unpriced')) {

                const newRow = document.createElement('div');
                newRow.className = 'flex-row';

                let hiddenField = '';
                if (nameAttribute.includes('selector')) {
                    hiddenField = `<input type="hidden" name="${nameAttribute.replace('[]', '').replace('[selector]', '[type]')}" value="css">`;
                }

                newRow.innerHTML = `
                        ${hiddenField}
                        <input type="text" name="${nameAttribute}" class="input" placeholder="${placeholder}" ${isRequired ? 'required' : ''}>
                        <button type="button" class="btn btn-danger remove-field">−</button>
                    `;
                container.appendChild(newRow);
            }

            // Remove button clicked
            if (target.classList.contains('remove-field') ||
                target.classList.contains('remove-url') ||
                target.classList.contains('remove-product-url') ||
                target.classList.contains('remove-category') ||
                target.classList.contains('remove-price') ||
                target.classList.contains('remove-availability') ||
                target.classList.contains('remove-out-of-stock') ||
                target.classList.contains('remove-product-id') ||
                target.classList.contains('remove-product-id-attr') ||
                target.classList.contains('remove-guarantee') ||
                target.classList.contains('remove-positive') ||
                target.classList.contains('remove-negative') ||
                target.classList.contains('remove-unpriced') ||
                target.classList.contains('remove-title-prefix')) {

                if (container.children.length > 1) {
                    target.closest('.flex-row, .grid-2').remove();
                }
            }
        });
    }

    // Initialize field managers
    createFieldManager('base-urls', 'base_urls[]', 'https://example.com', true);
    createFieldManager('product-urls', 'products_urls[]', 'https://example.com/product/123', true);
    createFieldManager('category-selectors', 'selectors[product_page][category][selector][]', '.product-category', true);
    createFieldManager('price-selectors', 'selectors[product_page][price][selector][]', '.product-price', true);
    createFieldManager('availability-selectors', 'selectors[product_page][availability][selector][]', '.product-availability', true);
    createFieldManager('out-of-stock-selectors', 'selectors[product_page][out_of_stock][selector][]', '.out-of-stock');
    createFieldManager('product-id-selectors', 'selectors[product_page][product_id][selector][]', '.product-id', true);
    createFieldManager('product-id-attributes', 'selectors[product_page][product_id][attribute][]', 'data-id', true);
    createFieldManager('guarantee-keywords', 'guarantee_keywords[]', '', true);
    createFieldManager('availability-positive', 'availability_keywords[positive][]', '', true);
    createFieldManager('availability-negative', 'availability_keywords[negative][]', '', true);
    createFieldManager('price-unpriced', 'price_keywords[unpriced][]', '', true);

    // Title Prefix Rules Management
    const titlePrefixContainer = document.getElementById('title-prefix-rules');
    const addTitlePrefixBtn = document.querySelector('.add-title-prefix');

    if (addTitlePrefixBtn) {
        addTitlePrefixBtn.addEventListener('click', () => {
            const newRule = document.createElement('div');
            newRule.className = 'grid-2 gap-4';
            newRule.innerHTML = `
                    <div class="flex-row">
                        <input type="url" name="title_prefix_rules[url][]" class="input" placeholder="https://example.com/fa/book/">
                        <button type="button" class="btn btn-danger remove-title-prefix">−</button>
                    </div>
                    <div class="flex-row">
                        <input type="text" name="title_prefix_rules[prefix][]" class="input" placeholder="کتاب">
                    </div>
                `;
            titlePrefixContainer.appendChild(newRule);
        });
    }

    if (titlePrefixContainer) {
        titlePrefixContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-title-prefix')) {
                if (titlePrefixContainer.children.length > 1) {
                    e.target.closest('.grid-2').remove();
                }
            }
        });
    }

    // Form Validation Enhancement
    function validateForm() {
        const requiredFields = document.querySelectorAll('input[required], select[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = 'var(--danger)';
                isValid = false;
            } else {
                field.style.borderColor = 'var(--border)';
            }
        });

        return isValid;
    }

    // Add real-time validation
    document.addEventListener('input', (e) => {
        if (e.target.hasAttribute('required')) {
            if (e.target.value.trim()) {
                e.target.style.borderColor = 'var(--success)';
            } else {
                e.target.style.borderColor = 'var(--danger)';
            }
        }
    });

    // Form Submission
    document.getElementById('config-form').addEventListener('submit', (e) => {
        if (!validateForm()) {
            e.preventDefault();
            alert('لطفاً همه فیلدهای اجباری را پر کنید.');
            return;
        }

        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = `
                <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                در حال به‌روزرسانی...
            `;
        submitBtn.disabled = true;
    });

    // Initialize method-specific visibility on page load
    const currentMethod = document.querySelector('input[name="method"]:checked');
    if (currentMethod) {
        const method = currentMethod.value;

        // Show/hide method-specific fields
        document.querySelectorAll('.method-2-field, .method-3-field').forEach(field => {
            field.classList.add('hidden');
        });

        if (method === '1') {
            paginationSettings.classList.remove('hidden');
            webdriverSettings.classList.add('hidden');
        } else {
            paginationSettings.classList.add('hidden');
            webdriverSettings.classList.remove('hidden');

            if (method === '2') {
                document.querySelectorAll('.method-2-field').forEach(field => {
                    field.classList.remove('hidden');
                });
            } else if (method === '3') {
                document.querySelectorAll('.method-3-field').forEach(field => {
                    field.classList.remove('hidden');
                });
            }
        }
    }

    // Initialize pagination method visibility
    if (paginationMethod) {
        const currentPaginationMethod = paginationMethod.value;
        if (currentPaginationMethod === 'next_button') {
            nextButtonContainer.classList.remove('hidden');
            urlPaginationContainer.classList.add('hidden');
        } else {
            nextButtonContainer.classList.add('hidden');
            urlPaginationContainer.classList.remove('hidden');
        }
    }

    console.log('✅ فرم ویرایش بهینه شده و کامل آماده است!');
</script>
</body>
</html>
