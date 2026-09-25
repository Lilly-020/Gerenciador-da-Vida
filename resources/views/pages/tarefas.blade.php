@php
    $weekdayHeaders = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
@endphp

<x-layout title="Tarefas" align="start">
    <div
        id="tarefas-page"
        data-selected-date="{{ $selected->toDateString() }}"
        data-today="{{ \Carbon\Carbon::today()->toDateString() }}"
        class="w-full max-w-5xl"
    >
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Tarefas</h1>
                <p class="mt-1 text-sm text-slate-400">Suas tarefas do dia, e como está o seu progresso.</p>
            </div>

            <div class="flex items-center gap-2">
                <div class="inline-flex items-center gap-1 rounded-full border border-white/10 bg-slate-900/70 p-1 shadow-xl shadow-black/30 backdrop-blur">
                    <button
                        type="button"
                        data-view-tab="calendar"
                        aria-selected="true"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition bg-indigo-500/25 text-indigo-100"
                    >
                        Calendário
                    </button>
                    <button
                        type="button"
                        data-view-tab="dashboard"
                        aria-selected="false"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition text-slate-300 hover:text-white"
                    >
                        Dashboard
                    </button>
                </div>

                <a
                    id="jump-to-today"
                    href="{{ route('tarefas', absolute: false) }}"
                    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white {{ $selected->isToday() ? 'hidden' : '' }}"
                >
                    Hoje
                </a>
            </div>
        </div>

        <div data-view-panel="calendar">
            <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
                <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5 lg:col-span-3">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-white capitalize">
                            {{ Str::lower(\App\Models\Tarefa::monthLabel($month)) }} {{ $month->format('Y') }}
                        </h2>
                        <div class="flex items-center gap-1">
                            <a
                                href="{{ route('tarefas', ['date' => $month->copy()->subMonth()->startOfMonth()->toDateString()], absolute: false) }}"
                                class="rounded-full p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white"
                                aria-label="Mês anterior"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <a
                                href="{{ route('tarefas', ['date' => $month->copy()->addMonth()->startOfMonth()->toDateString()], absolute: false) }}"
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
                                    class="relative flex aspect-square flex-col items-center justify-center gap-0.5 rounded-lg text-sm transition
                                        {{ ! $day['inMonth']
                                            ? 'cursor-default text-slate-700'
                                            : ($isSelected
                                                ? 'bg-indigo-500/25 text-indigo-100 ring-1 ring-inset ring-indigo-400/40'
                                                : ($isToday
                                                    ? 'font-semibold text-sky-300 ring-1 ring-inset ring-sky-400/60 hover:bg-white/5'
                                                    : 'text-slate-300 hover:bg-white/5')) }}"
                                >
                                    {{ $day['date']->day }}
                                    <span data-day-dot class="h-1 w-1 rounded-full {{ $dotClass }}"></span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <div class="lg:col-span-2">
                    @include('partials.tarefa-day', ['date' => $selected, 'tasks' => $dayTasks])
                </div>
            </div>
        </div>

        <div data-view-panel="dashboard" class="hidden">
            <div class="mb-8">
                @include('partials.tarefa-stats', ['stats' => $stats])
            </div>

            @include('partials.tarefa-charts', ['daily' => $daily, 'weekly' => $weekly, 'monthly' => $monthly])
        </div>
    </div>
</x-layout>
