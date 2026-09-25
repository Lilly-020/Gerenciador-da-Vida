<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every contribution to an investment is its own row — never collapsed
     * into a single running total — so the estimated yield can account for
     * how long each individual amount has actually been invested.
     */
    public function up(): void
    {
        Schema::create('investimento_aportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investimento_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->decimal('rate_override', 8, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['investimento_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investimento_aportes');
    }
};
