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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('stock_no_unique')->index('stock_no_unique');
            $table->string('stock_name_discription')->nullable();
            $table->integer('section_id')->nullable();
            $table->float('sku_weight')->nullable();
            $table->integer('re_order_qty')->nullable();
            $table->integer('min_reorder')->nullable();
            $table->integer('sales_30')->nullable();
            $table->integer('sales_90')->nullable();
            $table->integer('sales_180')->nullable();
            $table->integer('total_sale')->default('0');
            $table->float('qty_on_hand')->nullable()->default('0');
            $table->integer('qty_user_id')->nullable();
            $table->dateTime('qty_date')->nullable();
            $table->float('qty_alloc')->nullable()->default('0');
            $table->float('qty_av')->nullable()->default('0');
            $table->integer('total_purchase')->default('0');
            $table->integer('qty_exp')->default('0');
            $table->float('until_reorder')->default('0');
            $table->decimal('last_cost', 10,2)->default('0.00');
            $table->decimal('value', 10,2)->default('0.00');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('upc')->nullable();
            $table->string('wh_bin')->nullable();
            $table->string('warehouse')->nullable();
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
        Schema::dropIfExists('inventories');
    }
};
