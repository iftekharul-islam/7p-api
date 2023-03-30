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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_name')->nullable();
            $table->string('summaries')->nullable()->default('0');
            $table->string('start_finish')->nullable()->default('0');
            $table->string('same_user')->nullable()->default('0');
            $table->string('print_label')->nullable()->default('0');
            $table->string('inventory')->nullable()->default('0');
            $table->string('inv_control')->nullable()->default('0');
            $table->string('ret_name')->nullable();
            $table->string('ret_address_1')->nullable();
            $table->string('ret_address_2')->nullable();
            $table->string('ret_city')->nullable();
            $table->string('ret_state')->nullable();
            $table->string('ret_zipcode')->nullable();
            $table->string('ret_phone_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
