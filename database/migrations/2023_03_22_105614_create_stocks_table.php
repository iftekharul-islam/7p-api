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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number')->nullable();
            $table->string('description')->nullable();
            $table->integer('section_id')->nullable();
            $table->integer('weight')->nullable();
            $table->integer('order_quantity')->nullable();
            $table->integer('minimum_stock_quantity')->nullable();
            $table->integer('last_cost')->nullable();
            $table->string('upc')->nullable();
            $table->string('bin')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
