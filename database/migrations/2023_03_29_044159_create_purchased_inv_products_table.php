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
        Schema::create('purchased_inv_products', function (Blueprint $table) {
            $table->id();
            $table->string('stock_no')->index('stock_no')->comment('code');
            $table->string('unit')->nullable();
            $table->float('unit_price')->default('0');
            $table->integer('unit_qty')->default('1');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('vendor_sku')->nullable();
            $table->string('vendor_sku_name')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchased_inv_products');
    }
};
