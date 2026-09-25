<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTarefaRequest;
use App\Http\Requests\UpdateTarefaRequest;
use App\Models\Tarefa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TarefaController extends Controller
{
    /**
     * Display the calendar and the selected day's tasks, with a small
     * today/week/month completion dashboard.
     *
     * Also serves as the fetch endpoint used to switch the selected day:
     * a JSON request returns just the day panel + stats + calendar dot
     * summary for that date, without re-rendering the whole calendar.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $selected = $this->resolveDate($request->query('date'));

        $dayTasks = $user->tarefas()->whereDate('date', $selected)->orderBy('id')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'day' => view('partials.tarefa-day', ['date' => $selected, 'tasks' => $dayTasks])->render(),
                'stats' => view('partials.tarefa-stats', ['stats' => $this->stats($user)])->render(),
                'charts' => $this->chartsPartial($user),
                'date' => $selected->toDateString(),
                'summary' => [
                    'completed' => $dayTasks->where('completed', true)->count(),
                    'total' => $dayTasks->count(),
                ],
            ]);
        }

        $month = $selected->copy()->startOfMonth();
        $monthTasks = $this->monthTasks($user, $month);

        return view('pages.tarefas', [
            'selected' => $selected,
            'month' => $month,
            'weeks' => $this->buildCalendar($month, $monthTasks),
            'dayTasks' => $dayTasks,
            'stats' => $this->stats($user),
            'daily' => $this->dailySeries($user),
            'weekly' => $this->weeklySeries($user),
            'monthly' => $this->monthlySeries($user),
        ]);
    }

    /**
     * Add a new task for a given day.
     */
    public function store(StoreTarefaRequest $request): RedirectResponse|JsonResponse
    {
        $tarefa = $request->user()->tarefas()->create($request->validated());

        return $this->respondWith($request, $tarefa->date);
    }

    /**
     * Update a task's completion state.
     */
    public function update(UpdateTarefaRequest $request, Tarefa $tarefa): RedirectResponse|JsonResponse
    {
        $tarefa->update($request->validated());

        return $this->respondWith($request, $tarefa->date);
    }

    /**
     * Delete a task.
     */
    public function destroy(Request $request, Tarefa $tarefa): RedirectResponse|JsonResponse
    {
        $date = $tarefa->date->copy();
        $tarefa->delete();

        return $this->respondWith($request, $date);
    }

    /**
     * Parse the `date` query parameter, falling back to today for a
     * missing or unparseable value.
     */
    private function resolveDate(?string $value): Carbon
    {
        if (! $value) {
            return Carbon::today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }

    /**
     * @return Collection<string, Collection<int, Tarefa>>
     */
    private function monthTasks(User $user, Carbon $month): Collection
    {
        return $user->tarefas()
            ->whereBetween('date', $this->dateRange($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->get()
            ->groupBy(fn (Tarefa $tarefa): string => $tarefa->date->toDateString());
    }

    /**
     * SQLite stores `date`-cast columns as "Y-m-d 00:00:00", which sorts
     * *after* a bare "Y-m-d" string. A `whereBetween` upper bound of just
     * the end date would therefore silently exclude rows dated exactly on
     * that day. Always pair a bare start-of-day string with an
     * end-of-day string.
     *
     * @return array{0: string, 1: string}
     */
    private function dateRange(Carbon $start, Carbon $end): array
    {
        return [$start->toDateString(), $end->format('Y-m-d 23:59:59')];
    }

    /**
     * Build the calendar as an array of weeks, each with 7 day cells,
     * including the leading/trailing days needed to fill whole weeks.
     *
     * @param  Collection<string, Collection<int, Tarefa>>  $monthTasks
     * @return array<int, array<int, array{date: Carbon, inMonth: bool, total: int, completed: int}>>
     */
    private function buildCalendar(Carbon $month, Collection $monthTasks): array
    {
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dayTasks = $monthTasks->get($cursor->toDateString(), collect());

                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $month->month,
                    'total' => $dayTasks->count(),
                    'completed' => $dayTasks->where('completed', true)->count(),
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * Today/week/month completion stats for the dashboard tiles.
     *
     * @return array{today_completed: int, today_total: int, week_percentage: int, month_percentage: int, month_completed: int}
     */
    private function stats(User $user): array
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $todayTasks = $user->tarefas()->whereDate('date', $today)->get();
        $weekTasks = $user->tarefas()->whereBetween('date', $this->dateRange($weekStart, $weekEnd))->get();
        $monthTasks = $user->tarefas()->whereBetween('date', $this->dateRange($monthStart, $monthEnd))->get();

        $percentage = fn (Collection $tasks): int => $tasks->isEmpty()
            ? 0
            : (int) round($tasks->where('completed', true)->count() / $tasks->count() * 100);

        return [
            'today_completed' => $todayTasks->where('completed', true)->count(),
            'today_total' => $todayTasks->count(),
            'week_percentage' => $percentage($weekTasks),
            'month_percentage' => $percentage($monthTasks),
            'month_completed' => $monthTasks->where('completed', true)->count(),
        ];
    }

    /**
     * Render the day panel + stats partials for a JSON (fetch) request, or
     * fall back to a plain redirect for a normal form submission.
     */
    private function respondWith(Request $request, Carbon $date): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $user = $request->user();
        $dayTasks = $user->tarefas()->whereDate('date', $date)->orderBy('id')->get();

        return response()->json([
            'day' => view('partials.tarefa-day', ['date' => $date, 'tasks' => $dayTasks])->render(),
            'stats' => view('partials.tarefa-stats', ['stats' => $this->stats($user)])->render(),
            'charts' => $this->chartsPartial($user),
            'date' => $date->toDateString(),
            'summary' => [
                'completed' => $dayTasks->where('completed', true)->count(),
                'total' => $dayTasks->count(),
            ],
        ]);
    }

    /**
     * Render the daily/weekly/monthly bar chart dashboard.
     */
    private function chartsPartial(User $user): string
    {
        return view('partials.tarefa-charts', [
            'daily' => $this->dailySeries($user),
            'weekly' => $this->weeklySeries($user),
            'monthly' => $this->monthlySeries($user),
        ])->render();
    }

    /**
     * Completed vs. total tasks per day, for the last N days (including today).
     *
     * @return array<int, array{label: string, sublabel: string, completed: int, total: int, isToday: bool}>
     */
    private function dailySeries(User $user, int $days = 14): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        $tasks = $user->tarefas()
            ->whereBetween('date', $this->dateRange($start, $end))
            ->get()
            ->groupBy(fn (Tarefa $tarefa): string => $tarefa->date->toDateString());

        $series = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dayTasks = $tasks->get($cursor->toDateString(), collect());

            $series[] = [
                'label' => mb_substr(Tarefa::weekdayLabel($cursor), 0, 3),
                'sublabel' => $cursor->format('d/m'),
                'completed' => $dayTasks->where('completed', true)->count(),
                'total' => $dayTasks->count(),
                'isToday' => $cursor->isToday(),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Completed vs. total tasks per week, for the last N weeks (including this one).
     *
     * @return array<int, array{label: string, sublabel: string, completed: int, total: int, isToday: bool}>
     */
    private function weeklySeries(User $user, int $weeks = 8): array
    {
        $thisWeekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $start = $thisWeekStart->copy()->subWeeks($weeks - 1);
        $end = $thisWeekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $tasks = $user->tarefas()->whereBetween('date', $this->dateRange($start, $end))->get();

        $series = [];
        $cursor = $start->copy();

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);

            $weekTasks = $tasks->filter(fn (Tarefa $tarefa): bool => $tarefa->date->between($weekStart, $weekEnd));

            $series[] = [
                'label' => $weekStart->format('d/m'),
                'sublabel' => $weekStart->format('d/m').' a '.$weekEnd->format('d/m'),
                'completed' => $weekTasks->where('completed', true)->count(),
                'total' => $weekTasks->count(),
                'isToday' => $weekStart->eq($thisWeekStart),
            ];

            $cursor->addWeek();
        }

        return $series;
    }

    /**
     * Completed vs. total tasks per month, for the last N months (including this one).
     *
     * @return array<int, array{label: string, sublabel: string, completed: int, total: int, isToday: bool}>
     */
    private function monthlySeries(User $user, int $months = 6): array
    {
        $thisMonthStart = Carbon::today()->startOfMonth();
        $start = $thisMonthStart->copy()->subMonths($months - 1);
        $end = $thisMonthStart->copy()->endOfMonth();

        $tasks = $user->tarefas()->whereBetween('date', $this->dateRange($start, $end))->get();

        $series = [];
        $cursor = $start->copy();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $monthTasks = $tasks->filter(fn (Tarefa $tarefa): bool => $tarefa->date->between($monthStart, $monthEnd));

            $series[] = [
                'label' => mb_substr(Tarefa::monthLabel($cursor), 0, 3),
                'sublabel' => $cursor->format('Y'),
                'completed' => $monthTasks->where('completed', true)->count(),
                'total' => $monthTasks->count(),
                'isToday' => $monthStart->eq($thisMonthStart),
            ];

            $cursor->addMonth();
        }

        return $series;
    }
}
