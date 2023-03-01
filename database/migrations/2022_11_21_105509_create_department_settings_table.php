<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('department_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('department_id');
            $table->integer('hierarchy_level')->nullable();
            $table->boolean('include_department_head')->nullable();
            $table->boolean('include_division_head')->nullable();
            $table->integer('include_special_access_id')->nullable();
            $table->boolean('include_hr')->nullable();
            $table->boolean('include_final_approver')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('department_settings');
    }
}
