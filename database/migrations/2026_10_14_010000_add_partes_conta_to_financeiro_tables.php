<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whose account a divided expense's participant parts come out of:
     * each participant's own ("participante", the default) or the
     * carteira owner's ("dono", who then pays the whole amount).
     */
    public function up(): void
    {
        foreach (['lancamentos', 'custo_fixos', 'custo_fixo_ajustes'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('partes_conta')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['lancamentos', 'custo_fixos', 'custo_fixo_ajustes'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('partes_conta');
            });
        }
    }
};
