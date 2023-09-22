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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_5p')->nullable()->index('order_5p');
            $table->string('order_id')->nullable()->index('order_id');
            $table->string('store_id')->nullable()->index('store_id');
            $table->unsignedBigInteger('manufacture_id')->nullable()->index('manufacture_id');
            $table->string('item_code')->nullable()->index('item_code');
            $table->string('child_sku')->nullable()->index('child_sku');
            $table->string('item_description')->nullable();
            $table->string('item_id')->nullable()->index('item_id');
            $table->string('item_option')->nullable();
            $table->integer('item_quantity')->nullable();
            $table->string('item_thumb')->nullable();
            $table->decimal('item_unit_price', 8,2)->default('0.00');
            $table->string('item_url')->nullable();
            $table->string('item_taxable')->nullable();
            $table->string('tracking_number')->nullable()->index('tracking_number');
            $table->string('batch_number')->nullable()->index('batch_number');
            $table->string('data_parse_type')->nullable();
            $table->string('data_parse_type')->nullable();
            $table->string('item_status')->index('item_status')->default('1');
            $table->string('reached_shipping_station')->default('0');
            $table->string('sure3d')->nullable();
            $table->string('edi_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
