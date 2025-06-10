<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>لاگ‌های کانفیگ {{ $filename }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/vazirmatn/33.0.3/Vazirmatn-font-face.min.css" rel="stylesheet">
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
                        }
                    },
                    fontFamily: {
                        'vazir': ['Vazirmatn', 'system-ui', 'sans-serif'],
                    }
                },
            },
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
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(139, 92, 246, 0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
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

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .log-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.9), rgba(248,250,252,0.8));
            border: 2px solid transparent;
            background-clip: padding-box;
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .log-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #8b5cf6, #06b6d4, #10b981);
        }

        .log-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .empty-state {
            background: linear-gradient(145deg, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.6));
            border: 2px dashed #cbd5e1;
        }

        .log-console {
            background: linear-gradient(145deg, #1e293b, #334155);
            border: 2px solid #64748b;
            border-radius: 16px;
            height: 500px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            white-space: pre-wrap;
            color: #e2e8f0;
            font-size: 0.875rem;
            line-height: 1.6;
            position: relative;
        }

        .log-console::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(90deg, #475569, #64748b);
            border-bottom: 1px solid #64748b;
            border-radius: 14px 14px 0 0;
        }

        .log-console::-webkit-scrollbar {
            width: 8px;
        }

        .log-console::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 4px;
        }

        .log-console::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            border-radius: 4px;
        }

        .log-line {
            margin-bottom: 0.25rem;
            padding: 2px 0;
        }

        .log-info {
            color: #06b6d4;
        }

        .log-error {
            color: #f87171;
            background: rgba(239, 68, 68, 0.1);
            padding: 2px 4px;
            border-radius: 4px;
        }

        .log-success {
            color: #34d399;
        }

        .log-warning {
            color: #fbbf24;
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

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }
    </style>
</head>
<body class="min-h-screen">

