<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request, string $store)
    {
        \Log::info("Requested store: {$store}");

        try {
            $this->setupDynamicConnection($store);
        } catch (\Exception $e) {
            \Log::error("Database connection error for store {$store}: {$e->getMessage()}");
            // ریدایرکت به صفحه لیست API‌ها
            return redirect()->route('api.index');
        }

        $page = max(1, (int)$request->query('page', 1));
        $productsPerPage = 100;

        $totalProducts = Product::count();
        \Log::info("Total products: {$totalProducts}");

        $maxPages = ceil($totalProducts / $productsPerPage);
        \Log::info("Max pages: {$maxPages}");

        if ($page > $maxPages) {
            return response()->json([
                'error' => "شماره صفحه نامعتبر است! فقط بین 1 تا {$maxPages} مجاز است"
            ], 400);
        }

        $offset = ($page - 1) * $productsPerPage;
        \Log::info("Page: {$page}, Offset: {$offset}, Products per page: {$productsPerPage}");

        $products = Product::select([
            'title',
            'price',
            'product_id',
            'page_url',
            'availability',
            'image',
            'category',
            'brand',
            'off',
            'guarantee'
        ])
            ->orderBy('id')
            ->offset($offset)
            ->limit($productsPerPage)
            ->get();

        \Log::info("Products count for page {$page}: " . $products->count());

        if ($products->isEmpty()) {
            return response()->json([
                'error' => "هیچ محصولی برای صفحه {$page} پیدا نشد!"
            ], 404);
        }

        $output = [
            'total_pages_count' => $maxPages,
            'current_page' => $page,
            'total_products' => $totalProducts,
            'products' => $products->map(function ($product) {
                return [
                    'title' => $product->title,
                    'price' => $product->price,
                    'product_id' => $product->product_id,
                    'page_url' => $product->page_url,
                    'availability' => (int)$product->availability,
                    'off' => (float)$product->off,
                    'image' => $product->image,
                    'guarantee' => $product->guarantee,
                    'category' => $product->category,
                    'brand' => $product->brand,
                ];
            })->all()
        ];

        return response()->json($output);
    }

    private function setupDynamicConnection(string $store): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $store)) {
            \Log::error("Invalid database name: {$store}");
            throw new \Exception("نام دیتابیس نامعتبر است: {$store}");
        }

        Config::set('database.connections.dynamic', [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $store,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        DB::purge('dynamic');

        try {
            DB::connection('dynamic')->getPdo();
            \Log::info("Successfully connected to database: {$store}");
        } catch (\Exception $e) {
            \Log::error("Failed to connect to database {$store}: {$e->getMessage()}");
            throw new \Exception("دیتابیس '{$store}' یافت نشد یا خطایی رخ داد: {$e->getMessage()}");
        }
    }
}
