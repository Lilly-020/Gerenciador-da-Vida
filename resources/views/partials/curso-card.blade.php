@php($meta = $curso->statusMeta())
<div id="curso-card-{{ $curso->id }}" data-board-card data-curso-id="{{ $curso->id }}" data-status="{{ $curso->status }}"
    data-title="{{ $curso->title }}" data-platform="{{ $curso->platform }}" data-link="{{ $curso->link }}" data-objective="{{ $curso->objective }}"
    data-starts-at="{{ $curso->starts_at?->format('Y-m-d') }}" data-due-at="{{ $curso->due_at?->format('Y-m-d') }}"
    data-category="{{ $curso->category }}" data-progress-unit="{{ $curso->progress_unit }}" data-total-lessons="{{ $curso->total_lessons }}" data-completed-lessons="{{ $curso->completed_lessons }}"
    data-duration-hours="{{ $curso->duration_hours }}" data-studied-hours="{{ $curso->studied_hours }}" data-notes="{{ $curso->notes }}" data-rating="{{ $curso->rating }}" data-next-review-at="{{ $curso->next_review_at?->format('Y-m-d') }}"
    data-filter-item data-filter-title="{{ $curso->title }}" data-filter-category="{{ $curso->category }}"
    data-filter-start="{{ ($curso->starts_at ?? $curso->due_at ?? $curso->created_at)?->format('Y-m-d') }}" data-filter-end="{{ ($curso->due_at ?? $curso->starts_at ?? $curso->created_at)?->format('Y-m-d') }}"
    draggable="true" class="course-card min-w-0 rounded-xl border border-white/10 bg-slate-900/80 p-4 shadow-lg shadow-black/20">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1 course-overview">
            <div class="min-w-0">
                <h3 class="wrap-anywhere text-sm font-semibold text-white">{{ $curso->title }}</h3>
                <p class="mt-1 wrap-anywhere text-xs text-slate-400">{{ $curso->platform ?? 'Plataforma não informada' }} @if ($curso->category) · {{ $curso->category }} @endif</p>
                <span class="mt-2 inline-flex items-center gap-1.5 text-[11px] text-slate-300"><span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>
            </div>
            <div class="mt-3 course-progress">
                <div class="flex items-center justify-between gap-2 text-xs text-slate-400"><span>{{ $curso->total_lessons ? $curso->completed_lessons.' de '.$curso->total_lessons.' '.($curso->progress_unit === 'modules' ? 'módulos' : 'aulas') : 'Total não informado' }}</span><strong class="text-indigo-200">{{ $curso->progress }}%</strong></div>
                <div role="progressbar" aria-label="Progresso do curso" aria-valuenow="{{ $curso->progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $curso->progress }}%"></div></div>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-400 course-dates">
                @if ($curso->due_at)<span>Previsão · {{ $curso->due_at->format('d-m-Y') }}</span>@endif
                <span>{{ number_format($curso->studied_hours, 1, ',', '.') }}h estudadas</span>
                @if ($curso->next_review_at)<span class="{{ $curso->next_review_at->lte(today()) ? 'text-amber-300' : 'text-sky-300' }}">Revisar {{ $curso->next_review_at->format('d/m/Y') }}</span>@endif
            </div>
        </div>
        <details class="relative shrink-0">
            <summary aria-label="Ações do curso" class="cursor-pointer list-none rounded-lg px-2 text-lg text-slate-300 hover:bg-white/10">⋯</summary>
            <div class="absolute right-0 z-20 grid w-48 gap-2 rounded-xl border border-white/10 bg-slate-900 p-2 text-xs shadow-xl">
                <button type="button" data-edit-trigger class="rounded-lg px-3 py-2 text-left text-slate-300 hover:bg-white/10">Editar acompanhamento</button>
                <label class="grid gap-1 px-2 text-slate-400">Mover para
                    <select data-status-select class="w-full rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-xs text-white">
                        @foreach (\App\Models\Curso::STATUSES as $key => $statusMeta)<option value="{{ $key }}" @selected($curso->status === $key)>{{ $statusMeta['label'] }}</option>@endforeach
                    </select>
                </label>
                <button type="button" data-delete-trigger class="rounded-lg px-3 py-2 text-left text-rose-300 hover:bg-white/10">Excluir curso</button>
            </div>
        </details>
    </div>
    @if ($curso->link)<a href="{{ $curso->link }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-xs font-medium text-indigo-300 hover:text-indigo-200">Continuar estudando ↗</a>@endif
    <details data-course-details="learning" class="mt-3 min-w-0 wrap-anywhere border-t border-white/10 pt-3">
        <summary class="cursor-pointer text-xs text-indigo-300">Meu aprendizado e detalhes</summary>
        <div class="mt-3 grid min-w-0 grid-cols-1 gap-3 text-xs text-slate-400">
            @if ($curso->starts_at)<p>Início · {{ $curso->starts_at->format('d-m-Y') }}</p>@endif
            @if ($curso->duration_hours)<p>Carga horária: {{ number_format($curso->duration_hours, 1, ',', '.') }}h</p>@endif
            @if ($curso->completed_at)<p class="text-emerald-300">Concluído em {{ $curso->completed_at->format('d/m/Y') }} @if ($curso->status === 'in_review') · Em revisão @endif</p>@endif
            @if ($curso->rating)<p class="text-amber-300" aria-label="Avaliação {{ $curso->rating }} de 5">{{ str_repeat('★', $curso->rating) }}{{ str_repeat('☆', 5 - $curso->rating) }} · {{ $curso->rating }}/5</p>@endif
            @if ($curso->objective)<div><h4 class="mb-1 font-medium text-slate-300">Objetivo</h4><p class="whitespace-pre-wrap wrap-anywhere">{{ $curso->objective }}</p></div>@endif
            <div><h4 class="mb-1 font-medium text-slate-300">Meu aprendizado</h4><p class="whitespace-pre-wrap wrap-anywhere">{{ $curso->notes ?: 'Registre o que aprendeu em cada módulo no acompanhamento do curso.' }}</p></div>
            <button type="button" data-edit-trigger class="justify-self-start text-indigo-300 underline">Registrar progresso e anotações</button>
        </div>
        @foreach (['certificate' => 'Certificados', 'material' => 'Materiais e anexos'] as $kind => $label)
            <section class="mt-4 border-t border-white/10 pt-3" aria-label="{{ $label }}">
                <h4 class="text-xs font-medium text-slate-300">{{ $label }}</h4>
                <ul class="mt-2 grid gap-2">
                    @foreach ($curso->files->where('kind', $kind) as $file)
                        <li class="grid min-w-0 grid-cols-1 gap-2 rounded-lg border border-white/10 bg-slate-950/30 p-2.5 text-xs">
                            <a href="{{ $file->url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 wrap-anywhere text-indigo-200 underline">{{ $file->original_name }}</a>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <button type="button" data-file-kind-url="{{ route('cursos.arquivos.update', [$curso, $file], absolute: false) }}" data-next-kind="{{ $kind === 'material' ? 'certificate' : 'material' }}" class="shrink-0 whitespace-nowrap text-indigo-300">{{ $kind === 'material' ? 'É certificado' : 'É material' }}</button>
                            <button type="button" data-remove-file-url="{{ route('cursos.arquivos.destroy', [$curso, $file], absolute: false) }}" aria-label="Remover anexo" class="shrink-0 whitespace-nowrap text-rose-300">Remover</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs text-indigo-300">+ {{ $kind === 'certificate' ? 'Anexar certificado' : 'Anexar material' }}
                    <input type="file" data-file-input data-file-kind="{{ $kind }}" data-upload-url="{{ route('cursos.arquivos.store', $curso, absolute: false) }}" accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
                </label>
            </section>
        @endforeach
    </details>
</div>
