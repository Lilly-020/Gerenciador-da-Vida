@php
    $maxTotal = max(1, collect($series)->max('total'));
    $totalCompleted = collect($series)->sum('completed');
    $totalTasks = collect($series)->sum('total');
    $totalPending = $totalTasks - $totalCompleted;
@endphp

<div class="mb-3 flex items-center gap-4 text-[11px] text-slate-400">
    <span class="inline-flex items-center gap-1.5">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
        Concluídas
        <span class="font-semibold text-slate-200">{{ $totalCompleted }}</span>
    </span>
    <span class="inline-flex items-center gap-1.5">
        <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
        Pendentes
        <span class="font-semibold text-slate-200">{{ $totalPending }}</span>
    </span>
</div>

@if ($totalTasks === 0)
    <p class="flex h-40 items-center justify-center text-xs text-slate-500">Sem tarefas no período</p>
@else
    <div class="flex h-40 items-end gap-1.5">
        @foreach ($series as $point)
            @php
                $completedPct = ($point['completed'] / $maxTotal) * 100;
                $pendingPct = (($point['total'] - $point['completed']) / $maxTotal) * 100;
            @endphp
            <div
                class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
                title="{{ $point['sublabel'] ?? $point['label'] }}: {{ $point['completed'] }} de {{ $point['total'] }} concluídas"
            >
                <div class="flex h-full w-full flex-col justify-end overflow-hidden rounded-sm bg-white/5 transition group-hover:bg-white/10">
                    @if ($point['total'] > 0)
                        <div class="w-full bg-slate-500/70" style="height: {{ $pendingPct }}%"></div>
                        <div class="w-full bg-emerald-400/90" style="height: {{ $completedPct }}%"></div>
                    @endif
                </div>
                <span class="truncate text-[10px] {{ $point['isToday'] ? 'font-semibold text-indigo-300' : 'text-slate-500' }}">
                    {{ $point['label'] }}
                </span>
            </div>
        @endforeach
    </div>
@endif
