<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A lançamento's owner no longer gets a parte row of their own — their
     * part is what the participants leave. Drops the ones written before
     * that rule; nothing is lost, since the amount is implied.
     */
    public function up(): void
    {
        DB::table('lancamento_partes')
            ->whereIn('id', DB::table('lancamento_partes')
                ->join('lancamentos', 'lancamentos.id', '=', 'lancamento_partes.lancamento_id')
                ->whereColumn('lancamentos.user_id', 'lancamento_partes.user_id')
                ->pluck('lancamento_partes.id'))
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
