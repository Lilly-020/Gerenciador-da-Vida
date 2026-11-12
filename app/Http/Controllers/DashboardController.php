<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDashboardRequest;
use App\Models\Curso;
use App\Models\Lancamento;
use App\Models\Projeto;
use App\Models\Sonho;
use App\Support\Carteira;
use App\Support\DashboardLayout;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, TarefaController $tarefas): View|JsonResponse
    {
        $user = $request->user();
        $preferences = $user->dashboard_preferences ?? DashboardLayout::defaults();
        $sonhos = $user->sonhos()->where('archived', false)->with('tasks')->latest()->get();
        $projetos = Projeto::where('archived', false)->with('tasks')->latest()->get();
        $cursos = $user->cursos()->latest()->get();
        $today = Carbon::today();
        $todayTasks = $user->tarefas()->where('date', '<=', $today->copy()->endOfDay())
            ->where(fn (Builder $query): Builder => $query->whereDate('end_date', '>=', $today)
                ->orWhere(fn (Builder $query): Builder => $query->whereNull('end_date')->whereDate('date', $today)))
            ->get();
        $data = [
            'preferences' => $preferences,
            'widgetLabels' => DashboardLayout::WIDGETS,
            'taskViews' => DashboardLayout::TASK_VIEWS,
            'sonhos' => $sonhos,
            'projetos' => $projetos,
            'cursos' => $cursos,
            'sonhoStats' => Sonho::summarize($sonhos),
            'projetoStats' => Projeto::summarize($projetos),
            'cursoStats' => Curso::summarize($cursos),
            'todayTotal' => $todayTasks->count(),
            'todayCompleted' => $todayTasks->where('completed', true)->count(),
            'financeiro' => $this->financeiro($request),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'summary' => view('partials.dashboard-summary', $data)->render(),
                'widgets' => collect(array_keys(DashboardLayout::WIDGETS))->reject(fn (string $id): bool => $id === 'tarefas')
                    ->mapWithKeys(fn (string $id): array => [$id => view('partials.dashboard-'.$id, $data)->render()]),
            ]);
        }

        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'view' => ['nullable', 'in:'.implode(',', array_keys(DashboardLayout::TASK_VIEWS))]]);
        $selected = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : $today;
        $taskView = $request->query('view') ?? $preferences['task_view'];
        $data['taskData'] = $tarefas->calendarData($user, $selected, $taskView, allViews: true);

        return view('pages.dashboard', $data);
    }

    public function update(UpdateDashboardRequest $request): JsonResponse
    {
        $data = $request->validated();
        $preferences = [
            'widgets' => array_map(fn (array $widget): array => [
                'id' => $widget['id'],
                'visible' => (bool) $widget['visible'],
                'width' => $widget['width'],
            ], $data['widgets']),
            'task_view' => $data['task_view'],
        ];
        $request->user()->dashboard_preferences = $preferences;
        $request->user()->save();

        return response()->json(['preferences' => $preferences]);
    }

    /** @return array{owner: string, income: ?float, expense: ?float, to_pay: ?float, to_receive: ?float}|null */
    private function financeiro(Request $request): ?array
    {
        if (! Carteira::canSee('dashboard')) {
            return null;
        }

        $user = $request->user();
        $end = today()->endOfMonth();
        $rows = Carteira::owner()->lancamentos()->with('partes')->whereDate('date', '<=', $end)
            ->whereIn('status', [Lancamento::STATUS_REALIZADO, Lancamento::STATUS_PREVISTO])
            ->where(fn (Builder $query): Builder => $query->where('status', Lancamento::STATUS_PREVISTO)->orWhereDate('date', '>=', today()->startOfMonth()))->get();
        if (Carteira::isOwn()) {
            $shared = Lancamento::sharedWith($user)->with('partes')->whereDate('date', '<=', $end)
                ->whereIn('status', [Lancamento::STATUS_REALIZADO, Lancamento::STATUS_PREVISTO])
                ->where(fn (Builder $query): Builder => $query->where('status', Lancamento::STATUS_PREVISTO)->orWhereDate('date', '>=', today()->startOfMonth()))->get();
            $rows = $rows->concat($shared);
        }
        $realizado = $rows->where('status', Lancamento::STATUS_REALIZADO);
        $previsto = $rows->where('status', Lancamento::STATUS_PREVISTO);

        return [
            'owner' => Carteira::owner()->name,
            'income' => Carteira::canSee('entradas') ? (float) $realizado->sum(fn (Lancamento $row): float => $row->entradaFor($user)) : null,
            'expense' => Carteira::canSee('saidas') ? (float) $realizado->sum(fn (Lancamento $row): float => $row->saidaFor($user)) : null,
            'to_pay' => Carteira::canSee('saidas') ? (float) $previsto->sum(fn (Lancamento $row): float => $row->saidaFor($user)) : null,
            'to_receive' => Carteira::canSee('entradas') ? (float) $previsto->sum(fn (Lancamento $row): float => $row->entradaFor($user)) : null,
        ];
    }
}
