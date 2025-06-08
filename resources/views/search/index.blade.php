<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست جستجوی Elasticsearch</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 90%;
            max-width: 600px;
        }

        .search-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .search-header h1 {
            color: #333;
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
        }

        .search-header p {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 1.1rem;
        }

        .search-form {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 20px 50px 20px 20px;
            font-size: 18px;
            border: 2px solid #e1e5e9;
            border-radius: 50px;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 20px;
        }

        .results-container {
            position: relative;
        }

        .results-list {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }

        .results-list.show {
            display: block;
        }

        .result-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .result-item:hover {
            background-color: #f8f9fa;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-text {
            color: #333;
            text-decoration: none;
            font-size: 16px;
        }

        .result-type {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .result-type.category {
            background: #28a745;
        }

        .result-type.product {
            background: #ffc107;
            color: #333;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }

        .error-message {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 10px;
            margin: 10px 0;
            text-align: center;
        }

        .loading {
            padding: 20px;
            text-align: center;
            color: #666;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .stats {
            padding: 10px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="search-container">
    <div class="search-header">
        <h1>🔍 جستجوی هوشمند</h1>
        <p>تست Elasticsearch روی لوکال</p>
    </div>

    <form class="search-form">
        <input type="text" id="search-input" class="search-input" placeholder="جستجو کنید..." autocomplete="off">
        <span class="search-icon">🔍</span>
    </form>

    <div class="results-container">
        <div id="results-list" class="results-list">
            <!-- نتایج اینجا نمایش داده می شوند -->
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function () {
        let typingTimer;
        const doneTypingInterval = 300; // میلی‌ثانیه
        const $input = $('#search-input');
        const $resultsList = $('#results-list');

        // تنظیم CSRF token برای Ajax
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $input.on('keyup', function () {
            clearTimeout(typingTimer);
            const query = $(this).val().trim();

            if (query.length > 0) {
                typingTimer = setTimeout(() => performSearch(query), doneTypingInterval);
            } else {
                hideResults();
            }
        });

        $input.on('keydown', function () {
            clearTimeout(typingTimer);
        });

        function performSearch(query) {
            showLoading();

            $.ajax({
                url: "{{ route('search.api') }}",
                type: "POST",
                data: {
                    search: query
                },
                success: function (data) {
                    displayResults(data);
                },
                error: function (xhr) {
                    displayError('خطا در بارگذاری نتایج');
                }
            });
        }

        function showLoading() {
            $resultsList.html('<div class="loading">در حال جستجو... <span class="spinner"></span></div>').addClass('show');
        }

        function hideResults() {
            $resultsList.removeClass('show').html('');
        }

        function displayError(message) {
            $resultsList.html(`<div class="error-message">${message}</div>`).addClass('show');
        }

        function displayResults(data) {
            if (data.error) {
                displayError(data.error);
                return;
            }

            let html = '';
            let totalResults = 0;

            // نمایش دسته‌بندی‌ها
            if (data.categories && data.categories.length > 0) {
                totalResults += data.categories.length;
                data.categories.forEach(function (category) {
                    html += `
                            <div class="result-item" onclick="window.open('/category/${category.slug}', '_blank')">
                                <span class="result-text">دسته: ${category.name}</span>
                                <span class="result-type category">دسته‌بندی</span>
                            </div>
                        `;
                });
            }

            // نمایش محصولات
            if (data.products && data.products.length > 0) {
                totalResults += data.products.length;
                data.products.forEach(function (product) {
                    html += `
                            <div class="result-item" onclick="window.open('/product/${product.slug}', '_blank')">
                                <span class="result-text">${product.name} ${product.price ? '- ' + product.price : ''}</span>
                                <span class="result-type product">محصول</span>
                            </div>
                        `;
                });
            }

            // نمایش پیشنهادات
            if (data.suggestions && data.suggestions.length > 0) {
                data.suggestions.forEach(function (suggestion) {
                    html += `
                            <div class="result-item" onclick="window.open('/category/${suggestion.slug}', '_blank')">
                                <span class="result-text">پیشنهاد: ${suggestion.name}</span>
                                <span class="result-type">پیشنهاد</span>
                            </div>
                        `;
                });
            }

            if (html === '') {
                html = '<div class="no-results">موردی یافت نشد 😔</div>';
            } else {
                html = `<div class="stats">تعداد نتایج: ${totalResults}</div>` + html;
            }

            $resultsList.html(html).addClass('show');
        }

        // بستن نتایج هنگام کلیک خارج از المان
        $(document).click(function (e) {
            if (!$(e.target).closest('.search-container').length) {
                hideResults();
            }
        });
    });
</script>
</body>
</html>
