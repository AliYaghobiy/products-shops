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
                        primary: {
                            50: '#fef3ff',
                            100: '#fde6ff',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                        },
                        accent: {
                            50: '#fef3c7',
                            500: '#f59e0b',
                        },
                        success: {
                            50: '#ecfdf5',
                            500: '#10b981',
                        },
                        error: {
                            50: '#fef2f2',
                            500: '#ef4444',
                        },
                        method: {
                            1: '#e879f9',
                            2: '#4ade80',
                            3: '#fb923c'
                        }
                    },
                    fontFamily: {
                        'vazir': ['Vazirmatn', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: "Vazirmatn", system-ui, sans-serif;
            background: linear-gradient(135deg, #fef3ff 0%, #e0f2fe 50%, #ecfdf5 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.1);
            border-radius: 16px;
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(139, 92, 246, 0.15);
        }

        .running-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .stopped-badge {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .method-badge-1 {
            background: linear-gradient(135deg, #e879f9, #c084fc);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(232, 121, 249, 0.3);
        }

        .method-badge-2 {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(74, 222, 128, 0.3);
        }

        .method-badge-3 {
            background: linear-gradient(135deg, #fb923c, #f97316);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(251, 146, 60, 0.3);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-info {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(6, 182, 212, 0.3);
        }

        .btn-purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }

        .btn-purple:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #475569, #334155);
        }

        .config-row {
            background: linear-gradient(145deg, rgba(255,255,255,0.9), rgba(248,250,252,0.8));
            border: 2px solid transparent;
            background-clip: padding-box;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .config-row::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #8b5cf6, #06b6d4, #10b981);
        }

        .config-row:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .running-config {
            background: linear-gradient(145deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
            border: 2px solid rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }

        .running-config::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #34d399, #10b981);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        .empty-state {
            background: linear-gradient(145deg, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.6));
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
            color: #64748b;
        }

        .title-gradient {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title {
            position: relative;
            display: inline-block;
            padding: 0 20px;
        }

        .section-title::before,
        .section-title::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #8b5cf6, transparent);
        }

        .section-title::before {
            right: 100%;
        }

        .section-title::after {
            left: 100%;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.15);
        }

        .notification {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #065f46;
        }

        .notification-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #b91c1c;
        }

        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            font-family: "Vazirmatn", system-ui;
            transition: all 0.3s ease;
            direction: rtl;
            backdrop-filter: blur(10px);
        }

        .search-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            background: rgba(255, 255, 255, 1);
        }

        .search-input::placeholder {
            color: #9ca3af;
            font-size: 14px;
        }

        .search-clear {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 14px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 1;
            padding: 4px;
        }

        .search-clear.show {
            opacity: 1;
        }

        .search-clear:hover {
            color: #ef4444;
        }

        .search-filters {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .filter-chip {
            padding: 6px 12px;
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.7);
            color: #7c3aed;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .filter-chip:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .filter-chip.active {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border-color: #7c3aed;
        }

        .search-results {
            margin-top: 16px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
        }

        .no-results i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .highlight {
            background: linear-gradient(135deg, #fef3ff, #fde6ff);
            color: #7c3aed;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }

        .config-row.hidden {
            display: none !important;
        }

        .fade-animation {
            animation: fadeIn 0.3s ease-in-out;
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.1));
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .stats-purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stats-blue {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .stats-green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .icon-bounce {
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .pulse-dot {
            animation: pulse 2s infinite;
        }


        .stats-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .stat-value {
            font-weight: 600;
            color: #374151;
        }

        .duration-badge {
            display: inline-block;
            background: #f3f4f6;
            color: #374151;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .config-stats {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }


        .stat-item i {
            width: 12px;
            text-align: center;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
            border: 2px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .status-dot.green {
            background-color: #10b981;
        }

        .status-dot.yellow {
            background-color: #f59e0b;
        }

        .status-dot.red {
            background-color: #ef4444;
        }

        .config-row {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .config-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .running-config {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .running-config::before {
            display: none;
        }
    </style>
</head>
<body>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="glass-card p-6 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-cogs text-3xl text-gold-500 ml-3"></i>
                <h1 class="text-3xl font-bold title-gradient">مدیریت کانفیگ‌ها</h1>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('configs.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    کانفیگ جدید
                </a>
                <a href="{{ route('configs.single_product') }}" class="btn btn-info">
                    <i class="fas fa-flask"></i>
                    تست تک محصول
                </a>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    @if(session('success'))
        <div class="notification notification-success">
            <i class="fas fa-check-circle text-lg"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle text-lg"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Stats -->
    @php
        $runningCount = count(array_filter($configs, fn($c) => isset($c['status']) && $c['status'] === 'running'));
        $totalCount = count($configs);
        $stoppedCount = $totalCount - $runningCount;
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="stats-card">
            <div class="text-2xl font-bold text-green-600 mb-1">{{ $runningCount }}</div>
            <div class="text-sm text-gray-600">در حال اجرا</div>
        </div>
        <div class="stats-card">
            <div class="text-2xl font-bold text-gray-600 mb-1">{{ $stoppedCount }}</div>
            <div class="text-sm text-gray-600">متوقف</div>
        </div>
        <div class="stats-card">
            <div class="text-2xl font-bold text-gold-600 mb-1">{{ $totalCount }}</div>
            <div class="text-sm text-gray-600">کل کانفیگ‌ها</div>
        </div>
    </div>


    <!-- Search Config -->
    <div class="glass-card p-6 mb-8">
        <div class="search-container">
            <input
                type="text"
                id="searchInput"
                class="search-input"
                placeholder="جستجو در کانفیگ‌ها..."
                autocomplete="off"
            >
            <button type="button" id="searchClear" class="search-clear">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="search-filters">
            <div class="filter-chip active" data-filter="all">همه</div>
            <div class="filter-chip" data-filter="running">فعال</div>
            <div class="filter-chip" data-filter="stopped">متوقف</div>
            <div class="filter-chip" data-filter="method-1">روش ۱</div>
            <div class="filter-chip" data-filter="method-2">روش ۲</div>
            <div class="filter-chip" data-filter="method-3">روش ۳</div>
            <div class="filter-chip" data-filter="newest">جدیدترین</div>
        </div>

        <div id="searchResults" class="search-results"></div>
    </div>

    <!-- Running Configs Section -->

    @php
        $runningConfigs = array_filter($configs, fn($c) => isset($c['status']) && $c['status'] === 'running');
        // تغییر: مرتب‌سازی بر اساس نام فایل (انگلیسی)
        usort($runningConfigs, fn($a, $b) => strcmp($a['filename'], $b['filename']));
    @endphp
    @php
        use App\Helpers\PersianDateHelper;
    @endphp
    @if(count($runningConfigs) > 0)
        <div class="mb-8">
            <h2 class="text-xl font-bold text-center mb-6">
                <span class="section-title">کانفیگ‌های فعال</span>
            </h2>

            <div class="space-y-3">
                @foreach($runningConfigs as $config)
                    <div class="config-row running-config">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div class="flex items-center gap-4 flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                    <span class="font-bold text-lg">{{ $config['filename'] }}</span>
                                </div>
                                <span class="method-badge-{{ $config['content']['method'] }}">
                                        روش {{ $config['content']['method'] }}
                                    </span>
                                <span class="running-badge">فعال</span>
                            </div>

                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span><i class="fas fa-link ml-1"></i>{{ count($config['content']['base_urls']) + count($config['content']['products_urls']) }} URL</span>
                                @if(isset($config['started_at']))
                                    <div class="text-xs text-gray-500">
                                        {{ PersianDateHelper::toPersian($config['started_at']) }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex gap-2 flex-wrap">
                                <form action="{{ route('configs.update-scraper', $config['filename']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-purple">
                                        <i class="fas fa-sync-alt"></i>
                                        اپدیت
                                    </button>
                                </form>
                                <form action="{{ route('configs.stop', $config['filename']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-stop"></i>
                                        توقف
                                    </button>
                                </form>
                                <a href="{{ route('configs.test-database', $config['filename']) }}" class="btn btn-info">
                                    <i class="fas fa-database"></i>
                                    تست دیتابیس
                                </a>
                                <a href="{{ route('configs.edit', $config['filename']) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i>
                                    ویرایش
                                </a>
                                <a href="{{ route('configs.logs', $config['filename']) }}" class="btn btn-info">
                                    <i class="fas fa-file-alt"></i>
                                    لاگ‌ها
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- All Configs Section -->
    <div class="glass-card p-6">
        <h2 class="text-xl font-bold text-center mb-6">
            <span class="section-title">تمامی کانفیگ‌ها</span>
        </h2>

        @if(count($configs) > 0)
            <div class="space-y-3">
                @php
                    $sortedConfigs = $configs;
                    // تغییر: مرتب‌سازی بر اساس نام فایل (انگلیسی) به جای method
                    usort($sortedConfigs, fn($a, $b) => strcmp($a['filename'], $b['filename']));
                @endphp

                    <!-- تغییر در قسمت نمایش کانفیگ‌ها در فایل blade -->

                @foreach($sortedConfigs as $index => $config)
                    <div class="config-row {{ (isset($config['status']) && $config['status'] === 'running') ? 'running-config' : '' }}">
                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                            <div class="flex items-center gap-4 flex-1">
                                <!-- دایره وضعیت با منطق جدید -->
                                <div class="status-dot {{ $config['dot_class'] ?? 'red' }}"></div>

                                <div class="text-purple-600 font-bold text-lg min-w-[30px]">{{ $index + 1 }}</div>
                                <div>
                                    <div class="font-bold text-lg">{{ $config['filename'] }}</div>
                                    @if(isset($config['started_at']))
                                        <div class="text-xs text-gray-500">
                                            {{ PersianDateHelper::toPersian($config['started_at']) }}
                                        </div>
                                    @endif

                                    <!-- نمایش آمار -->
                                    @if(isset($config['stats']))
                                        <div class="config-stats">
                                            @if($config['stats']['total_products'] > 0)
                                                <div class="stat-item">
                                                    <i class="fas fa-box"></i>
                                                    <span>{{ $config['stats']['total_products'] }} محصول</span>
                                                </div>
                                            @endif
                                            @if($config['stats']['total_links'] > 0)
                                                <div class="stat-item">
                                                    <i class="fas fa-link"></i>
                                                    <span>{{ $config['stats']['total_links'] }} لینک</span>
                                                </div>
                                            @endif
                                            @if($config['stats']['last_run_duration'])
                                                <div class="stat-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span>{{ $config['stats']['last_run_duration'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <span class="method-badge-{{ $config['content']['method'] }}">
                    روش {{ $config['content']['method'] }}
                </span>
                                @if(isset($config['status']) && $config['status'] === 'running')
                                    <span class="running-badge">فعال</span>
                                @else
                                    <span class="stopped-badge">متوقف</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span><i class="fas fa-link ml-1"></i>{{ count($config['content']['base_urls']) + count($config['content']['products_urls']) }} URL</span>
                            </div>

                            <!-- باقی دکمه‌ها بدون تغییر -->
                            <div class="flex gap-2 flex-wrap">
                                @if(!isset($config['status']) || $config['status'] !== 'running')
                                    <form action="{{ route('configs.run', $config['filename']) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-play"></i>
                                            اجرا
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('configs.update-scraper', $config['filename']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-purple">
                                        <i class="fas fa-sync-alt"></i>
                                        اپدیت
                                    </button>
                                </form>

                                @if(isset($config['status']) && $config['status'] === 'running')
                                    <form action="{{ route('configs.stop', $config['filename']) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-stop"></i>
                                            توقف
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('configs.edit', $config['filename']) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i>
                                    ویرایش
                                </a>
                                <a href="{{ route('configs.logs', $config['filename']) }}" class="btn btn-info">
                                    <i class="fas fa-file-alt"></i>
                                    لاگ‌ها
                                </a>
                                <a href="{{ route('configs.test-database', $config['filename']) }}" class="btn btn-info">
                                    <i class="fas fa-database"></i>
                                    تست دیتابیس
                                </a>
                                <form action="{{ route('configs.destroy', $config['filename']) }}" method="POST" class="inline" onsubmit="return confirm('آیا از حذف این کانفیگ اطمینان دارید؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i>
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-folder-open text-5xl mb-4 opacity-50"></i>
                <h3 class="text-xl font-bold mb-2">هیچ کانفیگی وجود ندارد</h3>
                <p class="text-gray-600 mb-4">برای شروع، اولین کانفیگ خود را ایجاد کنید</p>
                <a href="{{ route('configs.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    ایجاد کانفیگ جدید
                </a>
            </div>
        @endif
    </div>

    <!-- Delete All Logs -->
    <div class="text-center mt-8">
        <form action="{{ route('configs.logs.deleteAll') }}" method="POST" onsubmit="return confirm('آیا از حذف تمامی لاگ‌ها اطمینان دارید؟')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash-alt"></i>
                حذف تمام لاگ‌ها
            </button>
        </form>
    </div>
</div>

<script>
    class ConfigSearch {
        constructor() {
            this.searchInput = document.getElementById('searchInput');
            this.searchClear = document.getElementById('searchClear');
            this.searchResults = document.getElementById('searchResults');
            this.filterChips = document.querySelectorAll('.filter-chip');
            this.configRows = document.querySelectorAll('.config-row');

            this.currentFilter = 'all';
            this.currentSearch = '';

            this.init();
        }

        init() {
            // Search input events
            this.searchInput.addEventListener('input', this.handleSearch.bind(this));
            this.searchInput.addEventListener('keydown', this.handleKeydown.bind(this));

            // Clear button
            this.searchClear.addEventListener('click', this.clearSearch.bind(this));

            // Filter chips
            this.filterChips.forEach(chip => {
                chip.addEventListener('click', this.handleFilter.bind(this));
            });

            // Initial count
            this.updateResults();
        }

        handleSearch(e) {
            this.currentSearch = e.target.value.trim();
            this.toggleClearButton();
            this.filterConfigs();
        }

        handleKeydown(e) {
            if (e.key === 'Escape') {
                this.clearSearch();
            }
        }

        handleFilter(e) {
            const filter = e.target.dataset.filter;

            // Update active filter chip
            this.filterChips.forEach(chip => chip.classList.remove('active'));
            e.target.classList.add('active');

            this.currentFilter = filter;
            this.filterConfigs();
        }

        clearSearch() {
            this.searchInput.value = '';
            this.currentSearch = '';
            this.toggleClearButton();
            this.filterConfigs();
            this.searchInput.focus();
        }

        toggleClearButton() {
            if (this.currentSearch.length > 0) {
                this.searchClear.classList.add('show');
            } else {
                this.searchClear.classList.remove('show');
            }
        }

        filterConfigs() {
            let visibleCount = 0;
            const searchTerm = this.currentSearch.toLowerCase().trim();

            // Sort configs based on current filter
            this.sortConfigs();

            this.configRows.forEach(row => {
                let shouldShow = false;

                // First apply category filter
                const isRunning = row.classList.contains('running-config');
                const methodBadge = row.querySelector('[class*="method-badge-"]');
                const method = methodBadge ? methodBadge.textContent : '';

                switch (this.currentFilter) {
                    case 'all':
                        shouldShow = true;
                        break;
                    case 'running':
                        shouldShow = isRunning;
                        break;
                    case 'stopped':
                        shouldShow = !isRunning;
                        break;
                    case 'method-1':
                        shouldShow = method.includes('۱') || method.includes('1');
                        break;
                    case 'method-2':
                        shouldShow = method.includes('۲') || method.includes('2');
                        break;
                    case 'method-3':
                        shouldShow = method.includes('۳') || method.includes('3');
                        break;
                    case 'newest':
                        shouldShow = true;
                        break;
                    default:
                        shouldShow = true;
                }

                // Then apply search filter
                if (shouldShow && searchTerm) {
                    shouldShow = this.shouldShowConfig(row, searchTerm);
                }

                if (shouldShow) {
                    row.classList.remove('hidden');
                    row.classList.add('fade-animation');
                    visibleCount++;

                    // Highlight search terms
                    if (searchTerm) {
                        this.highlightText(row, searchTerm);
                    } else {
                        this.removeHighlights(row);
                    }
                } else {
                    row.classList.add('hidden');
                    row.classList.remove('fade-animation');
                    this.removeHighlights(row);
                }
            });

            this.updateResults(visibleCount);
        }

        sortConfigs() {
            const container = document.querySelector('.space-y-3');
            if (!container) return;

            const configsArray = Array.from(this.configRows);

            // Sort based on current filter
            if (this.currentFilter === 'newest') {
                // Sort by newest first
                configsArray.sort((a, b) => {
                    const aTime = this.getConfigTime(a);
                    const bTime = this.getConfigTime(b);
                    return bTime - aTime;
                });
            } else {
                // مرتب‌سازی الفبایی انگلیسی (پیش‌فرض)
                configsArray.sort((a, b) => {
                    const aName = this.getConfigName(a);
                    const bName = this.getConfigName(b);
                    // استفاده از مقایسه انگلیسی به جای فارسی
                    return aName.toLowerCase().localeCompare(bName.toLowerCase(), 'en', { numeric: true });
                });
            }

            // Re-append sorted elements
            configsArray.forEach(config => container.appendChild(config));

            // Update configRows reference
            this.configRows = document.querySelectorAll('.config-row');
        }

        getConfigName(row) {
            const nameElement = row.querySelector('.font-bold');
            const filename = nameElement ? nameElement.textContent.trim() : '';
            // حذف پسوند .json اگر وجود دارد برای مرتب‌سازی بهتر
            return filename.replace('.json', '');
        }

        getConfigTime(row) {
            // Try to get timestamp from started_at or use DOM order as fallback
            const timeElement = row.querySelector('.text-xs.text-gray-500');
            if (timeElement) {
                const timeText = timeElement.textContent.trim();
                // Convert Persian/Arabic numbers to English for parsing
                const englishTime = timeText.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
                const date = new Date(englishTime);
                return date.getTime() || 0;
            }

            // Fallback: use position in DOM (newer elements typically added later)
            const allRows = Array.from(document.querySelectorAll('.config-row'));
            return allRows.indexOf(row);
        }

        shouldShowConfig(row, searchTerm) {
            if (!searchTerm) return true;

            // Get all text content from the row
            const allText = row.textContent.toLowerCase();

            // Simple search - just check if the search term exists anywhere in the row
            return allText.includes(searchTerm.toLowerCase());
        }

        highlightText(row, searchTerm) {
            // Remove existing highlights
            this.removeHighlights(row);

            // Find text nodes and highlight matches
            const textNodes = this.getTextNodes(row);
            textNodes.forEach(node => {
                const text = node.textContent.toLowerCase();
                const originalText = node.textContent;

                if (text.includes(searchTerm)) {
                    const regex = new RegExp(`(${this.escapeRegex(searchTerm)})`, 'gi');
                    const highlightedText = originalText.replace(regex, '<span class="highlight">$1</span>');

                    const wrapper = document.createElement('span');
                    wrapper.innerHTML = highlightedText;
                    node.parentNode.replaceChild(wrapper, node);
                }
            });
        }

        removeHighlights(row) {
            const highlights = row.querySelectorAll('.highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
                parent.normalize();
            });
        }

        getTextNodes(element) {
            const textNodes = [];
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: (node) => {
                        // Skip text nodes inside buttons, inputs, etc.
                        const parent = node.parentElement;
                        if (parent.tagName === 'BUTTON' || parent.tagName === 'INPUT' ||
                            parent.classList.contains('btn') || parent.classList.contains('badge')) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        return node.textContent.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                    }
                }
            );

            let node;
            while (node = walker.nextNode()) {
                textNodes.push(node);
            }

            return textNodes;
        }

        escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        updateResults(visibleCount = null) {
            if (visibleCount === null) {
                visibleCount = this.configRows.length - document.querySelectorAll('.config-row.hidden').length;
            }

            const totalCount = this.configRows.length;

            if (this.currentSearch || this.currentFilter !== 'all') {
                if (visibleCount === 0) {
                    this.searchResults.innerHTML = `
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <div>هیچ کانفیگی یافت نشد</div>
                        <div style="font-size: 12px; margin-top: 8px;">جستجوی خود را تغییر دهید یا فیلترها را بررسی کنید</div>
                    </div>
                `;
                } else {
                    this.searchResults.innerHTML = `
                    <i class="fas fa-filter text-gold-500"></i>
                    نمایش ${visibleCount} از ${totalCount} کانفیگ
                `;
                }
            } else {
                this.searchResults.innerHTML = `
                <i class="fas fa-list text-gold-500"></i>
                ${totalCount} کانفیگ موجود
            `;
            }
        }
    }

    // Initialize search when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Initial alphabetical sort of all configs
        const sortConfigsAlphabetically = () => {
            const containers = document.querySelectorAll('.space-y-3');
            containers.forEach(container => {
                const configs = Array.from(container.querySelectorAll('.config-row'));
                configs.sort((a, b) => {
                    const aName = a.querySelector('.font-bold')?.textContent.trim() || '';
                    const bName = b.querySelector('.font-bold')?.textContent.trim() || '';
                    // مرتب‌سازی انگلیسی به جای فارسی
                    return aName.toLowerCase().localeCompare(bName.toLowerCase(), 'en', { numeric: true });
                });
                configs.forEach(config => container.appendChild(config));
            });
        };

        // Sort configs alphabetically on page load
        sortConfigsAlphabetically();

        // Initialize search functionality
        new ConfigSearch();
    });

    // Pause auto-refresh when user is actively searching
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('focus', stopAutoRefresh);
            searchInput.addEventListener('blur', () => {
                setTimeout(startAutoRefresh, 2000); // Resume after 2 seconds
            });
        }
    });

</script>

</body>
</html>