<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <!-- Main Container -->
        <div class="glass-card rounded-3xl p-8 fade-in">

            <!-- Header Section -->
            <div class="header-card rounded-2xl p-6 mb-8">
                <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-4">
                        <div class="p-3 stats-purple rounded-xl shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800 mb-1">لاگ‌های کانفیگ</h1>
                            <p class="text-gray-600 text-sm">{{ $filename }}</p>
                        </div>
                    </div>

                    <a href="{{ route('configs.index') }}"
                       class="btn-secondary px-6 py-3 rounded-xl flex items-center gap-2 shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        بازگشت به لیست
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            @if(count($logFiles) > 0)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="glass-card rounded-xl p-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 stats-purple rounded-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800">{{ count($logFiles) }}</p>
                                <p class="text-gray-600 text-sm">فایل لاگ</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card rounded-xl p-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 stats-blue rounded-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 110-2h4z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800">{{ round(array_sum(array_column($logFiles, 'size')) / 1024, 1) }}</p>
                                <p class="text-gray-600 text-sm">کیلوبایت</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card rounded-xl p-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 stats-green rounded-lg pulse-dot">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800">زنده</p>
                                <p class="text-gray-600 text-sm">وضعیت سیستم</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Messages -->
            @if(session('success'))
                <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Logs Section -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-blue-500 rounded-full"></div>
                    <h2 class="text-2xl font-bold text-gray-800">فایل‌های لاگ</h2>
                </div>

                @if(count($logFiles) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($logFiles as $log)
                            <div class="log-card rounded-2xl p-6 card-hover">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-800 mb-2 cursor-pointer hover:text-purple-600 transition-colors"
                                            onclick="showLogContent('{{ $log['filename'] }}')">
                                            {{ \Carbon\Carbon::createFromTimestamp($log['last_modified'])->format('Y/m/d') }}
                                        </h3>
                                        <p class="text-gray-600 text-sm mb-1">
                                            {{ \Carbon\Carbon::createFromTimestamp($log['last_modified'])->format('H:i:s') }}
                                        </p>
                                        <p class="text-gray-500 text-xs">{{ $log['filename'] }}</p>
                                    </div>
                                    <div class="bg-gradient-to-br from-purple-100 to-blue-100 px-3 py-1 rounded-full">
                                        <span class="text-xs font-medium text-purple-700">
                                            {{ round($log['size'] / 1024, 1) }} KB
                                        </span>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button type="button"
                                            class="btn-primary px-4 py-2 rounded-lg text-sm flex items-center gap-2 flex-1"
                                            onclick="showLogContent('{{ $log['filename'] }}')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        مشاهده
                                    </button>

                                    <form action="{{ route('configs.logs.delete', $log['filename']) }}" method="POST"
                                          onsubmit="return confirm('آیا از حذف این فایل لاگ اطمینان دارید؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger p-2 rounded-lg">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state rounded-2xl p-12 text-center">
                        <div class="icon-bounce mb-4">
                            <svg class="w-16 h-16 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">هیچ لاگی یافت نشد</h3>
                        <p class="text-gray-500">هنوز هیچ اسکرپی برای این کانفیگ اجرا نشده است.</p>
                    </div>
                @endif
            </div>

            <!-- Log Content Section -->
            <div id="log-content-section" class="hidden fade-in">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-1 h-8 bg-gradient-to-b from-orange-500 to-red-500 rounded-full"></div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            <span id="log-title">محتوای لاگ</span>
                        </h2>
                    </div>
                    <button id="refresh-button" type="button"
                            class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        بروزرسانی
                    </button>
                </div>

                <div class="relative">
                    <div class="log-console p-6 pt-12" id="log-content"></div>
                    <!-- Console Header -->
                    <div class="absolute top-0 left-0 right-0 h-10 bg-gradient-to-r from-slate-700 to-slate-600 rounded-t-2xl flex items-center px-4 gap-2">
                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="text-slate-300 text-xs mr-4">Terminal</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            @if(count($logFiles) > 0)
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <form action="{{ route('configs.logs.deleteAllLogs') }}" method="POST"
                          onsubmit="return confirm('آیا از حذف تمام فایل‌های لاگ این کانفیگ اطمینان دارید؟ این عمل قابل بازگشت نیست!')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="config_filename" value="{{ $filename }}">
                        <button type="submit"
                                class="btn-danger px-6 py-3 rounded-xl flex items-center gap-3 shadow-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            حذف همه لاگ‌ها
                        </button>
                    </form>
                </div>
            @endif

        </div>
    </div>
</div>

<script>
    let currentLogFile = null;

    function showLogContent(logFile) {
        currentLogFile = logFile;
        document.getElementById('log-content-section').classList.remove('hidden');
        document.getElementById('log-title').textContent = 'محتوای لاگ: ' + logFile;
        fetchLogContent();
    }

    function fetchLogContent() {
        if (!currentLogFile) return;
        fetch("{{ url('configs/log-content') }}/" + currentLogFile)
            .then(response => {
                if (!response.ok) {
                    throw new Error('خطا در دریافت محتوای لاگ');
                }
                return response.text();
            })
            .then(data => {
                const logContent = document.getElementById('log-content');
                const lines = data.split('\n');
                let formattedContent = '';
                lines.forEach(line => {
                    if (!line.trim()) return;
                    let lineClass = 'log-line';
                    if (line.includes('ERROR') || line.includes('خطا') || line.includes('exception')) {
                        lineClass += ' log-error';
                    } else if (line.includes('SUCCESS') || line.includes('موفق')) {
                        lineClass += ' log-success';
                    } else if (line.includes('WARNING') || line.includes('هشدار')) {
                        lineClass += ' log-warning';
                    } else if (line.includes('INFO') || line.includes('اطلاعات')) {
                        lineClass += ' log-info';
                    }
                    const escapedLine = line
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                    formattedContent += `<div class="${lineClass}" dir="auto">${escapedLine}</div>`;
                });
                logContent.innerHTML = formattedContent;
                logContent.scrollTop = logContent.scrollHeight;
            })
            .catch(error => {
                console.error('Error fetching log content:', error);
                document.getElementById('log-content').textContent = 'خطا در دریافت محتوای لاگ: ' + error.message;
            });
    }

    document.getElementById('refresh-button').addEventListener('click', function () {
        fetchLogContent();
    });
</script>
</body>
</html>
