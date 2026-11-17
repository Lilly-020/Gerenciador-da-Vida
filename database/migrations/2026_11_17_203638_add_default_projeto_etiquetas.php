<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['name' => 'Realizado', 'color' => 'emerald'],
            ['name' => 'Importante', 'color' => 'amber'],
            ['name' => 'Melhorias', 'color' => 'orange'],
            ['name' => 'Não urgente', 'color' => 'rose'],
            ['name' => 'Novo módulo', 'color' => 'violet'],
            ['name' => 'Urgente', 'color' => 'blue'],
            ['name' => 'Ideia', 'color' => 'cyan'],
        ];
        DB::table('projetos')->select('id')->orderBy('id')->chunkById(100, function (Collection $projects) use ($defaults): void {
            $rows = [];
            $timestamp = now();
            foreach ($projects as $project) {
                foreach ($defaults as $label) {
                    $rows[] = [...$label, 'projeto_id' => $project->id, 'selected' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp];
                }
            }
            DB::table('projeto_etiquetas')->insertOrIgnore($rows);
        });
    }

    /**
     * Presets become editable user data and must survive a rollback.
     */
    public function down(): void {}
};
