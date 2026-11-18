<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DreamverseLargeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('A demonstração ampliada só pode ser criada no ambiente local ou de testes.');
        }

        $user = User::where('email', 'lillycardoso02@gmail.com')->firstOrFail();
        $themes = [
            'finance' => ['Organizar minhas finanças', 'Planejar uma compra importante', 'Construir independência financeira', 'Investir em um projeto pessoal'],
            'family' => ['Criar uma tradição em família', 'Celebrar uma conquista em família', 'Preparar uma surpresa especial', 'Guardar memórias da família'],
            'career' => ['Ampliar minha rede profissional', 'Desenvolver uma nova habilidade profissional', 'Lançar um projeto autoral', 'Conquistar uma oportunidade profissional'],
            'studies' => ['Aprofundar meus conhecimentos', 'Aprender uma nova tecnologia', 'Concluir uma trilha de estudos', 'Praticar um novo idioma'],
            'travel' => ['Conhecer um novo destino', 'Explorar uma cidade histórica', 'Planejar uma aventura na natureza', 'Descobrir um lugar especial'],
            'health' => ['Cultivar hábitos saudáveis', 'Cuidar do meu bem-estar', 'Praticar uma nova atividade física', 'Reservar tempo para descansar'],
            'personal' => ['Desenvolver um hobby criativo', 'Aprender algo por prazer', 'Realizar uma experiência marcante', 'Dedicar tempo a um projeto pessoal'],
            'other' => ['Transformar uma ideia em realidade', 'Melhorar um espaço da casa', 'Participar de uma iniciativa especial', 'Experimentar uma atividade diferente'],
        ];

        DB::transaction(function () use ($user, $themes): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $counts = $user->sonhos()->get(['category'])->countBy('category')->all();
            $total = $user->sonhos()->count();
            $added = 0;

            while ($total < 200) {
                $category = collect(array_keys($themes))->sortBy(fn (string $key): int => $counts[$key] ?? 0)->first();
                $number = ($counts[$category] ?? 0) + 1;
                $done = $added % 4;
                $goal = $category === 'finance' ? 6000 : null;
                $dream = $user->sonhos()->create([
                    'title' => $themes[$category][($number - 1) % 4].' · '.$number,
                    'notes' => 'Sonho de demonstração ampliada do Dreamverse, criado para explorar um universo com 200 sonhos. Você pode editar ou excluir este exemplo.',
                    'category' => $category,
                    'priority' => ['medium', 'low', 'high'][$added % 3],
                    'target_date' => today()->addDays(20 + $added % 365),
                    'financial_goal' => $goal,
                    'saved_amount' => $goal ? $done * 2000 : null,
                ]);
                $dream->tasks()->createMany(array_map(fn (string $title, int $index): array => ['title' => $title, 'completed' => $index < $done], [
                    'Definir o objetivo e os próximos passos',
                    'Colocar o plano em prática',
                    'Concluir e registrar a conquista',
                ], [0, 1, 2]));
                $counts[$category] = $number;
                $total++;
                $added++;
            }
        });
    }
}
