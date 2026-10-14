<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An invitation from one user (owner) for another (participant) to
     * take part in one of the owner's projects or in their Financeiro.
     * It only grants access once the participant accepts it.
     */
    public function up(): void
    {
        Schema::create('participacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo'); // projeto | financas
            $table->foreignId('projeto_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('permission')->default('ver'); // ver | editar
            $table->json('sections')->nullable(); // financas only: which sections the participant sees
            $table->string('status')->default('pendente'); // pendente | aceito | recusado
            $table->timestamps();

            $table->index(['participant_id', 'status']);
            $table->index(['owner_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participacoes');
    }
};
