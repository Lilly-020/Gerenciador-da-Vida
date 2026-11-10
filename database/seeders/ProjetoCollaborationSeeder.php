<?php

namespace Database\Seeders;

use App\Models\Projeto;
use App\Models\ProjetoActivity;
use App\Models\ProjetoComment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProjetoCollaborationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $project = Projeto::with('user')->first();
        if (! $project) {
            return;
        }

        if ($project->comments()->doesntExist()) {
            ProjetoComment::factory()->for($project)->for($project->user)->create([
                'body' => 'Vamos combinar os responsáveis e revisar os prazos por aqui.',
            ]);
            ProjetoActivity::factory()->for($project)->for($project->user)->create([
                'description' => 'Adicionou um comentário de planejamento.',
            ]);
        }

        $path = 'projeto-files/demo-'.$project->id.'.txt';
        if ($project->files()->where('path', $path)->doesntExist()) {
            Storage::disk('local')->put($path, 'Documento de exemplo para o planejamento do projeto.');
            $project->files()->create([
                'user_id' => $project->user_id, 'path' => $path,
                'original_name' => 'planejamento-exemplo.txt', 'size' => Storage::disk('local')->size($path),
            ]);
        }
    }
}
