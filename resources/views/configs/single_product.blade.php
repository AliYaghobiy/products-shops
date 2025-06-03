<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست تک محصول</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f9f9f9;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .success {
            color: green;
            margin-bottom: 10px;
        }

        .logs {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            font-family: monospace;
            white-space: pre-wrap;
        }

        .logs h3 {
            margin-top: 0;
        }

        .log-line {
            margin: 2px 0;
        }

        .log-green {
            color: #28a745;
        }

        .log-red {
            color: #dc3545;
        }

        .log-yellow {
            color: #ffc107;
        }

        .log-blue {
            color: #007bff;
        }

        .log-purple {
            color: #6f42c1;
        }

        .log-cyan {
            color: #17a2b8;
        }

        .log-gray {
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>تست تک محصول</h1>

    <!-- نمایش پیام‌های موفقیت یا خطا -->
    @if (session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فرم تست محصول -->
    <form method="POST" action="{{ route('configs.single_product') }}">
        @csrf
        <div class="form-group">
            <label for="product_url">آدرس محصول:</label>
            <input type="url" name="product_url" id="product_url"
                   value="{{ old('product_url', 'https://yaradarman.com/product/advanced-wound-healing-gel/') }}"
                   required>
        </div>
        <div class="form-group">
            <label for="title_selector">سلکتور عنوان:</label>
            <input type="text" name="title_selector" id="title_selector"
                   value="{{ old('title_selector', '.elementor-element-e499a81 > div:nth-child(1) > h1:nth-child(1)') }}"
                   required>
        </div>
        <div class="form-group">
            <label for="price_selector">سلکتور قیمت:</label>
            <input type="text" name="price_selector" id="price_selector"
                   value="{{ old('price_selector', 'p.price > span:nth-child(1) > bdi:nth-child(1)') }}" required>
        </div>
        <div class="form-group">
            <label for="category_selector">سلکتور دسته‌بندی:</label>
            <input type="text" name="category_selector" id="category_selector"
                   value="{{ old('category_selector', '.posted_in > a:nth-child(2)') }}">
        </div>
        <div class="form-group">
            <label for="availability_selector">سلکتور موجودی:</label>
            <input type="text" name="availability_selector" id="availability_selector"
                   value="{{ old('availability_selector', '.elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)') }}">
        </div>
        <div class="form-group">
            <label for="image_selector">سلکتور تصویر:</label>
            <input type="text" name="image_selector" id="image_selector"
                   value="{{ old('image_selector', '.woocommerce-product-gallery__image > a') }}">
        </div>
        <div class="form-group">
            <label for="product_id_selector">سلکتور شناسه محصول:</label>
            <input type="text" name="product_id_selector" id="product_id_selector"
                   value="{{ old('product_id_selector', '.elementor-element-a9a2f64 > div:nth-child(1) > form:nth-child(2) > button:nth-child(2)') }}">
        </div>
        <button type="submit" class="btn">تست محصول</button>
    </form>

    <!-- نمایش نتیجه تست -->
    @if (isset($result))
        <div class="result">
            <h3>نتیجه تست:</h3>
            <p><strong>وضعیت:</strong> {{ $result['status'] }}</p>
            <p><strong>پیام:</strong> {{ $result['message'] ?? 'تست با موفقیت انجام شد' }}</p>
            <p><strong>تعداد محصولات استخراج‌شده:</strong> {{ $result['total_products'] ?? 0 }}</p>
            <p><strong>نرخ موفقیت:</strong> {{ $result['success_rate'] ?? 0 }}%</p>
            <p><strong>لینک‌های ناموفق:</strong> {{ $result['failed_links'] ?? 0 }}</p>

            @if (!empty($result['products']))
                <h4>محصولات استخراج‌شده:</h4>
                @foreach ($result['products'] as $index => $product)
                    <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <h5>محصول {{ $index + 1 }}:</h5>
                        <ul>
                            <li><strong>عنوان:</strong> {{ $product['title'] ?? 'N/A' }}</li>
                            <li><strong>قیمت:</strong> {{ $product['price'] ?? 'N/A' }}</li>
                            <li><strong>شناسه محصول:</strong> {{ $product['product_id'] ?? 'N/A' }}</li>
                            <li><strong>دسته‌بندی:</strong> {{ $product['category'] ?? 'N/A' }}</li>
                            <li>
                                <strong>موجودی:</strong> {{ isset($product['availability']) ? ($product['availability'] ? 'موجود' : 'ناموجود') : 'N/A' }}
                            </li>
                            <li>
                                <strong>تصویر:</strong> {{ $product['image'] ? '<a href="' . $product['image'] . '" target="_blank">مشاهده</a>' : 'N/A' }}
                            </li>
                            <li><strong>تخفیف:</strong> {{ $product['off'] ?? '0' }}%</li>
                            <li><strong>گارانتی:</strong> {{ $product['guarantee'] ?? 'N/A' }}</li>
                            <li><strong>آدرس صفحه:</strong> <a href="{{ $product['page_url'] ?? '#' }}"
                                                               target="_blank">{{ $product['page_url'] ?? 'N/A' }}</a>
                            </li>
                        </ul>
                    </div>
                @endforeach
            @endif

            @if (!empty($result['failed_urls']))
                <h4>لینک‌های ناموفق:</h4>
                <ul>
                    @foreach ($result['failed_urls'] as $url)
                        <li>{{ $url }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
    
</div>
</body>
</html>
?>
