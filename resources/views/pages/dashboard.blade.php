<x-layout title="Dashboard" align="start">
    <div id="life-dashboard" data-preferences-url="{{ route('dashboard.preferences', absolute: false) }}" data-refresh-url="{{ route('dashboard', absolute: false) }}" class="w-full min-w-0 max-w-6xl">
        <script type="application/json" data-dashboard-preferences>@json($preferences)</script>
        <script type="application/json" data-dashboard-defaults>@json(\App\Support\DashboardLayout::defaults())</script>
        <div class="mb-7 flex flex-wrap items-start justify-between gap-5">
            <div>
                <p class="mb-3 inline-flex items-center gap-2 text-[10px] font-semibold tracking-[0.2em] text-indigo-300"><span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span> SEU ESPAÇO</p>
                <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Sua vida, do seu jeito.</h1>
                <p class="mt-3 text-sm text-slate-400">Olá, {{ explode(' ', trim(auth()->user()->name))[0] }}. Tudo o que importa, em um só lugar.</p>
                <p class="mt-2 text-xs text-slate-500">{{ \App\Models\Tarefa::weekdayLabel(today()) }}, {{ today()->format('d') }} de {{ mb_strtolower(\App\Models\Tarefa::monthLabel(today())) }} de {{ today()->year }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-dashboard-refresh class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/10 bg-slate-900/60 px-3 text-xs font-semibold text-slate-300 hover:bg-white/5" aria-label="Atualizar dashboard"><span aria-hidden="true">↻</span> Atualizar</button>
                <button type="button" data-dashboard-customize aria-expanded="false" aria-controls="dashboard-customization" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-indigo-400/30 bg-indigo-500/15 px-4 text-xs font-semibold text-indigo-100 hover:bg-indigo-500/25"><x-dashboard-icon name="settings" class="h-4 w-4" /> Personalizar</button>
            </div>
        </div>

        @include('partials.dashboard-summary')

        <div class="mb-4 mt-9 flex items-center justify-between gap-3"><h2 class="text-xs font-semibold uppercase tracking-widest text-slate-500">Meu painel</h2><span data-dashboard-save-status role="status" class="text-[11px] text-slate-500">Personalização salva na sua conta</span></div>
        <form id="dashboard-customization" data-dashboard-settings data-dashboard-edit-banner hidden class="sticky top-3 z-20 mb-4 space-y-3 rounded-xl border border-indigo-400/25 bg-slate-900/95 p-4 shadow-xl backdrop-blur">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-indigo-100">Arraste os blocos ou use as setas. Ajuste a largura e o calendário no próprio bloco.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-dashboard-reset class="px-2 py-2 text-xs text-slate-400 hover:text-white">Restaurar padrão</button>
                    <button type="button" data-dashboard-cancel class="rounded-lg border border-white/10 px-4 py-2 text-xs text-slate-300">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-indigo-500 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">Salvar personalização</button>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2" aria-label="Blocos visíveis">
                <span class="mr-1 text-[11px] text-slate-500">Mostrar no painel:</span>
                @foreach ($widgetLabels as $id => $label)
                    <button type="button" data-setting-visibility="{{ $id }}" aria-pressed="true" class="rounded-lg border border-indigo-400/20 px-3 py-2 text-xs text-indigo-200">{{ $label }}</button>
                @endforeach
            </div>
            <p data-dashboard-settings-error role="alert" hidden class="text-xs text-rose-300"></p>
        </form>
        <div data-dashboard-grid class="grid grid-cols-1 items-stretch gap-5 xl:grid-cols-2">
            @foreach ($preferences['widgets'] as $widget)
                @php
                    $id = $widget['id'];
                    $widgetWidth = $id === 'tarefas' && $taskData['view'] !== 'dashboard' ? 'wide' : $widget['width'];
                @endphp
                <section data-dashboard-widget="{{ $id }}" data-width="{{ $widgetWidth }}" @if (! $widget['visible']) hidden @endif @class(['dashboard-widget flex h-[30rem] min-w-0 flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900/55 shadow-lg shadow-black/10', 'xl:col-span-2' => $widgetWidth === 'wide']) aria-labelledby="dashboard-heading-{{ $id }}">
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-white/5 px-4 py-4 sm:px-5">
                        <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl border border-indigo-400/15 bg-indigo-500/10 text-indigo-300"><x-dashboard-icon :name="$id" /></span><div><h2 id="dashboard-heading-{{ $id }}" class="text-sm font-semibold text-white">{{ $widgetLabels[$id] }}</h2><p @if ($id === 'tarefas') data-dashboard-task-view-label @endif class="mt-0.5 text-[10px] text-slate-500">{{ ['tarefas' => $taskViews[$taskData['view']], 'financeiro' => 'Seu mês em números', 'sonhos' => 'Pequenos passos, grandes conquistas', 'projetos' => 'Ideias que viram realidade', 'cursos' => 'Aprender também é avançar'][$id] }}</p></div></div>
                        <div class="flex items-center gap-1">
                            @if ($id === 'tarefas')<button type="button" data-dashboard-new-task class="mr-2 rounded-lg border border-indigo-400/25 bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">+ Nova tarefa</button>@endif
                            <div data-dashboard-edit-controls hidden class="flex flex-wrap items-center gap-1">
                                @if ($id === 'tarefas')
                                    <select data-dashboard-task-view aria-label="Calendário de tarefas na dashboard" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs">
                                        @foreach ($taskViews as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                    </select>
                                @endif
                                <span data-widget-width-control @if ($id === 'tarefas' && $taskData['view'] !== 'dashboard') hidden @endif><select data-widget-width aria-label="Largura de {{ $widgetLabels[$id] }}" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs"><option value="normal">Meia largura</option><option value="wide">Largura inteira</option></select></span>
                                <button type="button" data-widget-drag draggable="true" aria-label="Arrastar {{ $widgetLabels[$id] }}; use as setas ao lado para mover pelo teclado" title="Arrastar bloco" class="cursor-grab rounded-lg px-2 py-2 text-slate-400 hover:bg-white/10 active:cursor-grabbing">⠿</button>
                                <button type="button" data-widget-move="-1" aria-label="Mover {{ $widgetLabels[$id] }} para cima" class="rounded-lg px-2 py-2 text-slate-400 hover:bg-white/10">↑</button>
                                <button type="button" data-widget-move="1" aria-label="Mover {{ $widgetLabels[$id] }} para baixo" class="rounded-lg px-2 py-2 text-slate-400 hover:bg-white/10">↓</button>
                                <button type="button" data-widget-hide aria-label="Ocultar {{ $widgetLabels[$id] }}" class="rounded-lg px-2 py-2 text-slate-400 hover:bg-white/10">×</button>
                            </div>
                        </div>
                    </div>
                    <div data-dashboard-content="{{ $id }}" tabindex="0" role="region" aria-labelledby="dashboard-heading-{{ $id }}" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-5">
                        @if ($id === 'tarefas')
                            @include('partials.tarefas-workspace', [...$taskData, 'dashboardEmbedded' => true])
                        @else
                            @include('partials.dashboard-'.$id)
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
        <div data-dashboard-empty @if (collect($preferences['widgets'])->contains('visible', true)) hidden @endif class="rounded-2xl border border-dashed border-white/15 p-12 text-center"><x-dashboard-icon name="settings" class="mx-auto mb-4 h-8 w-8 text-indigo-300/60" /><p class="font-semibold text-slate-200">Um espaço só seu</p><p class="mb-5 mt-2 text-sm text-slate-400">Escolha os blocos que quer acompanhar na sua dashboard.</p><button type="button" data-dashboard-customize class="rounded-xl bg-indigo-500 px-5 py-3 text-sm font-semibold text-white">Adicionar blocos</button></div>
        <p class="mt-7 text-center text-[11px] text-slate-600">Um passo de cada vez. O seu progresso acontece todos os dias.</p>
    </div>

    <dialog id="dashboard-create" aria-labelledby="dashboard-create-title" class="fixed inset-0 m-auto w-[calc(100%_-_2rem)] max-w-md rounded-2xl border border-white/15 bg-slate-900 p-6 text-slate-200 shadow-2xl backdrop:bg-black/70 backdrop:backdrop-blur-sm">
        <form data-dashboard-create-form>
            <div class="mb-5 flex items-center justify-between"><h2 id="dashboard-create-title" class="text-xl font-semibold text-white"></h2><button type="button" data-dashboard-create-close aria-label="Fechar cadastro" class="rounded-lg px-3 py-2 text-slate-400">✕</button></div>
            <p class="mb-4 text-xs leading-relaxed text-slate-400">Comece pelo título. Depois, você pode completar os detalhes na página correspondente.</p>
            <label for="dashboard-create-name" class="mb-2 block text-xs text-slate-300">Título</label><input id="dashboard-create-name" name="title" required maxlength="255" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3 text-sm text-white outline-none focus:border-indigo-400">
            <p data-dashboard-create-error role="alert" hidden class="mt-3 text-xs text-rose-300"></p>
            <div class="mt-5 flex justify-end gap-2"><button type="button" data-dashboard-create-close class="rounded-lg border border-white/10 px-4 py-2.5 text-xs text-slate-300">Cancelar</button><button type="submit" class="rounded-lg bg-indigo-500 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-50">Criar</button></div>
        </form>
    </dialog>
</x-layout>
