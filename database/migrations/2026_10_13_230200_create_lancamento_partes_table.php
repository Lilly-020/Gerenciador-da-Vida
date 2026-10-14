<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a lançamento in a shared carteira is divided: how much of it
     * each participant paid (or received).
     */
    public function up(): void
    {
        Schema::create('lancamento_partes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lancamento_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['lancamento_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamento_partes');
    }
};
