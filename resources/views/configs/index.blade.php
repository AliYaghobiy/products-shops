<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کانفیگ‌ها</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/vazirmatn/33.0.3/Vazirmatn-font-face.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: { 100: '#fffcf0', 500: '#ffd700', 600: '#e6c300' },
                        sepia: { 50: '#fcf9f5', 400: '#d5ad85', 500: '#c69c6d', 600: '#b38755', 800: '#795c41', 900: '#5f4935' }
                    },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: "Vazirmatn", system-ui, serif;
            background-color: #f8f1e9;
            color: #5f4935;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23d5ad85' fill-opacity='0.08' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        .btn-classic { background-color: #b38755; color: #fcf9f5; border: 1px solid #96714a; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .btn-classic:hover { background-color: #96714a; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15); }
        .btn-gold { background-color: #ffd700; color: #5f4935; border: 1px solid #e6c300; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .btn-gold:hover { background-color: #e6c300; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15); }
        .btn-danger { background-color: #a83240; color: #fcf9f5; border: 1px solid #8a2a36; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .btn-danger:hover { background-color: #8a2a36; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15); }
        .btn-success { background-color: #2d6a4f; color: #fcf9f5; border: 1px solid #1b4332; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .btn-success:hover { background-color: #1b4332; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15); }

        .config-card { background-color: #fcf9f5; border: 1px solid #d5ad85; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); transition: all 0.3s ease; }
        .config-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1); border-color: #b38755; }

        .badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-weight: 500; }
        .badge-method-1 { background-color: #e9d8fd; color: #553c9a; border: 1px solid #b794f4; }
        .badge-method-2 { background-color: #c6f6d5; color: #2f855a; border: 1px solid #68d391; }
        .badge-method-3 { background-color: #feebc8; color: #9c4221; border: 1px solid #f6ad55; }

        .empty-state { background-color: rgba(252, 249, 245, 0.8); border: 2px dashed #d5ad85; }
        .table-row { border-bottom: 1px solid #e4c9aa; transition: all 0.2s ease; }
        .table-row:hover { background-color: #f8f1e9; }

        .classic-panel { background-color: #fcf9f5; border: 1px solid #d5ad85; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); border-radius: 0.5rem; }
        .classic-header { border-bottom: 2px solid #d5ad85; position: relative; }
        .classic-header:after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, #b38755, transparent); }
        .classic-title { font-family: "Vazirmatn", serif; color: #795c41; text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.1); }

        .ornament { position: relative; display: inline-block; font-weight: bold; color: #b38755; }
        .ornament:before, .ornament:after { content: "❧"; display: inline-block; margin: 0 0.5rem; font-size: 1.2em; color: #c69c6d; }

        table.classic-table { border-collapse: separate; border-spacing: 0; width: 100%; border: 1px solid #d5ad85; border-radius: 0.5rem; overflow: hidden; }
        table.classic-table th { background-color: #e4c9aa; color: #5f4935; font-weight: bold; padding: 0.75rem 1rem; border-bottom: 1px solid #d5ad85; text-align: right; }
        table.classic-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f0e0cf; }
        table.classic-table tr:last-child td { border-bottom: none; }
        table.classic-table tr:hover { background-color: #f8f1e9; }

        /* تم دارک */
        body.dark { background-color: #2d2d2d; color: #e0e0e0; background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23808080' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E"); }
        body.dark .classic-panel { background-color: #2f2f2f; border: 1px solid #555; }
        body.dark .classic-title { color: #e0e0e0; }
        body.dark .config-card { background-color: #3a3a3a; border-color: #555; }
        body.dark .classic-table th { background-color: #444; color: #e0e0e0; border-color: #555; }
        body.dark .classic-table td { border-color: #444; }
        body.dark .classic-table tr:hover { background-color: #444; }
    </style>
</head>
<body class="min-h-screen py-10">
<div class="container mx-auto px-4">
    <div class="max-w-6xl mx-auto classic-panel p-8">
        <!-- هدر -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 pb-6 classic-header">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-cogs text-2xl text-sepia-600 ml-3"></i>
                <h1 class="text-3xl font-bold classic-title">مدیریت کانفیگ‌ها</h1>
                <button id="theme-toggle" class="btn-classic px-4 py-2 rounded-lg flex items-center mr-4">
                    <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
            </div>
            <div class="flex gap-4">
                <a href="{{ route('configs.create') }}" class="btn-gold px-6 py-3 rounded flex items-center shadow-lg hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-plus-circle ml-2"></i>
                    ایجاد کانفیگ جدید
                </a>
                <a href="{{ route('configs.single_product') }}" class="btn-classic px-6 py-3 rounded flex items-center shadow-lg hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-flask ml-2"></i>
                    تست تک محصول
                </a>
            </div>
        </div>

        <!-- پیام‌های سیستم -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-500 text-green-800 px-6 py-4 rounded mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2 text-green-600"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-500 text-red-800 px-6 py-4 rounded mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2 text-red-600"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- نمای کارت‌ها -->
        <div class="mb-12">
            <h2 class="text-xl font-bold mb-6 flex items-center justify-center">
                <span class="ornament">نمای کارت‌ها</span>
            </h2>

            @php
                $sortedConfigs = $configs;
                usort($sortedConfigs, function ($a, $b) {
                    return strcmp($b['started_at'] ?? '0000-00-00', $a['started_at'] ?? '0000-00-00');
                });
            @endphp

            @if(count($sortedConfigs) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($sortedConfigs as $config)
                        <div class="config-card p-6">
                            <div class="flex justify-between items-start mb-4 pb-3 border-b border-sepia-200">
                                <h3 class="text-lg font-bold text-sepia-900">{{ $config['filename'] }}</h3>
                                <span class="badge badge-method-{{ $config['content']['method'] }}">
                                    روش {{ $config['content']['method'] }}
                                </span>
                            </div>

                            <div class="mb-6 text-sepia-800">
                                <div class="flex items-center mb-3">
                                    <i class="fas fa-link text-sepia-500 ml-2"></i>
                                    <span class="text-sm">{{ count($config['content']['base_urls']) }} URL پایه</span>
                                </div>
                                <div class="flex items-center mb-3">
                                    <i class="fas fa-shopping-cart text-sepia-500 ml-2"></i>
                                    <span class="text-sm">{{ count($config['content']['products_urls']) }} URL محصول</span>
                                </div>
                                <div class="flex items-center">
                                    @if($config['status'] === 'running')
                                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                        <span class="text-sm text-green-700">در حال اجرا</span>
                                    @elseif($config['status'] === 'crashed')
                                        <div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div>
                                        <span class="text-sm text-red-700">خرابی</span>
                                    @else
                                        <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                                        <span class="text-sm text-gray-600">متوقف</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap justify-between gap-2 pt-4 border-t border-sepia-200">
                                <div class="flex flex-wrap gap-2">
                                    <form action="{{ route('configs.run', $config['filename']) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="btn-success px-3 py-2 rounded text-sm flex items-center">
                                            <i class="fas fa-play ml-1"></i>
                                            اجرا
                                        </button>
                                    </form>
                                    <form action="{{ route('configs.update-scraper', $config['filename']) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm flex items-center">
                                            <i class="fas fa-sync-alt ml-1"></i>
                                            اپدیت
                                        </button>
                                    </form>
                                    <form action="{{ route('configs.stop', $config['filename']) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="btn-danger px-3 py-2 rounded text-sm flex items-center">
                                            <i class="fas fa-stop ml-1"></i>
                                            توقف
                                        </button>
                                    </form>
                                    <a href="{{ route('configs.edit', $config['filename']) }}" class="btn-classic px-3 py-2 rounded text-sm flex items-center">
                                        <i class="fas fa-edit ml-1"></i>
                                        ویرایش
                                    </a>
                                    <a href="{{ route('configs.logs', $config['filename']) }}" class="btn-classic px-3 py-2 rounded text-sm flex items-center">
                                        <i class="fas fa-file-alt ml-1"></i>
                                        لاگ‌ها
                                    </a>
                                </div>
                                <form action="{{ route('configs.destroy', $config['filename']) }}" method="POST" class="inline-block" onsubmit="return confirm('آیا از حذف این کانفیگ اطمینان دارید؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger px-3 py-2 rounded text-sm flex items-center">
                                        <i class="fas fa-trash-alt ml-1"></i>
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state rounded p-10 flex flex-col items-center justify-center">
                    <i class="fas fa-folder-open text-5xl text-sepia-400 mb-4"></i>
                    <h3 class="text-xl font-bold text-sepia-700 mb-2">هیچ کانفیگی یافت نشد</h3>
                    <p class="text-sepia-600 mb-6 text-center">برای شروع، یک کانفیگ جدید ایجاد کنید。</p>
                    <a href="{{ route('configs.create') }}" class="btn-gold px-6 py-3 rounded flex items-center">
                        <i class="fas fa-plus-circle ml-2"></i>
                        ایجاد کانفیگ جدید
                    </a>
                </div>
            @endif
        </div>

        <!-- نمای جدول -->
        <div>
            <h2 class="text-xl font-bold mb-6 flex items-center justify-center">
                <span class="ornament">نمای جدول</span>
            </h2>

            @php
                $sortedByMethod = $configs;
                usort($sortedByMethod, function ($a, $b) {
                    return ($a['content']['method'] ?? 0) <=> ($b['content']['method'] ?? 0);
                });
            @endphp

            @if(count($sortedByMethod) > 0)
                <div class="overflow-x-auto rounded">
                    <table class="classic-table">
                        <thead>
                        <tr>
                            <th class="px-6 py-4 text-right">نام فایل</th>
                            <th class="px-6 py-4 text-right">روش</th>
                            <th class="px-6 py-4 text-right">وضعیت</th>
                            <th class="px-6 py-4 text-right">تعداد URL</th>
                            <th class="px-6 py-4 text-right">عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($sortedByMethod as $config)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-sepia-900">{{ $config['filename'] }}</div>
                                    @if(isset($config['started_at']))
                                        <div class="text-xs text-sepia-600">{{ $config['started_at'] }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                        <span class="badge badge-method-{{ $config['content']['method'] }}">
                                            روش {{ $config['content']['method'] }}
                                        </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($config['status'] === 'running')
                                        <span class="inline-flex items-center text-green-700">
                                                <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                                در حال اجرا
                                            </span>
                                    @elseif($config['status'] === 'crashed')
                                        <span class="inline-flex items-center text-red-700">
                                                <div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div>
                                                خرابی
                                            </span>
                                    @else
                                        <span class="inline-flex items-center text-gray-600">
                                                <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                                                متوقف
                                            </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sepia-800">
                                    {{ count($config['content']['base_urls']) + count($config['content']['products_urls']) }}
                                </td>
                                <td class="px-6 py-4 text-right flex flex-wrap gap-2">
                                    <a href="{{ route('configs.edit', $config['filename']) }}" class="btn-classic px-2 py-1 rounded text-xs">
                                        <i class="fas fa-edit ml-1"></i>
                                        ویرایش
                                    </a>
                                    <form action="{{ route('configs.run', $config['filename']) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="btn-success px-2 py-1 rounded text-xs flex items-center">
                                            <i class="fas fa-play ml-1"></i>
                                            اجرا
                                        </button>
                                    </form>
                                    <form action="{{ route('configs.stop', $config['filename']) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="btn-danger px-2 py-1 rounded text-xs flex items-center">
                                            <i class="fas fa-stop ml-1"></i>
                                            توقف
                                        </button>
                                    </form>
                                    <a href="{{ route('configs.logs', $config['filename']) }}" class="btn-classic px-2 py-1 rounded text-xs">
                                        <i class="fas fa-file-alt ml-1"></i>
                                        لاگ‌ها
                                    </a>
                                    <form action="{{ route('configs.destroy', $config['filename']) }}" method="POST" class="inline-block" onsubmit="return confirm('آیا از حذف این کانفیگ اطمینان دارید؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger px-2 py-1 rounded text-xs">
                                            <i class="fas fa-trash-alt ml-1"></i>
                                            حذف
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state rounded p-8 flex flex-col items-center justify-center">
                    <i class="fas fa-table text-4xl text-sepia-400 mb-3"></i>
                    <h3 class="text-lg font-bold text-sepia-700 mb-1">جدول خالی است</h3>
                    <p class="text-sepia-600 text-sm">داده‌ای برای نمایش وجود ندارد。</p>
                </div>
            @endif
        </div>

        <!-- دکمه حذف تمام لاگ‌ها -->
        <div class="mt-8 pt-6 border-t border-sepia-200 flex justify-center">
            <form action="{{ route('configs.logs.deleteAll') }}" method="POST" onsubmit="return confirm('آیا از حذف تمامی فایل‌های لاگ مرتبط با ربات اطمینان دارید؟ این عملیات قابل بازگشت نیست.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger px-6 py-3 rounded flex items-center shadow-lg hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-trash-alt ml-2"></i>
                    حذف تمام لاگ‌ها
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');

    themeToggleBtn.addEventListener('click', function () {
        document.body.classList.toggle('dark');

        if (document.body.classList.contains('dark')) {
            themeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>`;
        } else {
            themeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>`;
        }
    });
</script>
</body>
</html>
