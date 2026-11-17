    <div id="project-detail-{{ $projeto->id }}" data-project-detail hidden class="project-detail-overlay" role="dialog" aria-modal="true" aria-labelledby="project-heading-{{ $projeto->id }}" tabindex="-1">
        <div class="project-detail-panel">
            <header class="project-detail-header">
                <div class="min-w-0"><p class="mb-1 text-xs font-medium uppercase tracking-widest text-indigo-300">Projeto</p><h2 id="project-heading-{{ $projeto->id }}" class="wrap-break-word text-xl font-semibold text-white">{{ $projeto->title }}</h2></div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" data-project-mobile-comments class="project-tool lg:hidden" aria-label="Abrir comentários e histórico"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 15a3 3 0 0 1-3 3H8l-5 3V6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3z" /></svg></button>
                    @if ($canManage)<button type="button" data-edit-trigger class="project-tool">Editar</button>@endif
                    <button type="button" data-project-close class="project-tool" aria-label="Fechar projeto">✕</button>
                </div>
            </header>
            <div class="project-detail-body app-scroll">
                <div class="project-detail-main app-scroll" data-project-scroll="main">
                    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Informações do projeto">
                        <button type="button" data-project-labels-open data-url="{{ route('projetos.etiquetas.index', $projeto, absolute: false) }}" class="project-tool"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 3h7l7 7-11 11-7-7V7zM7 7h.01" /></svg>Etiquetas</button>
                        @foreach ([
                            ['participants', 'Responsáveis', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6m-3-3h6'],
                            ['metadata', 'Datas', 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2'],
                            ['tasks', 'Tarefas', 'M9 5H5v16h14V5h-4M9 3h6v4H9zM8 14l3 3 5-6'],
                        ] as [$section, $label, $path])
                            <button type="button" data-project-jump="{{ $section }}" class="project-tool"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}" /></svg>{{ $label }}</button>
                        @endforeach
                        @if ($canEdit)
                            <button type="button" data-project-attach class="project-tool"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 11l-8 8a6 6 0 0 1-8-8l9-9a4 4 0 0 1 6 6l-9 9a2 2 0 0 1-3-3l8-8" /></svg>Anexos</button>
                        @elseif ($projeto->files->isNotEmpty())
                            <button type="button" data-project-jump="files" class="project-tool">Anexos</button>
                        @endif
                    </nav>
                    <section data-project-section="metadata" class="grid gap-5 border-b border-white/10 pb-6 sm:grid-cols-2">
                        <div><h3 class="project-field-label">Status</h3><span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1.5 text-sm {{ $meta['text'] }}"><span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>@if ($projeto->overdue)<span class="ml-2 text-xs text-rose-300">Atrasado</span>@endif @if ($projeto->archived)<span class="ml-2 text-xs text-slate-400">Arquivado</span>@endif</div>
                        <div><h3 class="project-field-label">Prioridade</h3><span class="inline-flex rounded-md border border-indigo-400/20 bg-indigo-400/15 px-3 py-1.5 text-sm text-indigo-200">{{ ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta'][$projeto->priority] }} prioridade</span></div>
                        <div class="sm:col-span-2"><h3 class="project-field-label">Etiquetas</h3><div data-project-label-badges class="flex flex-wrap gap-2">@include('partials.projeto-etiqueta-badges')</div></div>
                        @if ($projeto->starts_at)<div><h3 class="project-field-label">Início</h3><p class="text-sm text-slate-200">{{ $projeto->starts_at->format('d-m-Y') }}</p></div>@endif
                        @if ($projeto->due_at)<div><h3 class="project-field-label">Prazo de entrega</h3><p class="text-sm text-slate-200">{{ $projeto->due_at->format('d-m-Y') }}</p></div>@endif
                        <div class="sm:col-span-2"><div class="mb-2 flex justify-between gap-3"><h3 class="project-field-label mb-0!">Progresso de execução</h3><strong class="text-sm text-indigo-200">{{ $projeto->progress }}%</strong></div><div role="progressbar" aria-label="Progresso do projeto" aria-valuenow="{{ $projeto->progress }}" aria-valuemin="0" aria-valuemax="100" class="h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $projeto->progress }}%"></div></div><p class="mt-2 text-xs text-slate-400">{{ $projeto->tasks->filter(fn ($task) => $task->is_finished)->count() }} de {{ $projeto->tasks->count() }} tarefas concluídas</p></div>
                    </section>
                    <section data-project-section="participants" class="border-b border-white/10 py-5"><h3 class="project-field-label">Responsáveis e participantes</h3>
    <details data-details-key="participants" class="mt-4">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-xs text-indigo-300">
            <span class="flex items-center gap-2">
                <span class="flex -space-x-1.5">
                    <span title="{{ $projeto->user->handle() }} · Administrador" class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-slate-900 bg-indigo-500/30 text-[10px] font-semibold text-indigo-200">{{ mb_strtoupper(mb_substr($projeto->user->name, 0, 2)) }}</span>
                    @foreach ($accepted->take(3) as $participacao)
                        <span title="{{ $participacao->participant->handle() }}" class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-slate-900 bg-slate-700 text-[10px] font-semibold text-slate-200">{{ mb_strtoupper(mb_substr($participacao->participant->name, 0, 2)) }}</span>
                    @endforeach
                </span>
                <span>{{ $projeto->user->name }}<span class="mt-1 block text-slate-400">{{ $accepted->count() + 1 }} participantes</span></span>
            </span>
            <span>{{ $canManage ? 'Gerenciar' : 'Visualizar' }} @if ($pending->isNotEmpty()) · {{ $pending->count() }} pendentes @endif</span>
        </summary>
        @include('partials.projeto-participants')
    </details>
                    </section>
                    @if ($projeto->description || $projeto->details)
                    <section class="border-b border-white/10 py-5">
        @foreach (['description' => 'Objetivo', 'details' => 'Descrição'] as $field => $label)
            @if ($projeto->$field)
                <div class="mt-4"><h3 class="mb-2 text-xs font-medium text-slate-400">{{ $label }}</h3><p class="whitespace-pre-wrap wrap-break-word text-sm text-slate-300">{{ $projeto->$field }}</p></div>
            @endif
        @endforeach
                    </section>
                    @endif
                    @if ($financialTasks->isNotEmpty())
                        <div class="my-5 rounded-xl border border-emerald-400/15 bg-emerald-400/5 p-4 text-sm text-emerald-300"><div class="flex justify-between gap-2"><span>Progresso financeiro</span><strong>{{ $projeto->financial_progress }}%</strong></div><p class="mt-2">R$ {{ number_format($projeto->financial_saved, 2, ',', '.') }} / R$ {{ number_format($financialTasks->sum('target_value'), 2, ',', '.') }}</p><p class="mt-1 text-xs text-slate-400">Acompanhado separadamente da execução.</p></div>
                    @endif
                    @include('partials.projeto-execution')
                    @include('partials.projeto-files')
                    @if ($canManage)
                        <footer class="mt-6 flex flex-wrap gap-3 border-t border-white/10 pt-5">
                            <form method="POST" action="{{ route('projetos.update', $projeto, absolute: false) }}">@csrf @method('PUT')<input type="hidden" name="title" value="{{ $projeto->title }}"><input type="hidden" name="archived" value="{{ $projeto->archived ? 0 : 1 }}"><button class="project-tool">{{ $projeto->archived ? 'Restaurar' : 'Arquivar' }} projeto</button></form>
                            <button type="button" data-delete-card-url="{{ route('projetos.destroy', $projeto, absolute: false) }}" data-confirm-message="Excluir este projeto e todas as suas tarefas? Essa ação não pode ser desfeita." class="project-tool text-rose-300!">Excluir projeto</button>
                        </footer>
                    @endif
                </div>
                <aside class="project-detail-sidebar" aria-label="Conversa e atividades do projeto">
                    @include('partials.projeto-collaboration')
                </aside>
            </div>
        </div>
    </div>
