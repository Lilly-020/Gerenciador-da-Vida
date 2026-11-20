<?php

namespace Database\Seeders;

use App\Models\Amizade;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\SocialAudience;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SocialDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::getDriverName() !== 'sqlite' || config('social.communities') !== null) {
            throw new RuntimeException('Os exemplos do Social só podem ser criados no SQLite local de uma comunidade única.');
        }

        $viewer = User::where('name', 'Lilly Cardoso')->sole();
        $examples = [
            'ana' => [
                'Ana Souza',
                'Reservei meia hora para ler de manhã. Fazia tempo que eu não começava o dia com tanta calma.',
                'Terminei de organizar meu cantinho de leitura. Agora só falta escolher o próximo livro. Alguém tem uma indicação?',
            ],
            'lucas' => [
                'Lucas Ferreira',
                'Consegui fazer a primeira caminhada da semana antes do trabalho. Vou tentar repetir na quinta.',
                'Separei as fotos favoritas da trilha de domingo e montei meu primeiro álbum do ano.',
            ],
            'rafael' => [
                'Rafael Silva',
                'Hoje experimentei planejar a semana com só três prioridades. A lista ficou bem mais fácil de seguir.',
                'Toquei minha primeira música inteira no violão hoje. Ainda saíram alguns acordes tortos, mas fui até o fim.',
            ],
        ];

        DB::transaction(function () use ($viewer, $examples): void {
            foreach ($examples as $handle => [$name, $firstPost, $secondPost]) {
                $author = User::firstOrCreate(['email' => 'social-demo-'.$handle.'@example.invalid'], [
                    'name' => $name.' (exemplo)',
                    'password' => Str::random(48),
                ]);
                Amizade::firstOrCreate(SocialAudience::pair($viewer->id, $author->id), [
                    'requester_id' => $author->id,
                    'status' => 'accepted',
                ]);
                foreach ([$firstPost, $secondPost] as $content) {
                    SocialPost::withTrashed()->firstOrCreate([
                        'user_id' => $author->id,
                        'content' => $content,
                    ], ['visibility' => 'friends']);
                }
            }
        });
    }
}
