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
        Schema::create('sonho_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sonho_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sonho_tasks');
    }
};
