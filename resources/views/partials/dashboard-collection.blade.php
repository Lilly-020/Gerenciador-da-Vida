<div class="flex items-end justify-between gap-4 border-b border-white/10 pb-4">
    <div><span class="text-3xl font-semibold tracking-tight text-white">{{ $total }}</span><span class="ml-2 text-xs text-slate-400">{{ $totalLabel }}</span></div>
    <a href="{{ route($kind, absolute: false) }}" class="text-xs font-semibold text-indigo-300 hover:text-indigo-100">Ver todos <span aria-hidden="true">↗</span></a>
</div>
<div class="mt-2 divide-y divide-white/5">
    @forelse ($items->take(4) as $item)
        @php
            $meta = $item->statusMeta();
            $deadline = $kind === 'sonhos' ? $item->target_date : $item->due_at;
            $progress = $kind === 'sonhos' && $item->tasks->isEmpty() && $item->financial_goal > 0 ? $item->financial_progress : $item->progress;
            $anchor = match ($kind) { 'sonhos' => 'sonho', 'projetos' => 'projeto', default => 'curso' };
        @endphp
        <a href="{{ route($kind, absolute: false) }}#{{ $anchor }}-card-{{ $item->id }}" class="group block rounded-lg py-4 outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
            <div class="flex items-center justify-between gap-3"><p class="truncate text-sm font-medium text-slate-200 group-hover:text-indigo-200">{{ $item->title }}</p><span class="text-xs text-slate-500">{{ $progress }}%</span></div>
            <div class="mt-2 h-1 overflow-hidden rounded-full bg-white/5" role="progressbar" aria-label="Progresso de {{ $item->title }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $progress }}%"></div></div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>
                @if ($kind === 'cursos' && $item->total_lessons)<span>{{ $item->completed_lessons }} / {{ $item->total_lessons }} {{ $item->progress_unit === 'modules' ? 'módulos' : 'aulas' }}</span>
                @elseif ($kind === 'sonhos' && $item->financial_goal > 0)<span>R$ {{ number_format($item->saved_amount ?? 0, 2, ',', '.') }} de {{ number_format($item->financial_goal, 2, ',', '.') }}</span>
                @elseif ($deadline)<span @class(['text-rose-300' => $deadline->isPast() && $item->status !== 'completed'])>{{ $deadline->format('d/m/Y') }}</span>@endif
            </div>
        </a>
    @empty
        <div class="flex min-h-40 flex-col items-center justify-center gap-2 py-6 text-center"><x-dashboard-icon :name="$kind" class="mb-1 h-8 w-8 text-indigo-300/40" /><p class="text-sm text-slate-300">{{ $emptyLabel }}</p><p class="max-w-64 text-xs leading-relaxed text-slate-500">{{ $emptyDetail }}</p></div>
    @endforelse
</div>
<button type="button" data-dashboard-create="{{ $kind }}" data-dashboard-store-url="{{ route($kind.'.store', absolute: false) }}" class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-indigo-400/25 bg-indigo-500/5 px-4 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/15"><span aria-hidden="true">+</span> {{ $createLabel }}</button>
