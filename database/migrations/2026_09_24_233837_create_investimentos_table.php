<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('institution')->nullable();
            $table->string('type'); // tesouro_selic | cdb | lci | lca | fundo | acoes | etfs | cripto | outro
            $table->decimal('rate', 8, 4); // e.g. 100 (=100% do CDI) or 12.5 (=12,5% a.a. fixa)
            $table->string('rate_reference')->nullable(); // CDI | Selic | IPCA | Fixa | Outro
            $table->string('rate_period')->default('anual'); // mensal | anual
            $table->string('liquidity')->nullable();
            $table->date('maturity_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('ativo'); // ativo | encerrado
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investimentos');
    }
};
