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
        Schema::create('curso_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curso_files');
    }
};
