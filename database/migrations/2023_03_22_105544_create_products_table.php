<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('stock_id')->nullable();
            $table->string('name')->nullable();
            $table->string('sku_weight')->nullable();
            $table->integer('re_order_qty')->nullable();
            $table->integer('min_order')->nullable();
            $table->string('adjusment')->nullable();
            $table->string('unit')->nullable();
            $table->string('qty')->nullable();
            $table->string('unit_price')->nullable();
            $table->string('vendor_id')->nullable();
            $table->string('vendor_sku')->nullable();
            $table->string('sku_name')->nullable();
            $table->string('lead_time_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
