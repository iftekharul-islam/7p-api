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
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('station_name');
            $table->string('station_description');
            $table->string('station_status');
            $table->integer('section');
            $table->unsignedBigInteger('type')->index('type')->comment('X G P Q');
            $table->string('start_finish')->default('0')->comment('0 1');
            $table->string('same_user')->default('0')->comment('0 1');
            $table->string('graphic_type')->default('N')->comment('N P F');
            $table->string('printer_type')->default('0')->comment('0 D Z');
            $table->string('print_label')->default('0')->comment('0 1');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
