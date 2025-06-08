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
                        gold: {
                            50: '#fffef7',
                            100: '#fefcf0',
                            200: '#fef08a',
                            300: '#fde047',
                            400: '#facc15',
                            500: '#eab308',
                            600: '#ca8a04',
                            700: '#a16207',
                            800: '#854d0e',
                            900: '#713f12'
                        },
                        method: {
                            1: '#e879f9',
                            2: '#4ade80', 
                            3: '#fb923c'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: "Vazirmatn", system-ui;
            background: linear-gradient(135deg, #fffef7 0%, #fefcf0 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(234, 179, 8, 0.2);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .running-badge {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
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
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: white;
            box-shadow: 0 2px 8px rgba(234, 179, 8, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #ca8a04, #a16207);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-1px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }

        .btn-info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
        }

        .btn-purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }

        .btn-purple:hover {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            transform: translateY(-1px);
        }

        .config-row {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(234, 179, 8, 0.1);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }

        .config-row:hover {
            background: rgba(255, 255, 255, 0.8);
            border-color: rgba(234, 179, 8, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .running-config {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.05));
            border: 2px solid rgba(34, 197, 94, 0.3);
            position: relative;
            overflow: hidden;
        }

        .running-config::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #22c55e, #4ade80, #22c55e);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        .empty-state {
            background: rgba(255, 255, 255, 0.4);
            border: 2px dashed rgba(234, 179, 8, 0.3);
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
            color: #a16207;
        }

        .title-gradient {
            background: linear-gradient(135deg, #eab308, #ca8a04);
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
            background: linear-gradient(90deg, transparent, #eab308, transparent);
        }

        .section-title::before {
            right: 100%;
        }

        .section-title::after {
            left: 100%;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(234, 179, 8, 0.2);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
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
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.05));
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #15803d;
        }

        .notification-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #b91c1c;
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

        <!-- Running Configs Section -->
        @php
            $runningConfigs = array_filter($configs, fn($c) => isset($c['status']) && $c['status'] === 'running');
            usort($runningConfigs, fn($a, $b) => strcmp($b['started_at'] ?? '0', $a['started_at'] ?? '0'));
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
                                        <span><i class="fas fa-clock ml-1"></i>{{ $config['started_at'] }}</span>
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
                        usort($sortedConfigs, fn($a, $b) => ($a['content']['method'] ?? 0) <=> ($b['content']['method'] ?? 0));
                    @endphp
                    
                    @foreach($sortedConfigs as $index => $config)
                        <div class="config-row {{ (isset($config['status']) && $config['status'] === 'running') ? 'running-config' : '' }}">
                            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="text-gold-600 font-bold text-lg min-w-[30px]">{{ $index + 1 }}</div>
                                    <div>
                                        <div class="font-bold text-lg">{{ $config['filename'] }}</div>
                                        @if(isset($config['started_at']))
                                            <div class="text-xs text-gray-500">{{ $config['started_at'] }}</div>
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
        // Auto-refresh running configs every 10 seconds
        setInterval(() => {
            location.reload();
        }, 10000);
    </script>
</body>
</html>
