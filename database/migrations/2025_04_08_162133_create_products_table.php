<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('price')->nullable();
            $table->string('product_id')->nullable();
            $table->string('page_url', 767)->unique();
            $table->integer('availability')->default(0);
            $table->string('image')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('brand')->nullable(); // اضافه کردن فیلد جدید
            $table->float('off')->default(0);
            $table->string('guarantee')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
}
