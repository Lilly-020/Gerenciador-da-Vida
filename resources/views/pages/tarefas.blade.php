@php
    $weekdayHeaders = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
@endphp

<x-layout title="Tarefas" align="start">
    <div
        id="tarefas-page"
        data-view="{{ $view }}"
        data-selected-date="{{ $selected->toDateString() }}"
        data-today="{{ \Carbon\Carbon::today()->toDateString() }}"
        class="w-full min-w-0 max-w-6xl"
    >
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Tarefas</h1>
                <p class="mt-1 text-sm text-slate-400">Suas tarefas do dia, e como está o seu progresso.</p>
            </div>

            <div class="flex max-w-full flex-wrap items-center gap-2">
                <div class="inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-full border border-white/10 bg-slate-900/70 p-1 shadow-xl shadow-black/30 backdrop-blur">
                    @foreach (['week' => 'Semana', 'calendar' => 'Minimalista', 'month' => 'Calendário amplo', 'dashboard' => 'Dashboard'] as $key => $label)
                        <a href="{{ route('tarefas', ['date' => $selected->toDateString(), 'view' => $key], absolute: false) }}" data-view-tab="{{ $key }}" aria-current="{{ $view === $key ? 'page' : 'false' }}" aria-selected="{{ $view === $key ? 'true' : 'false' }}" class="shrink-0 whitespace-nowrap rounded-full px-3 py-2 text-xs font-semibold transition sm:px-4 sm:text-sm {{ $view === $key ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>

                <a
                    id="jump-to-today"
                    href="{{ route('tarefas', ['view' => $view], absolute: false) }}"
                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white {{ $selected->isToday() ? 'hidden' : '' }}"
                >
                    Hoje
                </a>
            </div>
        </div>

        <div data-tarefa-busca class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-48 flex-1">
                    <label for="tarefa-busca-titulo" class="sr-only">Buscar tarefa por título</label>
                    <input
                        id="tarefa-busca-titulo"
                        type="search"
                        data-tarefa-busca-titulo
                        placeholder="Buscar tarefa por título, em qualquer dia"
                        autocomplete="off"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>
                <div>
                    <label for="tarefa-busca-de" class="mb-1 block text-xs text-slate-400">Data de</label>
                    <input id="tarefa-busca-de" type="date" data-tarefa-busca-de class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark">
                </div>
                <div>
                    <label for="tarefa-busca-ate" class="mb-1 block text-xs text-slate-400">até</label>
                    <input id="tarefa-busca-ate" type="date" data-tarefa-busca-ate class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark">
                </div>
                <button
                    type="button"
                    data-tarefa-busca-limpar
                    class="hidden rounded-lg border border-white/10 px-3 py-2.5 text-sm text-slate-300 transition hover:text-white"
                >
                    Limpar
                </button>
            </div>
            <div data-tarefa-busca-resultados class="mt-3 empty:hidden"></div>
        </div>

        <div data-view-panel="calendar" @class(['hidden' => $view !== 'calendar'])>
            <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
                <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5 lg:col-span-3">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-white capitalize">
                            {{ Str::lower(\App\Models\Tarefa::monthLabel($month)) }} {{ $month->format('Y') }}
                        </h2>
                        <div class="flex items-center gap-1">
                            <a
                                href="{{ route('tarefas', ['date' => $month->copy()->subMonth()->startOfMonth()->toDateString(), 'view' => 'calendar'], absolute: false) }}"
                                class="rounded-full p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
                                aria-label="Mês anterior"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <a
                                href="{{ route('tarefas', ['date' => $month->copy()->addMonth()->startOfMonth()->toDateString(), 'view' => 'calendar'], absolute: false) }}"
                                class="rounded-full p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
                                aria-label="Próximo mês"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-medium text-slate-500">
                        @foreach ($weekdayHeaders as $weekday)
                            <span>{{ $weekday }}</span>
                        @endforeach
                    </div>

                    <div id="tarefas-calendar" class="mt-1 grid grid-cols-7 gap-1">
                        @foreach ($weeks as $week)
                            @foreach ($week as $day)
                                @php
                                    $isSelected = $day['date']->isSameDay($selected);
                                    $isToday = $day['date']->isToday();
                                    $ratio = $day['total'] > 0 ? $day['completed'] / $day['total'] : null;
                                    $dotClass = match (true) {
                                        $ratio === null => 'bg-transparent',
                                        $ratio >= 1 => 'bg-emerald-400',
                                        $ratio > 0 => 'bg-amber-400',
                                        default => 'bg-slate-500',
                                    };
                                @endphp
                                <button
                                    type="button"
                                    @if ($day['inMonth'])
                                        data-calendar-day="{{ $day['date']->toDateString() }}"
                                    @else
                                        disabled
                                    @endif
                                    class="relative flex min-h-10 aspect-[1.4] flex-col items-center justify-center gap-0.5 rounded-lg text-sm transition
                                        {{ ! $day['inMonth']
                                            ? 'cursor-default text-slate-700'
                                            : ($isSelected
                                                ? 'bg-indigo-500/25 text-indigo-100 ring-1 ring-inset ring-indigo-400/40'
                                                : ($isToday
                                                    ? 'font-semibold text-sky-300 ring-1 ring-inset ring-sky-400/60 hover:bg-white/5'
                                                    : 'text-slate-300 hover:bg-white/5')) }}"
                                >
                                    {{ $day['date']->day }}
                                    @if ($day['inMonth'] && \App\Support\CalendarioFeriados::on($day['date']))<span class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-rose-400" aria-label="Feriado nacional"></span>@endif
                                    <span data-day-dot class="h-1 w-1 rounded-full {{ $dotClass }}"></span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                    <div class="mt-4 border-t border-white/10 pt-3"><p class="text-xs font-semibold text-rose-200">Feriados nacionais neste mês</p><ul class="mt-2 grid gap-2 text-xs text-slate-400">@forelse (array_filter(\App\Support\CalendarioFeriados::year($month->year), fn ($key) => str_starts_with($key, $month->format('Y-m')), ARRAY_FILTER_USE_KEY) as $holidayDate => $holidayName)<li><span class="font-medium text-slate-200">{{ substr($holidayDate, 8, 2) }}/{{ $month->format('m') }}</span> · {{ $holidayName }}</li>@empty<li>Sem feriados nacionais neste mês.</li>@endforelse</ul></div>
                </div>

                <div class="lg:col-span-2">
                    @if ($view === 'calendar')
                        @include('partials.tarefa-day', ['date' => $selected, 'tasks' => $dayTasks])
                    @endif
                </div>
            </div>
        </div>

        <div data-view-panel="week" @class(['hidden' => $view !== 'week'])>
            @if ($view === 'week')
                <div class="mb-6">
                    @include('partials.tarefa-day', ['date' => $selected, 'tasks' => $dayTasks])
                </div>
                @include('partials.tarefa-week')
            @endif
        </div>
        <div data-view-panel="month" @class(['hidden' => $view !== 'month'])>
            @if ($view === 'month')
                @include('partials.tarefa-month')
            @endif
        </div>
        <div data-view-panel="dashboard" @class(['hidden' => $view !== 'dashboard'])>
            @if ($view === 'dashboard')
                @include('partials.tarefa-overview', $overview)
            @endif
            <div class="mb-8">
                @include('partials.tarefa-stats', ['stats' => $stats])
            </div>

            @include('partials.tarefa-charts', ['daily' => $daily, 'weekly' => $weekly, 'monthly' => $monthly])
        </div>
    </div>
    <div id="task-info-popover" popover="manual" role="dialog" aria-labelledby="task-info-title" class="fixed inset-auto m-0 max-h-[75dvh] w-[calc(100%_-_2rem)] max-w-md overflow-y-auto rounded-2xl border border-indigo-300/20 bg-slate-900 p-4 text-slate-200 shadow-2xl shadow-black/40 sm:p-5"></div>
    <dialog id="task-details-modal" aria-labelledby="task-detail-title" class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-2xl border border-white/15 bg-slate-900 p-5 text-slate-200 shadow-2xl backdrop:bg-black/60 backdrop:backdrop-blur-sm sm:p-6"><div data-task-detail-body></div></dialog>
</x-layout>
