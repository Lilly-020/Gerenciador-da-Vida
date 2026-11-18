@props(['routeName', 'filter'])

<div class="mb-6">
    <form data-dashboard-period method="GET" action="{{ route($routeName, absolute: false) }}" aria-describedby="financeiro-period-notice" class="grid items-end gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 sm:grid-cols-[1.2fr_1fr_1fr_auto]">
        <label class="grid min-w-0 gap-1.5 text-xs text-slate-400">
            Período
            <select name="period" id="period">
                @foreach (\App\Support\FinanceiroPeriod::PERIODS as $key => $label)
                    <option value="{{ $key }}" @selected(($filter?->preset ?? 'mes_atual') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid min-w-0 gap-1.5 text-xs text-slate-400">
            Data de início
            <input name="start" type="date" value="{{ ($filter?->start ?? today()->startOfMonth())->toDateString() }}" required>
        </label>
        <label class="grid min-w-0 gap-1.5 text-xs text-slate-400">
            Data de fim
            <input name="end" type="date" value="{{ ($filter?->end ?? today()->endOfMonth())->toDateString() }}" required>
        </label>
        <button type="submit" class="rounded-lg bg-indigo-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400">Aplicar filtros</button>
    </form>

    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-400">
        <p id="financeiro-period-notice">{{ $filter ? 'Filtro ativo no Dashboard, em Entradas e em Saídas.' : 'Ao aplicar este filtro, o período será usado no Dashboard, em Entradas e em Saídas.' }}</p>
        @if ($filter)
            <a href="{{ route($routeName, ['period' => 'limpar'], absolute: false) }}" class="font-semibold text-indigo-300 hover:text-white">Limpar filtro</a>
        @endif
    </div>
    @error('start')<p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
    @error('end')<p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
</div>
