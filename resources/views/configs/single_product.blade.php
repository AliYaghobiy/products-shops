<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست محصول واحد</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/vazirmatn/33.0.3/Vazirmatn-font-face.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: {
                            50: '#FFFBF0',
                            100: '#FFF5E1',
                            200: '#FFECC3',
                            300: '#FFE0A1',
                            400: '#FFD47F',
                            500: '#FFC857',
                            600: '#FFBC30',
                            700: '#FFA800',
                            800: '#CC8600',
                            900: '#996400',
                        },
                        brown: {
                            50: '#F9F6F3',
                            100: '#F3EDE7',
                            200: '#E7DBCF',
                            300: '#D9C9B6',
                            400: '#CAB59C',
                            500: '#BBA183',
                            600: '#A88C6A',
                            700: '#8D7455',
                            800: '#6F5B42',
                            900: '#524330',
                        }
                    },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: "Vazirmatn", system-ui, sans-serif;
            background-color: #F9F6F3;
            color: #524330;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23d5ad85' fill-opacity='0.08' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        .card {
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #E7DBCF;
        }

        .input-field {
            background-color: #fff;
            border: 1px solid #E7DBCF;
            color: #524330;
        }

        .input-field:focus {
            border-color: #BBA183;
            box-shadow: 0 0 0 2px rgba(187, 161, 131, 0.2);
        }

        .btn-primary {
            background-color: #A88C6A;
            color: white;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #8D7455;
        }

        .btn-secondary {
            background-color: #D9C9B6;
            color: #524330;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: #CAB59C;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
            transition: all 0.2s ease;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .section-header {
            border-bottom: 1px solid #E7DBCF;
            padding-bottom: 0.75rem;
            margin-bottom: 1.25rem;
            font-weight: 700;
            color: #8D7455;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .tabs {
            display: flex;
            flex-wrap: wrap;
            border-bottom: 2px solid #E7DBCF;
            margin-bottom: 1.5rem;
        }

        .tab-button {
            flex: 1;
            padding: 0.75rem 1rem;
            text-align: center;
            cursor: pointer;
            font-weight: 500;
            color: #524330;
            transition: all 0.2s ease;
        }

        .tab-button:hover {
            background-color: #FFF5E1;
        }

        .tab-button.active {
            background-color: #FFF5E1;
            color: #996400;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .result-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .result-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .log-line {
            margin: 2px 0;
            font-family: monospace;
            font-size: 0.875rem;
        }

        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-warning { color: #ffc107; }
        .log-info { color: #17a2b8; }
        .log-primary { color: #007bff; }
        .log-purple { color: #6f42c1; }
        .log-cyan { color: #17a2b8; }
        .log-gray { color: #6c757d; }
    </style>
</head>
<body class="min-h-screen py-8">
<div class="container mx-auto px-4 max-w-6xl">
    <div class="card p-8">
        <!-- Header -->
        <h1 class="text-3xl font-bold text-brown-700 mb-8 pb-4 border-b border-brown-200 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 ml-3 text-brown-600" viewBox="0 0 20 20"
                 fill="currentColor">
                <path fill-rule="evenodd"
                      d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"
                      clip-rule="evenodd"/>
            </svg>
            تست محصول واحد
        </h1>

        <!-- نمایش خطاها -->
        @if ($errors->any())
            <div class="bg-red-50 border border-red-300 text-red-800 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-2 text-red-600" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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

        <form method="POST" action="{{ route('configs.single_product') }}">
            @csrf

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab-button active" data-tab="basic-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline ml-2" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    تنظیمات پایه
                </div>
                <div class="tab-button" data-tab="selectors-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline ml-2" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    سلکتورها
                </div>
                <div class="tab-button" data-tab="keywords-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline ml-2" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    کلمات کلیدی
                </div>
            </div>

            <!-- Tab Content: Basic Settings -->
            <div id="basic-settings" class="tab-content active">
                <div class="card p-6 mb-6">
        <h2 class="section-header text-xl">تنظیمات پایه</h2>

        <div class="form-group">
            <label for="product_url" class="block text-sm font-medium text-brown-700 mb-2">
                آدرس محصول <span class="text-red-500">*</span>
            </label>
            <input type="url" name="product_url" id="product_url"
                   value="{{ old('product_url', 'https://yaradarman.com/product/advanced-wound-healing-gel/') }}"
                   class="input-field w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                   placeholder="https://example.com/product/sample" required>
        </div>

        <div class="form-group">
            <label class="inline-flex items-center">
                <input type="hidden" name="out_of_stock_button" value="0">
                <input type="checkbox" name="out_of_stock_button" value="1" id="out_of_stock_button"
                       class="w-5 h-5 text-brown-600 border-brown-300 rounded focus:ring-brown-500 bg-white"
                    {{ old('out_of_stock_button') ? 'checked' : '' }}>
                <span class="mr-3 text-brown-700">استفاده از دکمه ناموجود</span>
            </label>
        </div>
    </div>
</div>

<!-- Tab Content: Selectors -->
<div id="selectors-settings" class="tab-content">
    <div class="card p-6 mb-6">
        <h2 class="section-header text-xl">سلکتورهای صفحه محصول</h2>

        <!-- عنوان محصول -->
        <div class="form-group">
            <label for="title_selector" class="block text-sm font-medium text-brown-700 mb-2">
                سلکتور عنوان <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title_selector" id="title_selector"
                   value="{{ old('title_selector', '.elementor-element-e499a81 > div:nth-child(1) > h1:nth-child(1)') }}"
                   class="input-field w-full px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                   placeholder=".product-title" required>
        </div>

        <!-- سلکتورهای قیمت -->
        <div class="form-group">
            <label class="block text-sm font-medium text-brown-700 mb-2">
                سلکتورهای قیمت <span class="text-red-500">*</span>
            </label>
            <div class="price-selectors-container space-y-3">
                <div class="flex">
                    <input type="text" name="price_selector[]"
                           value="{{ old('price_selector.0', 'p.price > span:nth-child(1) > bdi:nth-child(1)') }}"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder=".product-price" required>
                    <button type="button"
                            class="add-price-selector mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- سلکتورهای دسته‌بندی -->
        <div class="form-group">
            <label class="block text-sm font-medium text-brown-700 mb-2">
                سلکتورهای دسته‌بندی
            </label>
            <div class="category-selectors-container space-y-3">
                <div class="flex">
                    <input type="text" name="category_selector[]"
                           value="{{ old('category_selector.0', '.posted_in > a:nth-child(2)') }}"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder=".product-category">
                    <button type="button"
                            class="add-category-selector mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <button type="button"
                            class="remove-category-selector mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- سلکتورهای موجودی -->
        <div class="form-group">
            <label class="block text-sm font-medium text-brown-700 mb-2">
                سلکتورهای موجودی
            </label>
            <div class="availability-selectors-container space-y-3">
                <div class="flex">
                    <input type="text" name="availability_selector[]"
                           value="{{ old('availability_selector.0', '.elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)') }}"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder=".product-availability">
                    <button type="button"
                            class="add-availability-selector mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <button type="button"
                            class="remove-availability-selector mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- سلکتورهای ناموجودی -->
        <div id="out-of-stock-container" class="form-group {{ old('out_of_stock_button') ? '' : 'hidden' }}">
            <label class="block text-sm font-medium text-brown-700 mb-2">
                سلکتورهای ناموجودی <span class="text-red-500">*</span>
            </label>
            <div class="out-of-stock-selectors-container space-y-3">
                <div class="flex">
                    <input type="text" name="out_of_stock_selector[]"
                           value="{{ old('out_of_stock_selector.0', '.testselector1') }}"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder=".out-of-stock">
                    <button type="button"
                            class="add-out-of-stock-selector mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <button type="button"
                            class="remove-out-of-stock-selector mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Grid for other selectors -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- تصویر -->
            <div class="form-group">
                <label for="image_selector" class="block text-sm font-medium text-brown-700 mb-2">
                    سلکتور تصویر
                </label>
                <input type="text" name="image_selector" id="image_selector"
                       value="{{ old('image_selector', '.woocommerce-product-gallery__image > a') }}"
                       class="input-field w-full px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                       placeholder=".product-image">
            </div>

            <!-- صفت تصویر -->
            <div class="form-group">
                <label for="image_attribute" class="block text-sm font-medium text-brown-700 mb-2">
                    صفت تصویر
                </label>
                <input type="text" name="image_attribute" id="image_attribute"
                       value="{{ old('image_attribute', 'href') }}"
                       class="input-field w-full px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                       placeholder="src">
            </div>

            <!-- تخفیف -->
            <div class="form-group">
                <label for="off_selector" class="block text-sm font-medium text-brown-700 mb-2">
                    سلکتور تخفیف
                </label>
                <input type="text" name="off_selector" id="off_selector"
                       value="{{ old('off_selector', '.discount') }}"
                       class="input-field w-full px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                       placeholder=".product-discount">
            </div>

            <!-- گارانتی -->
            <div class="form-group">
                <label for="guarantee_selector" class="block text-sm font-medium text-brown-700 mb-2">
                    سلکتور گارانتی
                </label>
                <input type="text" name="guarantee_selector" id="guarantee_selector"
                       value="{{ old('guarantee_selector', '.selector') }}"
                       class="input-field w-full px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                       placeholder=".product-guarantee">
            </div>
        </div>

        <!-- Product ID Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- سلکتورهای شناسه محصول -->
            <div class="form-group">
                <label class="block text-sm font-medium text-brown-700 mb-2">
                    سلکتورهای شناسه محصول
                </label>
                <div class="product-id-selectors-container space-y-3">
                    <div class="flex">
                        <input type="text" name="product_id_selector[]"
                               value="{{ old('product_id_selector.0', '.elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)') }}"
                               class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                               placeholder=".product-id">
                        <button type="button"
                                class="add-product-id-selector mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <button type="button"
                                class="remove-product-id-selector mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- صفت‌های شناسه محصول -->
            <div class="form-group">
                <label class="block text-sm font-medium text-brown-700 mb-2">
                    صفت‌های شناسه محصول
                </label>
                <div class="product-id-attributes-container space-y-3">
                    <div class="flex">
                        <input type="text" name="product_id_attribute[]"
                               value="{{ old('product_id_attribute.0', 'value') }}"
                               class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                               placeholder="data-id">
                        <button type="button"
                                class="add-product-id-attribute mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <button type="button"
                                class="remove-product-id-attribute mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Content: Keywords -->
<div id="keywords-settings" class="tab-content">
    <div class="card p-6 mb-6">
        <h2 class="section-header text-xl">کلمات کلیدی</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- کلمات کلیدی گارانتی -->
            <div class="form-group">
                <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی گارانتی</label>
                <div class="guarantee-keywords-container space-y-3">
                    <div class="flex">
                        <input type="text" name="guarantee_keywords[]"
                               value="{{ old('guarantee_keywords.0', 'گارانتی') }}"
                               class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200">
                        <button type="button"
                                class="add-guarantee-keyword mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- کلمات کلیدی موجودی (مثبت) -->
            <div class="form-group">
                <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی موجودی (مثبت)</label>
                <div class="availability-positive-container space-y-3">
                    <div class="flex">
                        <input type="text" name="availability_keywords_positive[]"
                               value="{{ old('availability_keywords_positive.0', 'موجود') }}"
                               class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200">
                        <button type="button"
                                class="add-availability-positive mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- کلمات کلیدی موجودی (منفی) -->
            <div class="form-group">
                <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی موجودی (منفی)</label>
                <div class="availability-negative-container space-y-3">
                    <div class="flex">
                        <input type="text" name="availability_keywords_negative[]"
                               value="{{ old('availability_keywords_negative.0', 'ناموجود') }}"
                               class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200">
                        <button type="button"
                                class="add-availability-negative mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- کلمات کلیدی قیمت (بدون قیمت) -->
            <div class="form-group">
                <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی قیمت (بدون قیمت)</label>
                <div class="price-unpriced-container space-y-3">
                    <div class="flex">
                        <input type="text" name="price_keywords_unpriced[]"
                               value="{{ old('price_keywords_unpriced.0', 'تماس بگیرید') }}"
                               class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200">
                        <button type="button"
                                class="add-price-unpriced mr-2 btn-primary px-3 py-2 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit Button -->
<div class="flex justify-center mt-8">
    <button type="submit"
            class="btn-primary text-lg font-bold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-brown-500 focus:ring-opacity-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline ml-2" fill="none" viewBox="0 0 24 24"
             stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        تست محصول
    </button>
</div>
</form>

<!-- نمایش نتایج -->
@if (isset($result))
    <div class="mt-8">
        <div class="card p-6 {{ $result['status'] === 'success' ? 'result-success' : 'result-error' }}">
            <h3 class="text-xl font-bold mb-4 flex items-center">
                @if($result['status'] === 'success')
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-2 text-green-600" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-2 text-red-600" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
                نتیجه تست
            </h3>

            @if($result['status'] === 'success' && isset($result['test_mode']) && $result['test_mode'])
                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-3">آمار کلی</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white p-4 rounded-lg border">
                            <div class="text-2xl font-bold text-blue-600">{{ $result['total_tested'] ?? 0 }}</div>
                            <div class="text-sm text-gray-600">تعداد تست شده</div>
                        </div>
                        <div class="bg-white p-4 rounded-lg border">
                            <div class="text-2xl font-bold text-green-600">{{ $result['total_products'] ?? 0 }}</div>
                            <div class="text-sm text-gray-600">موفقیت آمیز</div>
                        </div>
                        <div class="bg-white p-4 rounded-lg border">
                            <div class="text-2xl font-bold text-red-600">{{ $result['failed_links'] ?? 0 }}</div>
                            <div class="text-sm text-gray-600">ناموفق</div>
                        </div>
                        <div class="bg-white p-4 rounded-lg border">
                            <div class="text-2xl font-bold text-purple-600">{{ $result['success_rate'] ?? 0 }}%</div>
                            <div class="text-sm text-gray-600">نرخ موفقیت</div>
                        </div>
                    </div>
                </div>
            @endif

            @if(isset($result['products']) && count($result['products']) > 0)
                <h4 class="text-lg font-semibold mb-3">محصولات استخراج شده</h4>
                @foreach($result['products'] as $index => $product)
                    <div class="bg-white p-4 rounded-lg border mb-4">
                        <h5 class="font-bold text-lg mb-2">محصول {{ $index + 1 }}</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if(!empty($product['title']))
                                <div>
                                    <strong>عنوان:</strong>
                                    <span class="text-blue-600">{{ $product['title'] }}</span>
                                </div>
                            @endif
                            @if(!empty($product['price']))
                                <div>
                                    <strong>قیمت:</strong>
                                    <span class="text-green-600">{{ $product['price'] }}</span>
                                </div>
                            @endif
                            @if(isset($product['availability']))
                                <div>
                                    <strong>موجودی:</strong>
                                    <span class="{{ $product['availability'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $product['availability'] ? 'موجود' : 'ناموجود' }}
                                            </span>
                                </div>
                            @endif
                            @if(!empty($product['category']))
                                <div>
                                    <strong>دسته‌بندی:</strong>
                                    <span class="text-purple-600">{{ $product['category'] }}</span>
                                </div>
                            @endif
                            @if(!empty($product['product_id']))
                                <div>
                                    <strong>شناسه محصول:</strong>
                                    <span class="text-indigo-600">{{ $product['product_id'] }}</span>
                                </div>
                            @endif
                            @if(!empty($product['guarantee']))
                                <div>
                                    <strong>گارانتی:</strong>
                                    <span class="text-orange-600">{{ $product['guarantee'] }}</span>
                                </div>
                            @endif
                            @if(!empty($product['image']))
                                <div>
                                    <strong>تصویر:</strong>
                                    <a href="{{ $product['image'] }}" target="_blank" class="text-blue-500 hover:underline">
                                        مشاهده تصویر
                                    </a>
                                </div>
                            @endif
                            @if(isset($product['off']) && $product['off'] > 0)
                                <div>
                                    <strong>تخفیف:</strong>
                                    <span class="text-red-600">{{ $product['off'] }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            @if(isset($result['message']))
                <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                    <strong>پیام:</strong> {{ $result['message'] }}
                </div>
            @endif

            @if(isset($result['failed_urls']) && count($result['failed_urls']) > 0)
                <div class="mt-4">
                    <h4 class="text-lg font-semibold mb-3 text-red-600">آدرس‌های ناموفق</h4>
                    <ul class="list-disc mr-6">
                        @foreach($result['failed_urls'] as $failedUrl)
                            <li class="text-red-600">{{ $failedUrl }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- نمایش لاگ‌ها -->
        @if (isset($logs) && count($logs) > 0)
            <div class="mt-6">
                <div class="card p-6">
                    <h3 class="text-xl font-bold mb-4">لاگ‌های اجرا</h3>
                    <div class="bg-black text-white p-4 rounded-lg overflow-auto max-h-96 font-mono text-sm">
                        @foreach($logs as $log)
                            <div class="log-line">{{ $log }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endif
    </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');

                // Remove active class from all tabs and content
                document.querySelectorAll('.tab-button').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(targetTab).classList.add('active');
            });
        });

        // Out of stock button toggle
        document.getElementById('out_of_stock_button').addEventListener('change', function() {
            const container = document.getElementById('out-of-stock-container');
            if (this.checked) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        });

        // Dynamic field addition/removal functions
        function createAddRemoveHandler(containerSelector, inputName, placeholder = '') {
            const container = document.querySelector(containerSelector);

            // Add button handler
            container.addEventListener('click', function(e) {
                if (e.target.closest('.add-' + inputName.replace(/[\[\]]/g, ''))) {
                    e.preventDefault();
                    const newRow = document.createElement('div');
                    newRow.className = 'flex mt-3';
                    newRow.innerHTML = `
                    <input type="text" name="${inputName}"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder="${placeholder}">
                    <button type="button" class="remove-${inputName.replace(/[\[\]]/g, '')} mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                `;
                    container.appendChild(newRow);
                }
            });

            // Remove button handler
            container.addEventListener('click', function(e) {
                if (e.target.closest('.remove-' + inputName.replace(/[\[\]]/g, ''))) {
                    e.preventDefault();
                    const row = e.target.closest('.flex');
                    if (container.children.length > 1) {
                        row.remove();
                    }
                }
            });
        }

        // Initialize all dynamic fields
        createAddRemoveHandler('.price-selectors-container', 'price_selector[]', '.product-price');
        createAddRemoveHandler('.category-selectors-container', 'category_selector[]', '.product-category');
        createAddRemoveHandler('.availability-selectors-container', 'availability_selector[]', '.product-availability');
        createAddRemoveHandler('.out-of-stock-selectors-container', 'out_of_stock_selector[]', '.out-of-stock');
        createAddRemoveHandler('.product-id-selectors-container', 'product_id_selector[]', '.product-id');
        createAddRemoveHandler('.product-id-attributes-container', 'product_id_attribute[]', 'data-id');
        createAddRemoveHandler('.guarantee-keywords-container', 'guarantee_keywords[]', 'گارانتی');
        createAddRemoveHandler('.availability-positive-container', 'availability_keywords_positive[]', 'موجود');
        createAddRemoveHandler('.availability-negative-container', 'availability_keywords_negative[]', 'ناموجود');
        createAddRemoveHandler('.price-unpriced-container', 'price_keywords_unpriced[]', 'تماس بگیرید');

        // Sync product ID selectors and attributes
        document.addEventListener('DOMContentLoaded', function() {
            function syncProductIdFields() {
                const selectorInputs = document.querySelectorAll('input[name="product_id_selector[]"]');
                const attributeInputs = document.querySelectorAll('input[name="product_id_attribute[]"]');

                const selectorContainer = document.querySelector('.product-id-selectors-container');
                const attributeContainer = document.querySelector('.product-id-attributes-container');

                // Make sure we have the same number of fields
                const maxLength = Math.max(selectorInputs.length, attributeInputs.length);

                // Add missing selector fields
                while (selectorContainer.children.length < maxLength) {
                    const newRow = document.createElement('div');
                    newRow.className = 'flex mt-3';
                    newRow.innerHTML = `
                    <input type="text" name="product_id_selector[]"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder=".product-id">
                    <button type="button" class="remove-product-id-selector mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                `;
                    selectorContainer.appendChild(newRow);
                }

                // Add missing attribute fields
                while (attributeContainer.children.length < maxLength) {
                    const newRow = document.createElement('div');
                    newRow.className = 'flex mt-3';
                    newRow.innerHTML = `
                    <input type="text" name="product_id_attribute[]"
                           class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                           placeholder="data-id" value="value">
                    <button type="button" class="remove-product-id-attribute mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                `;
                    attributeContainer.appendChild(newRow);
                }
            }

            // Initial sync
            syncProductIdFields();

            // Sync when selectors are added
            document.querySelector('.add-product-id-selector').addEventListener('click', function() {
                setTimeout(syncProductIdFields, 100);
            });

            // Sync when attributes are added
            document.querySelector('.add-product-id-attribute').addEventListener('click', function() {
                setTimeout(syncProductIdFields, 100);
            });
        });
    </script>
    </body>
    </html>



