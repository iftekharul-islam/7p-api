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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->date('po_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('grand_total', 8, 2)->nullable();
            $table->integer('tracking')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
