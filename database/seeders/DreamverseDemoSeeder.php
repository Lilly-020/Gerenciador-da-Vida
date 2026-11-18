<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DreamverseDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os sonhos de demonstração só podem ser criados no ambiente local ou de testes.');
        }

        $user = User::where('email', 'lillycardoso02@gmail.com')->firstOrFail();
        $examples = [
            ['Montar minha reserva de emergência', 'finance', ['Definir o valor da reserva', 'Organizar um plano mensal', 'Completar o valor planejado']],
            ['Fazer uma viagem em família', 'family', ['Escolher o destino juntos', 'Planejar o orçamento', 'Reservar a viagem']],
            ['Lançar meu próprio negócio', 'career', ['Validar a ideia', 'Preparar a primeira oferta', 'Atender o primeiro cliente']],
            ['Aprender inglês para conversar', 'studies', ['Escolher uma rotina de estudos', 'Praticar conversação', 'Concluir uma conversa em inglês']],
            ['Conhecer os Lençóis Maranhenses', 'travel', ['Pesquisar a melhor época', 'Organizar o roteiro', 'Preparar as reservas']],
            ['Completar uma corrida de cinco quilômetros', 'health', ['Montar um plano de treino', 'Correr três quilômetros', 'Completar os cinco quilômetros']],
            ['Ler doze livros no ano', 'personal', ['Escolher os primeiros livros', 'Criar um horário de leitura', 'Registrar os livros concluídos']],
            ['Criar um cantinho de leitura', 'other', ['Escolher o espaço', 'Organizar os livros', 'Montar o cantinho']],
            ['Comprar meu primeiro imóvel', 'finance', ['Definir o orçamento', 'Pesquisar opções', 'Planejar a entrada']],
            ['Organizar um encontro especial em família', 'family', ['Escolher uma data', 'Combinar os preparativos', 'Reunir a família']],
            ['Construir um portfólio profissional', 'career', ['Selecionar trabalhos', 'Escrever os estudos de caso', 'Publicar o portfólio']],
            ['Estudar fotografia', 'studies', ['Escolher um curso', 'Praticar com a câmera', 'Criar meu primeiro ensaio']],
            ['Fazer uma viagem de trem', 'travel', ['Pesquisar os trajetos', 'Escolher as paradas', 'Comprar as passagens']],
            ['Ter uma rotina de sono mais tranquila', 'health', ['Definir o horário de descanso', 'Preparar um ritual noturno', 'Manter a rotina por um mês']],
            ['Aprender a tocar violão', 'personal', ['Aprender os primeiros acordes', 'Praticar uma música', 'Tocar a música inteira']],
            ['Cultivar uma pequena horta', 'other', ['Escolher as plantas', 'Preparar os vasos', 'Fazer a primeira colheita']],
            ['Investir todos os meses', 'finance', ['Entender meu perfil', 'Definir um valor mensal', 'Fazer os primeiros aportes']],
            ['Registrar as histórias da minha família', 'family', ['Separar fotografias antigas', 'Conversar com os familiares', 'Organizar um álbum de memórias']],
            ['Apresentar uma palestra', 'career', ['Escolher o tema', 'Preparar a apresentação', 'Compartilhar meu conhecimento']],
            ['Concluir um curso de programação', 'studies', ['Escolher uma trilha', 'Desenvolver um projeto prático', 'Concluir os módulos']],
            ['Ver o pôr do sol em um novo lugar', 'travel', ['Escolher um destino', 'Planejar um fim de semana', 'Fazer a viagem']],
            ['Criar uma rotina de exercícios', 'health', ['Escolher uma atividade', 'Reservar horários na semana', 'Completar um mês de prática']],
            ['Escrever meu primeiro conto', 'personal', ['Criar os personagens', 'Escrever a primeira versão', 'Revisar e finalizar o conto']],
            ['Organizar meu espaço de trabalho', 'other', ['Separar o que não uso', 'Planejar a organização', 'Preparar um espaço confortável']],
            ['Fazer uma pós-graduação', 'studies', ['Pesquisar os programas', 'Planejar os estudos', 'Concluir a matrícula']],
        ];

        DB::transaction(function () use ($user, $examples): void {
            foreach ($examples as $index => [$title, $category, $tasks]) {
                $done = $index % 4;
                $financialGoal = $category === 'finance' ? 3000 : null;
                $dream = $user->sonhos()->firstOrCreate([
                    'title' => $title,
                    'notes' => 'Sonho de demonstração do Dreamverse. Você pode editar ou excluir este exemplo livremente.',
                ], [
                    'category' => $category,
                    'priority' => ['low', 'medium', 'high'][$index % 3],
                    'target_date' => today()->addDays(30 + $index * 12),
                    'financial_goal' => $financialGoal,
                    'saved_amount' => $financialGoal ? $done * 1000 : null,
                ]);
                if ($dream->wasRecentlyCreated) {
                    $dream->tasks()->createMany(array_map(fn (string $task, int $position): array => ['title' => $task, 'completed' => $position < $done], $tasks, array_keys($tasks)));
                }
            }
        });
    }
}
