<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست کیفیت دیتابیس {{ $filename }}</title>
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

        .test-console {
            background: linear-gradient(145deg, #1e293b, #334155);
            border: 2px solid #64748b;
            border-radius: 16px;
            min-height: 600px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
            white-space: pre-wrap;
            color: #e2e8f0;
            font-size: 0.875rem;
            line-height: 1.6;
            position: relative;
            padding: 20px;
            direction: ltr;
        }

        .test-console::before {
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

        .test-console::-webkit-scrollbar {
            width: 8px;
        }

        .test-console::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 4px;
        }

        .test-console::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            border-radius: 4px;
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
            gap: 8px;
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

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #475569, #334155);
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

        .success-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .error-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .title-gradient {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .test-line {
            margin-bottom: 0.5rem;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .test-success {
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            border-left-color: #10b981;
            font-weight: 600;
        }

        .test-warning {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
            border-left-color: #f59e0b;
            font-weight: 600;
        }

        .test-error {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            border-left-color: #ef4444;
            font-weight: 600;
        }

        .test-info {
            color: #06b6d4;
            background: rgba(6, 182, 212, 0.1);
            border-left-color: #06b6d4;
            font-weight: 600;
        }

        .test-header {
            color: #8b5cf6;
            background: rgba(139, 92, 246, 0.1);
            border-left-color: #8b5cf6;
            font-weight: 700;
            font-size: 1.1em;
            padding: 12px 16px;
            margin: 16px 0 8px 0;
        }

        .test-separator {
            color: #64748b;
            background: rgba(100, 116, 139, 0.05);
            border-left-color: #64748b;
            font-weight: 400;
            font-family: monospace;
        }

        .test-stats {
            color: #7c3aed;
            background: rgba(124, 58, 237, 0.1);
            border-left-color: #7c3aed;
            font-weight: 600;
        }

        .console-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 8px;
            z-index: 1;
        }

        .console-header .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .console-header .red { background-color: #ef4444; }
        .console-header .yellow { background-color: #f59e0b; }
        .console-header .green { background-color: #10b981; }

        .console-content {
            padding-top: 50px;
            direction: ltr;
            text-align: left;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="glass-card p-6 mb-8 fade-in">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-database text-3xl text-purple-500 ml-3"></i>
                <div>
                    <h1 class="text-3xl font-bold title-gradient">تست کیفیت دیتابیس</h1>
                    <p class="text-gray-600 mt-1">کانفیگ: {{ $filename }}</p>
                    <p class="text-gray-500 text-sm">دیتابیس: {{ $database_name }}</p>
                </div>
            </div>
            <div class="flex gap-3">
                @if(isset($success) && $success)
                    <span class="success-badge">
                        <i class="fas fa-check-circle"></i>
                        تست موفق
                    </span>
                @else
                    <span class="error-badge">
                        <i class="fas fa-exclamation-circle"></i>
                        خطا در تست
                    </span>
                @endif
                <a href="{{ route('configs.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت
                </a>
            </div>
        </div>
    </div>

    <!-- Test Results -->
    <div class="glass-card p-6 fade-in">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3">
                <i class="fas fa-terminal text-xl text-purple-500"></i>
                <h2 class="text-xl font-bold text-gray-800">نتایج تست</h2>
            </div>
            <button id="refresh-test" class="btn btn-primary" onclick="refreshTest()">
                <i class="fas fa-sync-alt"></i>
                تست مجدد
            </button>
        </div>

        <div class="relative">
            <div class="test-console">
                <!-- Console Header -->
                <div class="console-header">
                    <div class="dot red"></div>
                    <div class="dot yellow"></div>
                    <div class="dot green"></div>
                    <span class="text-slate-300 text-xs mr-4">Database Quality Test</span>
                </div>

                <!-- Console Content -->
                <div class="console-content" id="test-output">
                    @if(isset($output))
                        {!! nl2br(e($output)) !!}
                    @else
                        <div class="text-gray-400">در حال اجرای تست...</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="glass-card p-6 mt-8 fade-in">
        <h3 class="text-lg font-bold text-gray-800 mb-4">عملیات سریع</h3>
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('configs.logs', $filename) }}" class="btn btn-primary">
                <i class="fas fa-file-alt"></i>
                مشاهده لاگ‌ها
            </a>
            <a href="{{ route('configs.edit', $filename) }}" class="btn btn-secondary">
                <i class="fas fa-edit"></i>
                ویرایش کانفیگ
            </a>
            <button onclick="copyResults()" class="btn btn-secondary">
                <i class="fas fa-copy"></i>
                کپی نتایج
            </button>
        </div>
    </div>
</div>

<script>
    function refreshTest() {
        const button = document.getElementById('refresh-test');
        const output = document.getElementById('test-output');

        // نمایش loading
        button.innerHTML = '<div class="loading-spinner"></div> در حال تست...';
        button.disabled = true;

        output.innerHTML = '<div class="text-yellow-400"><i class="fas fa-spinner fa-spin"></i> در حال اجرای تست دیتابیس...</div>';

        // ریلود صفحه برای تست مجدد
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    function copyResults() {
        const output = document.getElementById('test-output');
        const text = output.innerText;

        navigator.clipboard.writeText(text).then(() => {
            // نمایش پیام موفقیت
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> کپی شد!';
            button.classList.add('btn-success');

            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
            }, 2000);
        }).catch(() => {
            alert('خطا در کپی کردن نتایج');
        });
    }

    // اضافه کردن رنگ‌بندی به خروجی
    document.addEventListener('DOMContentLoaded', function() {
        const output = document.getElementById('test-output');
        if (output) {
            const lines = output.innerHTML.split('<br>');
            let coloredOutput = '';

            lines.forEach(line => {
                if (line.includes('✅') || line.includes('کامل')) {
                    coloredOutput += '<div class="test-success">' + line + '</div>';
                } else if (line.includes('⚠️') || line.includes('ناقص')) {
                    coloredOutput += '<div class="test-warning">' + line + '</div>';
                } else if (line.includes('❌') || line.includes('خطا')) {
                    coloredOutput += '<div class="test-error">' + line + '</div>';
                } else if (line.includes('🔍') || line.includes('📊') || line.includes('📈')) {
                    coloredOutput += '<div class="test-info">' + line + '</div>';
                } else {
                    coloredOutput += '<div class="test-line">' + line + '</div>';
                }
            });

            output.innerHTML = coloredOutput;
        }
    });
</script>
</body>
</html>
