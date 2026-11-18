@php
    $people = collect([$quadro->user])->merge($quadro->membros->where('status', 'accepted')->pluck('user'))->keyBy('id');
@endphp
<div data-quadro-kanban class="quadro-columns app-scroll" aria-label="Listas do quadro" data-order-url="{{ route('quadros.listas.ordem', $quadro, absolute: false) }}">
    @foreach ($quadro->listas as $list)
        <section data-quadro-list="{{ $list->id }}" data-title="{{ $list->title }}" data-color="{{ $list->color }}" data-url="{{ route('quadros.listas.update', [$quadro, $list], absolute: false) }}" class="quadro-column">
            @if ($canEdit)<div class="quadro-insert-zone"><button type="button" data-quadro-insert-list aria-label="Adicionar coluna à direita de {{ $list->title }}" title="Adicionar coluna aqui" class="quadro-insert-button">+</button></div>@endif
            <header class="quadro-column-header" style="border-top-color:{{ $list->color }}">
                @if ($canEdit)<span draggable="true" data-quadro-list-handle class="cursor-grab select-none px-1 text-slate-500" title="Arrastar lista">⠿</span>@endif
                <h2 class="min-w-0 flex-1 truncate text-xs font-semibold uppercase tracking-wide text-slate-200">{{ $list->title }}</h2><span data-quadro-list-count class="text-xs text-slate-500">{{ $list->cartoes->count() }}</span>
                <button type="button" data-quadro-list-filter class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10" aria-label="Filtrar cartões em {{ $list->title }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h18l-7 8v7l-4 2v-9z" /></svg></button>
                @if ($canEdit)<button type="button" data-quadro-list-config class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10" aria-label="Configurar lista {{ $list->title }}"><x-settings-icon /></button>@endif
            </header>
            <div class="quadro-column-scroll app-scroll">
                @if ($canEdit)
                    <button type="button" data-quadro-new-card data-list="{{ $list->id }}" class="mb-3 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-xs text-slate-400 hover:bg-white/5 hover:text-indigo-200"><span class="text-lg">+</span>Adicionar cartão</button>
                    <form data-quadro-inline-card data-list="{{ $list->id }}" hidden class="mb-3 rounded-xl border border-white/10 bg-slate-900 p-2.5">
                        <label class="sr-only" for="quadro-card-title-{{ $list->id }}">Título do cartão</label>
                        <textarea id="quadro-card-title-{{ $list->id }}" name="title" required maxlength="255" rows="3" placeholder="Insira um título para este cartão…" class="w-full resize-y"></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Adicionar cartão</button>
                            <button type="button" data-quadro-card-cancel class="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Cancelar novo cartão">×</button>
                        </div>
                        <p data-quadro-error hidden role="alert" class="mt-2 text-xs text-rose-300"></p>
                    </form>
                @endif
                <div data-quadro-list-cards class="grid min-h-16 content-start gap-2.5">
                    @foreach ($list->cartoes as $card)
                        <button type="button" data-quadro-card-open data-quadro-card="{{ $card->id }}" data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" data-move-url="{{ route('quadros.cartoes.move', [$quadro, $card], absolute: false) }}" data-title="{{ $card->title }}" data-status="{{ $card->status }}" data-due="{{ $card->due_at?->toIso8601String() }}" data-created="{{ $card->created_at->toIso8601String() }}" data-position="{{ $card->position }}" draggable="{{ $canEdit ? 'true' : 'false' }}" class="quadro-card">
                            @if ($card->labels)<div class="mb-2 flex flex-wrap gap-1">@foreach ($card->labels as $label)<span class="quadro-tag">{{ $label }}</span>@endforeach</div>@endif
                            <h3 class="wrap-break-word text-sm font-medium text-slate-200">{{ $card->title }}</h3>
                            @if ($card->description)<span class="mt-2 block line-clamp-2 text-[11px] leading-relaxed text-slate-500">{{ $card->description }}</span>@endif
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-[10px] text-slate-500">
                                @if ($card->status !== 'undefined')<span class="rounded bg-white/5 px-1.5 py-0.5 {{ $card->status === 'done' ? 'text-emerald-300' : '' }}">{{ \App\Models\QuadroCartao::STATUSES[$card->status] }}</span>@endif
                                @if ($card->comentarios_count)<span>♧ {{ $card->comentarios_count }}</span>@endif
                                @if ($card->due_at)<time class="{{ $card->status !== 'done' && $card->due_at->isPast() ? 'text-rose-300' : '' }}">◷ {{ $card->due_at->format('d/m') }}</time>@endif
                                <span class="ml-auto flex -space-x-1">@foreach ($card->assignee_ids ?? [] as $personId)@if ($people->has($personId))<span class="quadro-avatar h-5! w-5! text-[8px]!" title="{{ $people[$personId]->name }}">{{ mb_strtoupper(mb_substr($people[$personId]->name, 0, 2)) }}</span>@endif @endforeach</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
    @if ($canEdit)
        <form data-quadro-inline-list hidden class="quadro-list-composer">
            <label class="sr-only" for="quadro-new-list-title">Nome da lista</label>
            <input id="quadro-new-list-title" name="title" required maxlength="100" autocomplete="off" placeholder="Insira o nome da lista…" class="app-input w-full">
            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Adicionar lista</button>
                <button type="button" data-quadro-list-cancel class="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Cancelar nova lista">×</button>
            </div>
            <p data-quadro-error hidden role="alert" class="mt-2 text-xs text-rose-300"></p>
        </form>
        <button type="button" data-quadro-new-list class="quadro-add-list">+ Adicionar outra lista</button>
    @endif
</div>
<div data-quadro-table hidden class="h-full overflow-auto p-4 app-scroll">
    <table class="w-full min-w-[800px] border-collapse overflow-hidden rounded-xl bg-slate-950/85 text-left text-xs">
        <thead class="sticky top-0 bg-slate-900 text-slate-400"><tr>@foreach (['Cartão', 'Lista', 'Status', 'Etiquetas', 'Responsáveis', 'Entrega', 'Última resposta'] as $heading)<th class="border-b border-white/10 px-4 py-4 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($quadro->listas as $list)@foreach ($list->cartoes as $card)
            <tr data-quadro-table-row data-title="{{ $card->title }}" data-status="{{ $card->status }}" class="border-b border-white/5 text-slate-300 hover:bg-white/5">
                <td class="px-4 py-4"><button type="button" data-quadro-card-open data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" class="text-left font-semibold hover:text-indigo-200">{{ $card->title }}</button></td><td class="px-4 py-4">{{ $list->title }}</td><td class="px-4 py-4">{{ \App\Models\QuadroCartao::STATUSES[$card->status] }}</td><td class="px-4 py-4"><span class="flex flex-wrap gap-1">@foreach ($card->labels ?? [] as $label)<span class="quadro-tag">{{ $label }}</span>@endforeach</span></td><td class="px-4 py-4">@foreach ($card->assignee_ids ?? [] as $personId)@if ($people->has($personId))<span class="mb-1 block">{{ $people[$personId]->name }}</span>@endif @endforeach</td><td class="whitespace-nowrap px-4 py-4">{{ $card->due_at?->format('d/m/Y H:i') ?? '—' }}</td><td class="px-4 py-4">@if ($last = $card->comentarios->first())<time class="block whitespace-nowrap">{{ $last->created_at->format('d/m/Y H:i') }}</time><span class="mt-1 block text-slate-500">{{ $last->user?->name ?? 'Usuário removido' }}</span>@else — @endif</td>
            </tr>
        @endforeach @endforeach</tbody>
    </table>
</div>
