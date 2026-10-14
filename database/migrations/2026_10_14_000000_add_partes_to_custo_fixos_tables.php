<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much of a fixed cost each participant of the carteira pays
     * (amount per user id) — on the cost itself, and on a single month's
     * ajuste when that month is divided differently.
     */
    public function up(): void
    {
        Schema::table('custo_fixos', function (Blueprint $table) {
            $table->json('partes')->nullable();
        });

        Schema::table('custo_fixo_ajustes', function (Blueprint $table) {
            $table->json('partes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('custo_fixos', function (Blueprint $table) {
            $table->dropColumn('partes');
        });

        Schema::table('custo_fixo_ajustes', function (Blueprint $table) {
            $table->dropColumn('partes');
        });
    }
};
