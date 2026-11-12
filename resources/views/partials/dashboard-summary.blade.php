<div id="dashboard-summary" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([['label' => 'Tarefas de hoje', 'value' => $todayCompleted.' / '.$todayTotal, 'detail' => $todayTotal > 0 ? 'concluídas · uma de cada vez' : 'seu dia está livre por aqui', 'icon' => 'tarefas'], ['label' => 'Sonhos em movimento', 'value' => $sonhoStats['em_andamento'], 'detail' => $sonhoStats['total'].' sonhos para realizar', 'icon' => 'sonhos'], ['label' => 'Projetos em andamento', 'value' => $projetoStats['em_andamento'], 'detail' => $projetoStats['total'].' projetos no seu radar', 'icon' => 'projetos'], ['label' => 'Cursos em andamento', 'value' => $cursoStats['em_andamento'], 'detail' => number_format($cursoStats['horas'], 1, ',', '.').' horas de aprendizado', 'icon' => 'cursos']] as $metric)
        <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2"><span class="text-xs text-slate-400">{{ $metric['label'] }}</span><x-dashboard-icon :name="$metric['icon']" class="h-4 w-4 text-indigo-300/70" /></div>
            <p class="mt-3 text-2xl font-semibold tracking-tight text-white">{{ $metric['value'] }}</p>
            <p class="mt-1 text-[11px] text-slate-500">{{ $metric['detail'] }}</p>
        </div>
    @endforeach
</div>
