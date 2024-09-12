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
        Schema::create('rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('sort_order');
            $table->unsignedBigInteger('department_id')->index('department_id')->nullable();
            $table->unsignedBigInteger('station_id')->index('station_id')->nullable();
            $table->string('rejection_message');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rejection_reasons');
    }
};
