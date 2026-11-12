<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTarefaRequest;
use App\Http\Requests\UpdateTarefaRequest;
use App\Models\Tarefa;
use App\Models\TarefaCategoria;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TarefaController extends Controller
{
    private const SEARCH_LIMIT = 50;

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
        $view = in_array($request->query('view'), ['calendar', 'month', 'week', 'dashboard'], true) ? $request->query('view') : 'calendar';

        if ($request->wantsJson()) {
            $dayTasks = $this->tasksBetween($user, $selected, $selected)->with(['category', 'notas:id,tarefa_id,title'])->orderBy('id')->get();

            return response()->json([
                'day' => view('partials.tarefa-day', ['date' => $selected, 'tasks' => $dayTasks])->render(),
                'stats' => view('partials.tarefa-stats', ['stats' => $this->stats($user)])->render(),
                'charts' => $this->chartsPartial($user),
                'week' => $this->weekPartial($user, $selected),
                'calendar' => $this->largeCalendarPartial($user, $selected),
                'overview' => view('partials.tarefa-overview', $this->overview($user))->render(),
                'date' => $selected->toDateString(),
                'summary' => [
                    'completed' => $dayTasks->where('completed', true)->count(),
                    'total' => $dayTasks->count(),
                ],
            ]);
        }

        return view('pages.tarefas', $this->calendarData($user, $selected, $view));
    }

    /** @return array<string, mixed> */
    public function calendarData(User $user, Carbon $selected, string $view, bool $allViews = false): array
    {
        $month = $selected->copy()->startOfMonth();
        $monthTasks = $this->monthTasks($user, $month);

        return [
            'weekDays' => $allViews || $view === 'week' ? $this->weekDays($user, $selected) : [],
            'view' => $view,
            'overview' => $allViews || $view === 'dashboard' ? $this->overview($user) : [],
            'selected' => $selected,
            'month' => $month,
            'weeks' => $this->buildCalendar($month, $monthTasks),
            'dayTasks' => $this->tasksBetween($user, $selected, $selected)->with(['category', 'notas:id,tarefa_id,title'])->orderBy('id')->get(),
            'stats' => $this->stats($user),
            'daily' => $this->dailySeries($user),
            'weekly' => $this->weeklySeries($user),
            'monthly' => $this->monthlySeries($user),
        ];
    }

    /**
     * Search the user's tasks across every day, by title and/or date
     * range. Returns the rendered result list for the page's search bar.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q'));
        $de = $this->parseDate($request->query('de'));
        $ate = $this->parseDate($request->query('ate'));

        if ($termo === '' && ! $de && ! $ate) {
            return response()->json(['html' => '']);
        }

        $tarefas = $request->user()->tarefas()
            ->when($termo !== '', fn ($query) => $query->whereLike('title', '%'.addcslashes($termo, '%_\\').'%'))
            ->when($de, fn ($query) => $query->where('date', '>=', $de->toDateString()))
            ->when($ate, fn ($query) => $query->where('date', '<=', $ate->format('Y-m-d 23:59:59')))
            ->orderByDesc('date')
            ->orderBy('id')
            ->limit(self::SEARCH_LIMIT + 1)
            ->get();

        return response()->json([
            'html' => view('partials.tarefa-busca', [
                'tarefas' => $tarefas->take(self::SEARCH_LIMIT),
                'hasMore' => $tarefas->count() > self::SEARCH_LIMIT,
            ])->render(),
        ]);
    }

    /**
     * Add a new task for a given day — or, when weekdays are chosen, one
     * task on each of those weekdays from that day through `repeat_until`.
     */
    public function store(StoreTarefaRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $date = Carbon::parse($data['date'])->startOfDay();
        $weekdays = array_map(intval(...), $data['weekdays'] ?? []);

        if ($weekdays === []) {
            $task = $request->user()->tarefas()->create(Arr::only($data, ['title', 'date', 'start_time', 'end_time', 'kind', 'category_id', 'end_date', 'notes']));

            return $this->respondWith($request, $date, $task);
        }

        $dates = collect($date->daysUntil(Carbon::parse($data['repeat_until'])->startOfDay()))
            ->filter(fn (Carbon $day): bool => in_array($day->dayOfWeek, $weekdays, true));

        if ($dates->isEmpty()) {
            throw ValidationException::withMessages([
                'weekdays' => 'Nenhum dos dias escolhidos cai dentro desse período.',
            ]);
        }

        $seriesId = (string) Str::uuid();

        $createdTasks = $dates->map(fn (Carbon $day) => $request->user()->tarefas()->create([
            'title' => $data['title'],
            'date' => $day->toDateString(),
            'series_id' => $seriesId,
            ...Arr::only($data, ['start_time', 'end_time', 'kind', 'category_id', 'notes']),
            'end_date' => ! empty($data['end_date']) ? $day->copy()->addDays((int) $date->diffInDays(Carbon::parse($data['end_date'])))->toDateString() : null,
        ]));

        return $this->respondWith($request, $date, $createdTasks->first());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Informe o nome da categoria.']);
        }
        $category = TarefaCategoria::firstOrCreate($data);

        return response()->json(['id' => $category->id, 'name' => $category->name]);
    }

    public function show(Tarefa $tarefa): JsonResponse
    {
        $tarefa->load(['files', 'category', 'notas.items']);

        return response()->json([
            'html' => view('partials.tarefa-details', ['task' => $tarefa])->render(),
            'summary' => view('partials.tarefa-info', ['task' => $tarefa])->render(),
            'note_section' => view('partials.tarefa-notas', ['task' => $tarefa])->render(),
        ]);
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
     * Delete a task. With `?scope=series`, also deletes the later tasks of
     * the same repetition (earlier ones are history and stay).
     */
    public function destroy(Request $request, Tarefa $tarefa): RedirectResponse|JsonResponse
    {
        $date = $tarefa->date->copy();

        if ($request->query('scope') === 'series' && $tarefa->series_id) {
            $tasks = $request->user()->tarefas()->with('files')->where('series_id', $tarefa->series_id)
                ->where('date', '>=', $date->toDateString())->get();
            Storage::disk('local')->delete($tasks->flatMap(fn (Tarefa $task): Collection => $task->files->pluck('path'))->all());
            $tasks->each->delete();
        } else {
            Storage::disk('local')->delete($tarefa->files->pluck('path')->all());
            $tarefa->delete();
        }

        return $this->respondWith($request, $date);
    }

    /**
     * Parse the `date` query parameter, falling back to today for a
     * missing or unparseable value.
     */
    private function resolveDate(?string $value): Carbon
    {
        return $this->parseDate($value) ?? Carbon::today();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<string, Collection<int, Tarefa>>
     */
    private function monthTasks(User $user, Carbon $month): Collection
    {
        return $this->groupTasksByDay($user, $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY), $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY));
    }

    /** @return Builder<Tarefa> */
    private function tasksBetween(User $user, Carbon $start, Carbon $end): Builder
    {
        return Tarefa::where('user_id', $user->id)->where('date', '<=', $end->format('Y-m-d 23:59:59'))
            ->where(fn (Builder $query): Builder => $query->whereDate('end_date', '>=', $start->toDateString())
                ->orWhere(fn (Builder $query): Builder => $query->whereNull('end_date')->where('date', '>=', $start->toDateString())));
    }

    /** @return Collection<string, Collection<int, Tarefa>> */
    private function groupTasksByDay(User $user, Carbon $start, Carbon $end): Collection
    {
        $days = collect();
        foreach ($this->tasksBetween($user, $start, $end)->with('notas:id,tarefa_id,title')->orderBy('start_time')->orderBy('id')->get() as $task) {
            $cursor = $task->date->max($start)->copy()->startOfDay();
            $last = ($task->end_date ?? $task->date)->min($end)->copy()->startOfDay();
            while ($cursor->lte($last)) {
                $key = $cursor->toDateString();
                if (! $days->has($key)) {
                    $days->put($key, collect());
                }
                $days->get($key)->push($task);
                $cursor->addDay();
            }
        }

        return $days;
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
     * @return array<int, array<int, array{date: Carbon, inMonth: bool, total: int, completed: int, tasks: Collection<int, Tarefa>}>>
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
                    'tasks' => $dayTasks,
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
    private function respondWith(Request $request, Carbon $date, ?Tarefa $created = null): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $user = $request->user();
        $dayTasks = $this->tasksBetween($user, $date, $date)->with(['category', 'notas:id,tarefa_id,title'])->orderBy('id')->get();

        return response()->json([
            'day' => view('partials.tarefa-day', ['date' => $date, 'tasks' => $dayTasks])->render(),
            'stats' => view('partials.tarefa-stats', ['stats' => $this->stats($user)])->render(),
            'charts' => $this->chartsPartial($user),
            'week' => $this->weekPartial($user, $date),
            'calendar' => $this->largeCalendarPartial($user, $date),
            'overview' => view('partials.tarefa-overview', $this->overview($user))->render(),
            'created_task_url' => $created ? route('tarefas.show', $created, absolute: false) : null,
            'date' => $date->toDateString(),
            'summary' => [
                'completed' => $dayTasks->where('completed', true)->count(),
                'total' => $dayTasks->count(),
            ],
        ]);
    }

    private function weekPartial(User $user, Carbon $selected): string
    {
        return view('partials.tarefa-week', ['selected' => $selected, 'weekDays' => $this->weekDays($user, $selected)])->render();
    }

    /** @return array<int, array{date: Carbon, allDay: Collection<int, Tarefa>, events: array<int, array{task: Tarefa, start: int, end: int, lane: int, lanes: int}>}> */
    private function weekDays(User $user, Carbon $selected): array
    {
        $start = $selected->copy()->startOfWeek(Carbon::MONDAY);
        $tasks = $this->groupTasksByDay($user, $start, $start->copy()->addDays(6));
        $days = [];
        for ($index = 0; $index < 7; $index++) {
            $date = $start->copy()->addDays($index);
            $dayTasks = $tasks->get($date->toDateString(), collect());
            $timed = $dayTasks->filter(fn (Tarefa $task): bool => $task->start_time !== null)->values();
            $groups = [];
            $group = [];
            $groupEnd = 0;
            foreach ($timed as $task) {
                $parts = explode(':', $task->start_time);
                $minutes = $task->date->isSameDay($date) ? (int) $parts[0] * 60 + (int) $parts[1] : 0;
                $endParts = $task->end_time ? explode(':', $task->end_time) : null;
                $end = $endParts ? (int) $endParts[0] * 60 + (int) $endParts[1] : min(1440, $minutes + 60);
                if (($task->end_date ?? $task->date)->gt($date)) {
                    $end = 1440;
                }
                $end = max($end, min(1440, $minutes + 24));
                if ($group !== [] && $minutes >= $groupEnd) {
                    $groups[] = $group;
                    $group = [];
                    $groupEnd = 0;
                }
                $group[] = ['task' => $task, 'start' => $minutes, 'end' => $end];
                $groupEnd = max($groupEnd, $end);
            }
            if ($group !== []) {
                $groups[] = $group;
            }
            $events = [];
            foreach ($groups as $overlap) {
                $lanes = [];
                foreach ($overlap as &$event) {
                    $lane = 0;
                    while (isset($lanes[$lane]) && $lanes[$lane] > $event['start']) {
                        $lane++;
                    }
                    $event['lane'] = $lane;
                    $lanes[$lane] = $event['end'];
                }
                unset($event);
                foreach ($overlap as $event) {
                    $events[] = [...$event, 'lanes' => count($lanes)];
                }
            }
            $days[] = ['date' => $date, 'allDay' => $dayTasks->whereNull('start_time'), 'events' => $events];
        }

        return $days;
    }

    private function largeCalendarPartial(User $user, Carbon $selected): string
    {
        $month = $selected->copy()->startOfMonth();

        return view('partials.tarefa-month', ['month' => $month, 'selected' => $selected,
            'weeks' => $this->buildCalendar($month, $this->monthTasks($user, $month))])->render();
    }

    /** @return array{todayTasks: Collection<int, Tarefa>, overdue: Collection<int, Tarefa>, upcoming: Collection<int, Tarefa>, overdueCount: int, upcomingCount: int} */
    private function overview(User $user): array
    {
        $today = today();
        $overdue = $user->tarefas()->where('completed', false)->whereDate('date', '<', $today);
        $upcoming = $user->tarefas()->where('completed', false)->whereBetween('date', $this->dateRange($today->copy()->addDay(), $today->copy()->addDays(7)));

        return [
            'todayTasks' => $user->tarefas()->whereDate('date', $today)->orderBy('completed')->orderBy('id')->get(),
            'overdueCount' => (clone $overdue)->count(),
            'upcomingCount' => (clone $upcoming)->count(),
            'overdue' => $overdue->orderBy('date')->orderBy('id')->limit(6)->get(),
            'upcoming' => $upcoming->orderBy('date')->orderBy('id')->limit(8)->get(),
        ];
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
