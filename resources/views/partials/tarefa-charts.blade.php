@php
    $panels = [
        'daily' => ['label' => 'Dia', 'series' => $daily],
        'weekly' => ['label' => 'Semana', 'series' => $weekly],
        'monthly' => ['label' => 'Mês', 'series' => $monthly],
    ];
@endphp

<div id="tarefas-charts" class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-white">Análises</h2>

        <div class="inline-flex items-center gap-1 rounded-full border border-white/10 bg-slate-950/40 p-1">
            @foreach ($panels as $key => $panel)
                <button
                    type="button"
                    data-chart-tab="{{ $key }}"
                    aria-selected="{{ $key === 'daily' ? 'true' : 'false' }}"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $key === 'daily' ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-400 hover:text-white' }}"
                >
                    {{ $panel['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    @foreach ($panels as $key => $panel)
        <div data-chart-panel="{{ $key }}" @class(['hidden' => $key !== 'daily'])>
            @include('partials.tarefa-bar-chart', ['series' => $panel['series']])
        </div>
    @endforeach
</div>
