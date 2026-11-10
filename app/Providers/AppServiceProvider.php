<?php

namespace App\Providers;

use App\Models\TarefaCategoria;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.tarefa-schedule-fields', function (\Illuminate\View\View $view): void {
            $request = request();
            if (! $request->attributes->has('taskCategories')) {
                $request->attributes->set('taskCategories', TarefaCategoria::orderBy('name')->get());
            }
            $view->with('taskCategories', $request->attributes->get('taskCategories'));
        });
        Gate::define('manage-users', fn (User $user): bool => $user->is_admin === true);

        Blade::directive('money', fn (string $expression): string => "<?php echo \\Illuminate\\Support\\Number::currency({$expression}, 'BRL', 'pt_BR'); ?>");
    }
}
