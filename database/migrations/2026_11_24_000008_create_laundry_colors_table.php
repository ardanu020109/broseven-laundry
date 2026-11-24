<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_colors', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('label', 60);
            $table->string('value', 7);
            $table->string('default_value', 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_colors');
    }
};