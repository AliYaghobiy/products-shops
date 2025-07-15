<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'dynamic'; // اتصال به دیتابیس داینامیک

    protected $fillable = [
        'title',
        'price',
        'product_id',
        'page_url',
        'availability',
        'image',
        'category',
        'description',
        'off',
        'guarantee',
        'brand', // اضافه کردن فیلد جدید
    ];

    protected $casts = [
        'images' => 'array',
        'categories' => 'array',
        'availability' => 'integer',
        'off' => 'integer',
    ];
}
