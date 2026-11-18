@php
    $people = collect([$quadro->user])->merge($quadro->membros->where('status', 'accepted')->pluck('user'))->keyBy('id');
@endphp
<div data-quadro-kanban class="quadro-columns app-scroll" aria-label="Listas do quadro" data-order-url="{{ route('quadros.listas.ordem', $quadro, absolute: false) }}">
    @foreach ($quadro->listas as $list)
        <section data-quadro-list="{{ $list->id }}" data-title="{{ $list->title }}" data-color="{{ $list->color }}" data-url="{{ route('quadros.listas.update', [$quadro, $list], absolute: false) }}" class="quadro-column" style="--quadro-list-color:{{ $list->color }};--quadro-list-text:{{ $list->foregroundColor() }}">
            @if ($canEdit)<div class="quadro-insert-zone"><button type="button" data-quadro-insert-list aria-label="Adicionar coluna após {{ $list->title }}" title="Adicionar coluna aqui" class="quadro-insert-button">+</button></div>@endif
            <header class="quadro-column-header" style="border-top-color:{{ $list->color }}">
                @if ($canEdit)<button type="button" draggable="true" data-quadro-list-handle class="quadro-drag-handle" aria-label="Arrastar lista {{ $list->title }}" title="Arrastar lista"><x-card-icon name="grip" /></button>@endif
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
                        <article data-quadro-card="{{ $card->id }}" data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" data-move-url="{{ route('quadros.cartoes.move', [$quadro, $card], absolute: false) }}" data-title="{{ $card->title }}" data-status="{{ $card->status }}" data-due="{{ $card->due_at?->toIso8601String() }}" data-created="{{ $card->created_at->toIso8601String() }}" data-position="{{ $card->position }}" draggable="{{ $canEdit ? 'true' : 'false' }}" data-list="{{ $list->id }}" data-copy-url="{{ route('quadros.cartoes.copy', [$quadro, $card], absolute: false) }}" data-delete-url="{{ route('quadros.cartoes.destroy', [$quadro, $card], absolute: false) }}" class="quadro-card quadro-card-tile">
                            @php($cardCover = collect($card->attachments ?? [])->firstWhere('id', $card->cover_file_id))
                            @if ($cardCover || $card->cover_color)
                                <button type="button" data-quadro-card-cover="{{ $cardCover ? 'image' : 'color' }}" data-quadro-card-open data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" aria-label="Abrir cartão {{ $card->title }}" class="quadro-tile-cover quadro-tile-cover--{{ $cardCover ? 'image' : 'color' }}" @if ($card->cover_color) style="background-color:{{ $card->cover_color }}" @endif>@if ($cardCover)<img src="{{ route('quadros.arquivos.show', [$quadro, $card, $cardCover['id']], absolute: false) }}?preview=1" alt="Capa de {{ $card->title }}" class="h-full w-full object-cover" loading="lazy">@endif</button>
                            @endif
                            <div data-quadro-card-open data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" class="quadro-card-open">
                            @if ($card->labels)<div class="quadro-tile-labels">@foreach ($card->labels as $label)<span class="quadro-tile-label" style="{{ $quadro->labelStyle($label) }}">{{ $label }}</span>@endforeach</div>@endif
                            <div class="quadro-tile-heading">
                                <button type="button" data-quadro-tile-complete aria-pressed="{{ $card->status === 'done' ? 'true' : 'false' }}" aria-label="{{ $card->status === 'done' ? 'Reabrir cartão' : 'Concluir cartão' }}" title="{{ \App\Models\QuadroCartao::STATUSES[$card->status] }}" @disabled(!$canEdit) class="quadro-tile-complete"><svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
                                <h3 class="min-w-0 flex-1"><button type="button" data-quadro-card-open data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" class="quadro-tile-title">{{ $card->title }}</button></h3>
                            </div>
                            <div class="quadro-tile-footer">
                                @if ($card->due_at)<time class="quadro-tile-date {{ $card->status === 'done' ? 'is-complete' : ($card->due_at->isPast() ? 'is-overdue' : '') }}" datetime="{{ $card->due_at->toIso8601String() }}" title="Entrega: {{ $card->due_at->format('d/m/Y H:i') }}"><x-card-icon name="date" />{{ $card->due_at->format('d/m') }}@if ($card->reminder_minutes !== null)<span title="Lembrete: {{ \App\Models\QuadroCartao::REMINDERS[$card->reminder_minutes] }}"><x-card-icon name="bell" /></span>@endif</time>@endif
                                @if ($card->comentarios_count)<span class="quadro-tile-indicator" title="Comentários"><x-card-icon name="comment" />{{ $card->comentarios_count }}</span>@endif
                                @php($items = collect($card->checklists ?? [])->flatMap(fn ($checklist) => $checklist['items']))
                                @if ($items->isNotEmpty())<span class="quadro-tile-indicator" title="Itens do checklist"><x-card-icon name="checklist" />{{ $items->where('done', true)->count() }}/{{ $items->count() }}</span>@endif
                                @if ($card->attachments)<span class="quadro-tile-indicator" title="Anexos"><x-card-icon name="attachment" />{{ count($card->attachments) }}</span>@endif
                                <span class="quadro-tile-assignees">@foreach ($card->assignee_ids ?? [] as $personId)@if ($people->has($personId))<span class="quadro-avatar" title="{{ $people[$personId]->name }}" aria-label="{{ $people[$personId]->name }}">{{ mb_strtoupper(mb_substr($people[$personId]->name, 0, 2)) }}</span>@endif @endforeach</span>
                            </div>
                        </div>
                            @if ($canEdit)<button type="button" data-quadro-card-handle class="quadro-drag-handle quadro-card-drag-handle" aria-label="Arrastar cartão {{ $card->title }}" title="Arrastar cartão"><x-card-icon name="grip" /></button><button type="button" data-quadro-card-menu class="quadro-card-pencil" aria-label="Ações do cartão {{ $card->title }}" title="Ações do cartão"><x-card-icon name="pencil" /></button>@endif
                        </article>
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
        <thead class="sticky top-0 bg-slate-900 text-slate-400"><tr>
            @foreach (['title' => 'Cartão', 'list' => 'Lista', 'status' => 'Status', 'labels' => 'Etiquetas', 'assignees' => 'Responsáveis', 'due' => 'Entrega', 'response' => 'Última resposta'] as $key => $heading)
                <th aria-sort="none" class="border-b border-white/10 font-medium"><button type="button" data-quadro-table-sort="{{ $key }}" class="quadro-table-sort" aria-label="Ordenar por {{ $heading }}"><span>{{ $heading }}</span><svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path data-quadro-sort-up d="m5 8 5-5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path data-quadro-sort-down d="m5 12 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></button></th>
            @endforeach
        </tr></thead>
        <tbody>@foreach ($quadro->listas as $list)@foreach ($list->cartoes as $card)
            @php($lastResponse = $card->comentarios->first())
            <tr data-quadro-table-row data-quadro-row-id="{{ $card->id }}" data-sort-title="{{ $card->title }}" data-sort-list="{{ $list->title }}" data-sort-status="{{ array_search($card->status, array_keys(\App\Models\QuadroCartao::STATUSES), true) }}" data-sort-labels="{{ implode(', ', $card->labels ?? []) }}" data-sort-assignees="{{ $people->whereIn('id', $card->assignee_ids ?? [])->pluck('name')->sort()->join(', ') }}" data-sort-due="{{ $card->due_at?->getTimestamp() }}" data-sort-response="{{ $lastResponse?->created_at?->getTimestamp() }}" data-title="{{ $card->title }}" data-status="{{ $card->status }}" class="border-b border-white/5 text-slate-300 hover:bg-white/5">
                <td class="px-4 py-4"><button type="button" data-quadro-card-open data-url="{{ route('quadros.cartoes.show', [$quadro, $card], absolute: false) }}" class="text-left font-semibold hover:text-indigo-200">{{ $card->title }}</button></td><td class="px-4 py-4">{{ $list->title }}</td><td class="px-4 py-4">{{ \App\Models\QuadroCartao::STATUSES[$card->status] }}</td><td class="px-4 py-4"><span class="flex flex-wrap gap-1">@foreach ($card->labels ?? [] as $label)<span class="quadro-tag" style="{{ $quadro->labelStyle($label) }}">{{ $label }}</span>@endforeach</span></td><td class="px-4 py-4">@foreach ($card->assignee_ids ?? [] as $personId)@if ($people->has($personId))<span class="mb-1 block">{{ $people[$personId]->name }}</span>@endif @endforeach</td><td class="whitespace-nowrap px-4 py-4">{{ $card->due_at?->format('d/m/Y H:i') ?? '—' }}</td><td class="px-4 py-4">@if ($last = $card->comentarios->first())<time class="block whitespace-nowrap">{{ $last->created_at->format('d/m/Y H:i') }}</time><span class="mt-1 block text-slate-500">{{ $last->user?->name ?? 'Usuário removido' }}</span>@else — @endif</td>
            </tr>
        @endforeach @endforeach</tbody>
    </table>
</div>
