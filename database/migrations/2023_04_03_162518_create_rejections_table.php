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
        Schema::create('rejections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->index('item_id')->nullable();
            $table->string('complete')->nullable();
            $table->tinyInteger('graphic_status')->nullable();
            $table->integer('scrap')->nullable();
            $table->integer('rejection_reason')->nullable();
            $table->string('rejection_message')->nullable();
            $table->integer('reject_qty')->nullable();
            $table->integer('rejection_user_id')->nullable();
            $table->string('supervisor_message')->nullable();
            $table->unsignedBigInteger('supervisor_user_id')->index('supervisor_user_id')->nullable();
            $table->unsignedBigInteger('from_station_id')->index('from_station_id')->nullable();
            $table->unsignedBigInteger('to_station_id')->index('to_station_id')->nullable();
            $table->string('from_batch')->nullable();
            $table->string('to_batch')->nullable();
            $table->string('from_screen')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rejections');
    }
};
