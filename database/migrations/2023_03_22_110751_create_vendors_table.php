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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('zip_code')->nullable();
            $table->string('state')->nullable();
            $table->string('phone_number');
            $table->string('country')->nullable();
            $table->string('image')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('link')->nullable();
            $table->string('login_id')->nullable();
            $table->string('password')->nullable();
            $table->string('bank_info')->nullable();
            $table->string('paypal_info')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
