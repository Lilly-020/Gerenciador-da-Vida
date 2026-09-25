@php
    $tiles = [
        ['label' => 'Total de sonhos', 'value' => $stats['total']],
        ['label' => 'Em andamento', 'value' => $stats['em_andamento']],
        ['label' => 'Concluídos', 'value' => $stats['concluidos']],
        ['label' => 'Progresso médio', 'value' => $stats['progresso_medio'] . '%'],
    ];
@endphp

<div id="sonhos-stats" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach ($tiles as $tile)
        <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
            <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ $tile['label'] }}</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $tile['value'] }}</p>
        </div>
    @endforeach
</div>
