<?php

use App\Models\ProjetoEtiqueta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quadros', function (Blueprint $table): void {
            $table->json('label_catalog')->nullable();
        });
        Schema::table('quadro_cartoes', function (Blueprint $table): void {
            $table->json('checklists')->nullable();
        });
        Schema::table('quadro_comentarios', function (Blueprint $table): void {
            $table->text('body_html')->nullable();
            $table->json('file_ids')->nullable();
            $table->boolean('pinned')->default(false);
            $table->json('reactions')->nullable();
        });
        DB::table('quadros')->orderBy('id')->each(function (object $board): void {
            $catalog = collect(ProjetoEtiqueta::DEFAULTS)->map(fn (array $label): array => [...$label, 'id' => (string) Str::uuid()])->all();
            $names = array_column($catalog, 'name');
            foreach (DB::table('quadro_cartoes')->where('quadro_id', $board->id)->pluck('labels') as $labels) {
                foreach (json_decode($labels ?? '[]', true) as $name) {
                    if (!in_array($name, $names, true)) {
                        $catalog[] = ['id' => (string) Str::uuid(), 'name' => $name, 'color' => 'violet'];
                        $names[] = $name;
                    }
                }
            }
            DB::table('quadros')->where('id', $board->id)->update(['label_catalog' => json_encode($catalog)]);
        });
        DB::table('quadro_cartoes')->whereNotNull('checklist')->orderBy('id')->each(function (object $card): void {
            $items = json_decode($card->checklist, true);
            if ($items !== []) {
                DB::table('quadro_cartoes')->where('id', $card->id)->update(['checklists' => json_encode([['id' => (string) Str::uuid(), 'title' => 'Checklist', 'items' => $items]])]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quadro_comentarios', function (Blueprint $table): void {
            $table->dropColumn(['body_html', 'file_ids', 'pinned', 'reactions']);
        });
        Schema::table('quadro_cartoes', function (Blueprint $table): void {
            $table->dropColumn('checklists');
        });
        Schema::table('quadros', function (Blueprint $table): void {
            $table->dropColumn('label_catalog');
        });
    }
};
