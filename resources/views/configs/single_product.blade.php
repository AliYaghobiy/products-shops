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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .tabs {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        .tab-button {
            flex: 1;
            min-width: 200px;
            padding: 20px 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-bottom: 3px solid transparent;
        }

        .tab-button:hover {
            background: #e2e8f0;
            color: #4f46e5;
        }

        .tab-button.active {
            color: #4f46e5;
            background: white;
            border-bottom-color: #4f46e5;
        }

        .tab-content {
            display: none;
            padding: 40px;
            background: #f8fafc;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .section-header {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .required {
            color: #ef4444;
            margin-right: 5px;
        }

        .input-field {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fff;
        }

        .input-field:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f1f5f9;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-container:hover {
            background: #e2e8f0;
        }

        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #4f46e5;
        }

        .selector-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .selector-container .input-field {
            flex: 1;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #10b981;
            color: white;
            padding: 15px 30px;
            font-size: 16px;
            border-radius: 10px;
            margin-top: 30px;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .grid {
            display: grid;
            gap: 25px;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .hidden {
            display: none !important;
        }

        .space-y-3 > * + * {
            margin-top: 12px;
        }

        .icon {
            width: 20px;
            height: 20px;
        }

        /* خصوصی‌سازی رنگ‌ها بر اساس تصویر */
        .tab-button.active,
        .tab-button:hover {
            color: #7c3aed;
        }

        .tab-button.active {
            border-bottom-color: #7c3aed;
        }

        .input-field:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .checkbox-container input[type="checkbox"] {
            accent-color: #7c3aed;
        }

        .btn-primary {
            background: #7c3aed;
        }

        .btn-primary:hover {
            background: #6d28d9;
        }

        .btn-success {
            background: #059669;
        }

        .btn-success:hover {
            background: #047857;
            box-shadow: 0 5px 15px rgba(5, 150, 105, 0.3);
        }

        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }

            .tab-button {
                min-width: auto;
                text-align: right;
            }

            .grid-cols-2 {
                grid-template-columns: 1fr;
            }

            .selector-container {
                flex-direction: column;
                align-items: stretch;
            }

            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .tab-content {
                padding: 20px;
            }
        }

        /* انیمیشن‌های اضافی */
        .card {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
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

        <!-- فرم -->
        <form method="POST" action="{{ route('configs.single_product') }}" id="product-test-form">
            @csrf

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab-button active" data-tab="basic-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    تنظیمات پایه
                </div>
                <div class="tab-button" data-tab="selectors-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    سلکتورها
                </div>
                <div class="tab-button" data-tab="keywords-settings">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    کلمات کلیدی
                </div>
            </div>

            <!-- Tab Content: Basic Settings -->
            <div id="basic-settings" class="tab-content active">
                <div class="card">
                    <h2 class="section-header">
                        ⚙️ تنظیمات پایه
                    </h2>

                    <div class="form-group">
                        <label for="product_url">
                            آدرس محصول <span class="required">*</span>
                        </label>
                        <input type="url" name="product_url" id="product_url"
                               value="https://yaradarman.com/product/advanced-wound-healing-gel/"
                               class="input-field"
                               placeholder="https://example.com/product/sample" required>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-container">
                            <input type="hidden" name="out_of_stock_button" value="0">
                            <input type="checkbox" name="out_of_stock_button" value="1" id="out_of_stock_button">
                            <label for="out_of_stock_button">استفاده از دکمه ناموجود</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Selectors -->
            <div id="selectors-settings" class="tab-content">
                <div class="card">
                    <h2 class="section-header">
                        🎯 سلکتورهای صفحه محصول
                    </h2>

                    <!-- عنوان محصول -->
                    <div class="form-group">
                        <label for="title_selector">
                            سلکتور عنوان <span class="required">*</span>
                        </label>
                        <input type="text" name="title_selector" id="title_selector"
                               value=".elementor-element-e499a81 > div:nth-child(1) > h1:nth-child(1)"
                               class="input-field"
                               placeholder=".product-title" required>
                    </div>

                    <div class="form-group">
                        <label for="brand_selector">
                            سلکتور برند <span class="required">*</span>
                        </label>
                        <input type="text" name="brand_selector" id="brand_selector"
                               value=".elementor-element-e499a81 > div:nth-child(1) > h1:nth-child(1)"
                               class="input-field"
                               placeholder=".product-brand" required>
                    </div>

                    <!-- سلکتورهای قیمت -->
                    <div class="form-group">
                        <label>
                            سلکتورهای قیمت <span class="required">*</span>
                        </label>
                        <div class="price-selectors-container space-y-3">
                            <div class="selector-container">
                                <input type="text" name="price_selector[]"
                                       value="p.price > span:nth-child(1) > bdi:nth-child(1)"
                                       class="input-field"
                                       placeholder=".product-price" required>
                                <button type="button" class="btn btn-primary add-price-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
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
                        <label>
                            سلکتورهای دسته‌بندی <span class="required">*</span>
                        </label>
                        <div class="category-selectors-container space-y-3">
                            <div class="selector-container">
                                <input type="text" name="category_selector[]"
                                       value=".posted_in > a:nth-child(2)"
                                       class="input-field"
                                       placeholder=".product-category">
                                <button type="button" class="btn btn-primary add-category-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                         fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <button type="button" class="btn btn-danger remove-category-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                         fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- سلکتورهای توضیحات -->
                    <div class="form-group">
                        <label>
                            سلکتورهای توضیحات <span class="required">*</span>
                        </label>
                        <div class="description-selectors-container space-y-3">
                            <div class="selector-container">
                                <input type="text" name="description_selector[]"
                                       value=".posted_in > a:nth-child(2)"
                                       class="input-field"
                                       placeholder=".product-description">
                                <button type="button" class="btn btn-primary add-description-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                         fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <button type="button" class="btn btn-danger remove-description-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
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
                        <label>
                            سلکتورهای موجودی <span class="required">*</span>
                        </label>
                        <div class="availability-selectors-container space-y-3">
                            <div class="selector-container">
                                <input type="text" name="availability_selector[]"
                                       value=".elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)"
                                       class="input-field"
                                       placeholder=".product-availability">
                                <button type="button" class="btn btn-primary add-availability-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                         fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <button type="button" class="btn btn-danger remove-availability-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
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
                    <div id="out-of-stock-container" class="form-group hidden">
                        <label>
                            سلکتورهای ناموجودی <span class="required">*</span>
                        </label>
                        <div class="out-of-stock-selectors-container space-y-3">
                            <div class="selector-container">
                                <input type="text" name="out_of_stock_selector[]"
                                       value=".testselector1"
                                       class="input-field"
                                       placeholder=".out-of-stock">
                                <button type="button" class="btn btn-primary add-out-of-stock-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                         fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <button type="button" class="btn btn-danger remove-out-of-stock-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
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
                    <div class="grid grid-cols-2">
                        <!-- سلکتورهای تصاویر -->
                        <div class="form-group">
                            <label>
                                سلکتورهای تصاویر
                            </label>
                            <div class="image-selectors-container space-y-3">
                                <div class="selector-container">
                                    <input type="text" name="image_selector[]"
                                           value=".elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)"
                                           class="input-field"
                                           placeholder=".image">
                                    <button type="button" class="btn btn-primary add-image-selector">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="btn btn-danger remove-image-selector">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- صفت‌های تصاویر -->
                        <div class="form-group">
                            <label>
                                صفت‌های تصاویر
                            </label>
                            <div class="image-attributes-container space-y-3">
                                <div class="selector-container">
                                    <input type="text" name="image_attribute[]"
                                           value="value"
                                           class="input-field"
                                           placeholder="data-id">
                                    <button type="button" class="btn btn-primary add-image-attribute">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="btn btn-danger remove-image-attribute">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- تخفیف -->
                        <div class="form-group">
                            <label for="off_selector">
                                سلکتور تخفیف
                            </label>
                            <input type="text" name="off_selector" id="off_selector"
                                   value=".discount"
                                   class="input-field"
                                   placeholder=".product-discount">
                        </div>

                        <!-- گارانتی -->
                        <div class="form-group">
                            <label for="guarantee_selector">
                                سلکتور گارانتی
                            </label>
                            <input type="text" name="guarantee_selector" id="guarantee_selector"
                                   value=".selector"
                                   class="input-field"
                                   placeholder=".product-guarantee">
                        </div>
                    </div>

                    <!-- Product ID Section -->
                    <div class="grid grid-cols-2">
                        <!-- سلکتورهای شناسه محصول -->
                        <div class="form-group">
                            <label>
                                سلکتورهای شناسه محصول
                            </label>
                            <div class="product-id-selectors-container space-y-3">
                                <div class="selector-container">
                                    <input type="text" name="product_id_selector[]"
                                           value=".elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)"
                                           class="input-field"
                                           placeholder=".product-id">
                                    <button type="button" class="btn btn-primary add-product-id-selector">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="btn btn-danger remove-product-id-selector">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
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
                            <label>
                                صفت‌های شناسه محصول
                            </label>
                            <div class="product-id-attributes-container space-y-3">
                                <div class="selector-container">
                                    <input type="text" name="product_id_attribute[]"
                                           value="value"
                                           class="input-field"
                                           placeholder="data-id">
                                    <button type="button" class="btn btn-primary add-product-id-attribute">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
                                             fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="btn btn-danger remove-product-id-attribute">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20"
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
                            <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی موجودی
                                (مثبت)</label>
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
                            <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی موجودی
                                (منفی)</label>
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
                            <label class="block text-sm font-medium text-brown-700 mb-2">کلمات کلیدی قیمت (بدون
                                قیمت)</label>
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
        </form>

        <!-- دکمه ارسال -->
        <div class="flex justify-center mt-8">
            <button type="submit" form="product-test-form"
                    class="btn-primary text-lg font-bold py-3 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-brown-500 focus:ring-opacity-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline ml-2" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                تست محصول
            </button>
        </div>

        <!-- بخش نتایج -->
        <div id="result-container" class="mt-8">
            @if (isset($result))
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
                                    <div
                                        class="text-2xl font-bold text-blue-600">{{ $result['total_tested'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">تعداد تست شده</div>
                                </div>
                                <div class="bg-white p-4 rounded-lg border">
                                    <div
                                        class="text-2xl font-bold text-green-600">{{ $result['total_products'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">موفقیت آمیز</div>
                                </div>
                                <div class="bg-white p-4 rounded-lg border">
                                    <div
                                        class="text-2xl font-bold text-red-600">{{ $result['failed_links'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">ناموفق</div>
                                </div>
                                <div class="bg-white p-4 rounded-lg border">
                                    <div
                                        class="text-2xl font-bold text-purple-600">{{ $result['success_rate'] ?? 0 }}%</div>
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
                                    @if(!empty($product['brand']))
                                        <div>
                                            <strong>برند:</strong>
                                            <span class="text-blue-600">{{ $product['brand'] }}</span>
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
                                            <span
                                                class="{{ $product['availability'] ? 'text-green-600' : 'text-red-600' }}">
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
                                        @if(!empty($product['description']))
                                            <div>
                                                <strong>توضیحات:</strong>
                                                <span class="text-purple-600">{{ $product['description'] }}</span>
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
                                            <a href="{{ $product['image'] }}" target="_blank"
                                               class="text-blue-500 hover:underline">
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
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        console.log('Script loaded and DOM fully parsed');

        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function () {
                console.log('Tab button clicked:', this.getAttribute('data-tab'));
                const targetTab = this.getAttribute('data-tab');
                document.querySelectorAll('.tab-button').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(targetTab).classList.add('active');
            });
        });

        // Out of stock button toggle
        const outOfStockCheckbox = document.querySelector('#out_of_stock_button');
        const outOfStockContainer = document.getElementById('out-of-stock-container');
        if (outOfStockCheckbox && outOfStockContainer) {
            outOfStockContainer.classList.toggle('hidden', !outOfStockCheckbox.checked);
            outOfStockCheckbox.addEventListener('change', function () {
                console.log('Out of stock checkbox toggled:', this.checked);
                outOfStockContainer.classList.toggle('hidden', !this.checked);
            });
        } else {
            console.error('Out of stock checkbox or container not found');
        }

        // Generic function to create a new input field
        function createInputField(inputName, placeholder) {
            const div = document.createElement('div');
            div.className = 'flex mt-3';
            div.innerHTML = `
            <input type="text" name="${inputName}"
                   class="input-field flex-1 px-4 py-2.5 rounded-lg focus:outline-none focus:ring-2 transition-all duration-200"
                   placeholder="${placeholder}">
            <button type="button" class="remove-${inputName.replace(/[\[\]]/g, '')} mr-2 btn-danger px-3 py-2 rounded-lg flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                     fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 011.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                          clip-rule="evenodd"/>
                </svg>
            </button>
        `;
            return div;
        }

        // Function to attach add/remove handlers for a container
        function attachHandlers(addButtonSelector, containerSelector, inputName, placeholder) {
            const addButton = document.querySelector(addButtonSelector);
            const container = document.querySelector(containerSelector);
            if (!addButton || !container) {
                console.error(`Add button (${addButtonSelector}) or container (${containerSelector}) not found`);
                return;
            }

            // Add button handler
            addButton.addEventListener('click', function (e) {
                e.preventDefault();
                console.log(`Add button clicked for ${inputName}`);
                const newField = createInputField(inputName, placeholder);
                container.appendChild(newField);
            });

            // Remove button handler using Event Delegation
            container.addEventListener('click', function (e) {
                const removeButton = e.target.closest(`button.remove-${inputName.replace(/[\[\]]/g, '')}`);
                if (removeButton) {
                    e.preventDefault();
                    console.log(`Remove button clicked for ${inputName}`);
                    const row = removeButton.closest('div.flex');
                    if (row && container.contains(row)) {
                        if (container.children.length > 1) {
                            container.removeChild(row);
                            console.log(`Row removed for ${inputName}`);
                        } else {
                            const input = row.querySelector('input');
                            if (input) {
                                input.value = '';
                                console.log(`Input cleared for ${inputName} (only one row remains)`);
                            }
                        }
                    } else {
                        console.error(`Row not found or not a child of container for ${inputName}`);
                    }
                }
            });
        }

        // Initialize handlers for all dynamic fields
        attachHandlers('.add-price-selector', '.price-selectors-container', 'price_selector[]', '.product-price');
        attachHandlers('.add-category-selector', '.category-selectors-container', 'category_selector[]', '.product-category');
        attachHandlers('.add-description-selector', '.description-selectors-container', 'description_selector[]', '.product-description');
        attachHandlers('.add-availability-selector', '.availability-selectors-container', 'availability_selector[]', '.product-availability');
        attachHandlers('.add-out-of-stock-selector', '.out-of-stock-selectors-container', 'out_of_stock_selector[]', '.out-of-stock');
        attachHandlers('.add-guarantee-keyword', '.guarantee-keywords-container', 'guarantee_keywords[]', 'گارانتی');
        attachHandlers('.add-availability-positive', '.availability-positive-container', 'availability_keywords_positive[]', 'موجود');
        attachHandlers('.add-availability-negative', '.availability-negative-container', 'availability_keywords_negative[]', 'ناموجود');
        attachHandlers('.add-price-unpriced', '.price-unpriced-container', 'price_keywords_unpriced[]', 'تماس بگیرید');
        attachHandlers('.add-product-id-selector', '.product-id-selectors-container', 'product_id_selector[]', '.product-id');
        attachHandlers('.add-product-id-attribute', '.product-id-attributes-container', 'product_id_attribute[]', 'data-id');
        attachHandlers('.add-image-selector', '.image-selectors-container', 'product_id_selector[]', '.image');
        attachHandlers('.add-image-attribute', '.image-attributes-container', 'product_id_attribute[]', 'data-id');

        // Sync product ID selectors and attributes
        function syncProductIdFields() {
            const selectorInputs = document.querySelectorAll('input[name="product_id_selector[]"]');
            const attributeInputs = document.querySelectorAll('input[name="product_id_attribute[]"]');
            const selectorContainer = document.querySelector('.product-id-selectors-container');
            const attributeContainer = document.querySelector('.product-id-attributes-container');

            if (!selectorContainer || !attributeContainer) {
                console.error('Product ID containers not found');
                return;
            }

            const maxLength = Math.max(selectorInputs.length, attributeInputs.length);

            // Add missing selector fields
            while (selectorContainer.children.length < maxLength) {
                const newField = createInputField('product_id_selector[]', '.product-id');
                selectorContainer.appendChild(newField);
            }

            // Add missing attribute fields
            while (attributeContainer.children.length < maxLength) {
                const newField = createInputField('product_id_attribute[]', 'data-id');
                attributeContainer.appendChild(newField);
            }

            console.log('Product ID fields synced:', selectorInputs.length, attributeInputs.length);
        }

        // Initial sync
        syncProductIdFields();

        function syncImageFields() {
            const selectorInputs = document.querySelectorAll('input[name="image_selector[]"]');
            const attributeInputs = document.querySelectorAll('input[name="image_attribute[]"]');
            const selectorContainer = document.querySelector('.image-selectors-container');
            const attributeContainer = document.querySelector('.image-attributes-container');

            if (!selectorContainer || !attributeContainer) {
                console.error('image containers not found');
                return;
            }

            const maxLength = Math.max(selectorInputs.length, attributeInputs.length);

            // Add missing selector fields
            while (selectorContainer.children.length < maxLength) {
                const newField = createInputField('product_id_selector[]', '.image');
                selectorContainer.appendChild(newField);
            }

            // Add missing attribute fields
            while (attributeContainer.children.length < maxLength) {
                const newField = createInputField('image_attribute[]', 'data-id');
                attributeContainer.appendChild(newField);
            }

            console.log('image fields synced:', selectorInputs.length, attributeInputs.length);
        }

        // Initial sync
        syncImageFields();

        // Handle form submission with AJAX
        const form = document.querySelector('#product-test-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault(); // Prevent page refresh
                console.log('Form submission intercepted');
                console.log('Form action URL:', form.action);

                // Collect form data
                const formData = new FormData(form);
                console.log('Form data:', Object.fromEntries(formData)); // Log form data for debugging

                // Show loading state
                const resultContainer = document.querySelector('#result-container');
                if (resultContainer) {
                    resultContainer.innerHTML = '<div class="loading">در حال پردازش...</div>';
                }

                // Send AJAX request
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json'
                    }
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers.get('content-type'));
                        if (!response.ok) {
                            if (response.status === 422) {
                                return response.json().then(data => {
                                    throw new Error(`خطای اعتبارسنجی: ${data.errors.join(', ')}`);
                                });
                            }
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        if (!response.headers.get('content-type').includes('application/json')) {
                            return response.text().then(text => {
                                throw new Error(`Response is not JSON. Raw response: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Parsed JSON:', data);
                        displayResults(data);
                    })
                    .catch(error => {
                        console.error('Error during fetch:', error);
                        displayError(`خطایی رخ داد: ${error.message}`);
                    })
                    .finally(() => {
                        // Hide loading state
                        if (resultContainer) resultContainer.querySelector('.loading')?.remove();
                    });
            });
        } else {
            console.error('Form not found');
        }

        // Function to display results dynamically
        function displayResults(data) {
            const resultContainer = document.querySelector('#result-container');
            if (!resultContainer) {
                console.error('Result container not found');
                return;
            }

            const card = document.createElement('div');
            card.className = `card p-6 ${data.status === 'success' ? 'result-success' : 'result-error'}`;

            let html = `
                <h3 class="text-xl font-bold mb-4 flex items-center">
                    ${data.status === 'success' ? `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-2 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    ` : `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-2 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    `}
                    نتیجه تست
                </h3>
            `;

            if (data.status === 'success' && data.result.test_mode) {
                html += `
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold mb-3">آمار کلی</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-white p-4 rounded-lg border">
                                <div class="text-2xl font-bold text-blue-600">${data.result.total_tested || 0}</div>
                                <div class="text-sm text-gray-600">تعداد تست شده</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border">
                                <div class="text-2xl font-bold text-green-600">${data.result.total_products || 0}</div>
                                <div class="text-sm text-gray-600">موفقیت آمیز</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border">
                                <div class="text-2xl font-bold text-red-600">${data.result.failed_links || 0}</div>
                                <div class="text-sm text-gray-600">ناموفق</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border">
                                <div class="text-2xl font-bold text-purple-600">${data.result.success_rate || 0}%</div>
                                <div class="text-sm text-gray-600">نرخ موفقیت</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            if (data.result.products && data.result.products.length > 0) {
                html += `<h4 class="text-lg font-semibold mb-3">محصولات استخراج شده</h4>`;
                data.result.products.forEach((product, index) => {
                    html += `
                        <div class="bg-white p-4 rounded-lg border mb-4">
                            <h5 class="font-bold text-lg mb-2">محصول ${index + 1}</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                ${product.title ? `<div><strong>عنوان:</strong> <span class="text-blue-600">${product.title}</span></div>` : ''}
                                ${product.brand ? `<div><strong>برند:</strong> <span class="text-blue-600">${product.brand}</span></div>` : ''}
                                ${product.price ? `<div><strong>قیمت:</strong> <span class="text-green-600">${product.price}</span></div>` : ''}
                                ${typeof product.availability !== 'undefined' ? `
                                    <div><strong>موجودی:</strong>
                                        <span class="${product.availability ? 'text-green-600' : 'text-red-600'}">
                                            ${product.availability ? 'موجود' : 'ناموجود'}
                                        </span>
                                    </div>` : ''}
                                ${product.category ? `<div><strong>دسته‌بندی:</strong> <span class="text-purple-600">${product.category}</span></div>` : ''}
                                ${product.description ? `<div><strong>توضیحات:</strong> <span class="text-purple-600">${product.description}</span></div>` : ''}
                                ${product.product_id ? `<div><strong>شناسه محصول:</strong> <span class="text-indigo-600">${product.product_id}</span></div>` : ''}
                                ${product.guarantee ? `<div><strong>گارانتی:</strong> <span class="text-orange-600">${product.guarantee}</span></div>` : ''}
                                ${product.image ? `<div><strong>تصویر:</strong> <a href="${product.image}" target="_blank" class="text-blue-500 hover:underline">مشاهده تصویر</a></div>` : ''}
                                ${product.off && product.off > 0 ? `<div><strong>تخفیف:</strong> <span class="text-red-600">${product.off}%</span></div>` : ''}
                            </div>
                        </div>
                    `;
                });
            }

            if (data.result.message) {
                html += `
                    <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                        <strong>پیام:</strong> ${data.result.message}
                    </div>
                `;
            }

            if (data.result.failed_urls && data.result.failed_urls.length > 0) {
                html += `
                    <div class="mt-4">
                        <h4 class="text-lg font-semibold mb-3 text-red-600">آدرس‌های ناموفق</h4>
                        <ul class="list-disc mr-6">
                            ${data.result.failed_urls.map(url => `<li class="text-red-600">${url}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            //if (data.logs && data.logs.length > 0) {
            //    html += `
            //       <div class="mt-4">
            //          <h4 class="text-lg font-semibold mb-3">لاگ‌ها</h4>
            //           <div class="bg-gray-100 p-4 rounded-lg">
            //             ${data.logs.map(log => `<div class="log-line">${log}</div>`).join('')}
            //         </div>
            //      </div>
            //  `;
            //  }

            card.innerHTML = html;
            resultContainer.innerHTML = ''; // Clear previous content
            resultContainer.appendChild(card);
        }

        // Function to display error message
        function displayError(message) {
            const resultContainer = document.querySelector('#result-container');
            if (!resultContainer) {
                console.error('Result container not found');
                return;
            }

            resultContainer.innerHTML = `
                <div class="card p-6 result-error">
                    <h3 class="text-xl font-bold mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-2 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        خطا
                    </h3>
                    <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                        <strong>پیام:</strong> ${message}
                    </div>
                </div>
            `;
        }
    });
</script>
</body>
</html>
