@php
    $people = collect([$quadro->user])->merge($quadro->membros->where('status', 'accepted')->pluck('user'))->unique('id');
    $assignees = $people->whereIn('id', $cartao->assignee_ids ?? []);
    $coverFile = collect($cartao->attachments ?? [])->firstWhere('id', $cartao->cover_file_id);
    $checklists = $cartao->checklists ?? ($cartao->checklist ? [['id' => '00000000-0000-4000-8000-'.str_pad($cartao->id, 12, '0', STR_PAD_LEFT), 'title' => 'Checklist', 'items' => $cartao->checklist]] : []);
    $formId = 'quadro-card-edit-'.$cartao->id;
@endphp
<div data-quadro-card-detail data-mention-people="{{ $people->map(fn ($person) => ['id' => $person->id, 'name' => $person->name])->values()->toJson() }}" data-mobile-pane="card" data-url="{{ route('quadros.cartoes.show', [$quadro, $cartao], absolute: false) }}" data-comments-url="{{ route('quadros.comentarios.index', [$quadro, $cartao], absolute: false) }}">
    <nav class="quadro-mobile-card-tabs" aria-label="Seções do cartão">
        <button type="button" data-quadro-mobile-tab="card" aria-selected="true">Cartão</button>
        <button type="button" data-quadro-mobile-tab="comments" aria-selected="false">Comentários</button>
        <button type="button" data-quadro-mobile-tab="history" aria-selected="false">Histórico</button>
        <button type="button" data-quadro-close aria-label="Fechar cartão"><x-card-icon name="close" /></button>
    </nav>
    <div class="quadro-card-detail-grid">
        <div class="quadro-card-main">
            <header class="quadro-card-titlebar">
                @if ($canEdit)<x-checkbox data-quadro-card-complete :checked="$cartao->status === 'done'" aria-label="Concluir cartão" />@else<x-card-icon name="status" />@endif
                <div class="min-w-0 flex-1"><span class="mb-1 block text-[10px] uppercase tracking-wider text-slate-500">{{ $cartao->lista->title }}</span>
                    @if ($canEdit)<input name="title" form="{{ $formId }}" value="{{ $cartao->title }}" required maxlength="255" aria-label="Título do cartão" class="quadro-card-title-input">@else<h2>{{ $cartao->title }}</h2>@endif
                </div>
                <button type="button" data-quadro-close aria-label="Fechar cartão" class="quadro-tool sm:hidden"><x-card-icon name="close" /></button>
            </header>
            <div class="quadro-card-fields app-scroll">
                @if ($coverFile || $cartao->cover_color)<div class="quadro-card-cover" @if ($cartao->cover_color) style="background-color:{{ $cartao->cover_color }}" @endif>@if ($coverFile)<img src="{{ route('quadros.arquivos.show', [$quadro, $cartao, $coverFile['id']], absolute: false) }}?preview=1" alt="Capa do cartão" class="h-full w-full object-cover">@endif</div>@endif
                @if ($canEdit)
                    <form id="{{ $formId }}" data-quadro-card-form data-method="PATCH" action="{{ route('quadros.cartoes.update', [$quadro, $cartao], absolute: false) }}">
                        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Configurar cartão">
                            @foreach (['status' => 'Status', 'people' => 'Responsáveis', 'tag' => 'Etiquetas', 'date' => 'Datas', 'checklist' => 'Checklist', 'image' => 'Capa', 'location' => 'Local'] as $icon => $label)
                                <button type="button" data-quadro-card-panel="{{ $icon }}" class="quadro-tool"><x-card-icon :name="$icon" />{{ $label }}</button>
                            @endforeach
                            <button type="button" data-quadro-card-attach class="quadro-tool"><x-card-icon name="attachment" />Anexo</button>
                        </nav>
                        <div data-quadro-card-editor="status" popover="auto" class="quadro-filter-popover app-scroll"><h3 class="mb-4 font-semibold">Status e lista</h3><label class="grid gap-2 text-xs text-slate-400">Status<select name="status">@foreach (\App\Models\QuadroCartao::STATUSES as $value => $label)<option value="{{ $value }}" @selected($value === $cartao->status)>{{ $label }}</option>@endforeach</select></label><label class="mt-4 grid gap-2 text-xs text-slate-400">Lista<select name="lista_id">@foreach ($quadro->listas as $list)<option value="{{ $list->id }}" @selected($list->id === $cartao->lista_id)>{{ $list->title }}</option>@endforeach</select></label><button type="submit" class="quadro-primary mt-4 w-full">Salvar</button></div>
                        <div data-quadro-card-editor="people" popover="auto" class="quadro-filter-popover app-scroll"><h3 class="mb-4 font-semibold">Responsáveis</h3><div class="grid gap-4">@foreach ($people as $person)<label class="flex items-center gap-3 text-sm"><x-checkbox name="assignee_ids[]" value="{{ $person->id }}" :checked="in_array($person->id, $cartao->assignee_ids ?? [])" /><span class="quadro-avatar">{{ mb_strtoupper(mb_substr($person->name, 0, 2)) }}</span>{{ $person->name }}</label>@endforeach</div><button type="submit" class="quadro-primary mt-4 w-full">Salvar</button></div>
                        <div data-quadro-card-editor="tag" popover="auto" class="quadro-filter-popover quadro-label-popover app-scroll">@include('partials.quadro-card-labels')</div>
                        <div data-quadro-card-editor="checklist" popover="auto" class="quadro-filter-popover app-scroll"><h3 class="mb-4 font-semibold">Criar checklist</h3><label class="grid gap-2 text-xs text-slate-400">Título<input data-quadro-new-checklist-title maxlength="100" value="Checklist" class="w-full"></label><button type="button" data-quadro-checklist-create class="quadro-primary mt-4 w-full">Criar checklist</button></div>
                        <div data-quadro-card-editor="date" popover="auto" class="quadro-filter-popover quadro-dates-popover app-scroll">
                            <header class="mb-5 flex items-center justify-between"><h3 class="flex-1 text-center text-base font-semibold">Datas</h3><button type="button" data-quadro-card-panel-close aria-label="Fechar datas" class="rounded-lg p-1 text-slate-400 hover:bg-white/10"><x-card-icon name="close" /></button></header>
                            <p class="mb-2 text-xs font-medium text-slate-400">Data de início</p>
                            <div class="flex items-center gap-3"><x-checkbox data-quadro-start-enabled :checked="(bool) $cartao->starts_on" aria-label="Definir data de início" /><input data-quadro-start-date type="date" value="{{ $cartao->starts_on?->format('Y-m-d') ?? now()->format('Y-m-d') }}" @disabled(!$cartao->starts_on) aria-label="Data de início" class="min-w-0 flex-1"></div>
                            <p class="mb-2 mt-5 text-xs font-medium text-slate-400">Data de entrega</p>
                            <div class="flex items-center gap-3"><x-checkbox data-quadro-due-enabled :checked="(bool) $cartao->due_at" aria-label="Definir data de entrega" /><div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] gap-2"><input data-quadro-due-date type="date" value="{{ $cartao->due_at?->format('Y-m-d') ?? now()->format('Y-m-d') }}" @disabled(!$cartao->due_at) aria-label="Data de entrega" class="min-w-0 w-full"><input data-quadro-due-time type="time" value="{{ $cartao->due_at?->format('H:i') ?? '12:00' }}" @disabled(!$cartao->due_at) aria-label="Horário de entrega" class="min-w-0 w-full"></div></div>
                            <label class="mt-5 grid gap-2 text-xs font-medium text-slate-400">Definir lembrete<select data-quadro-reminder @disabled(!$cartao->due_at)><option value="">Sem lembrete</option>@foreach (\App\Models\QuadroCartao::REMINDERS as $minutes => $label)<option value="{{ $minutes }}" @selected($cartao->reminder_minutes !== null && $cartao->reminder_minutes === $minutes)>{{ $label }}</option>@endforeach</select></label>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">O lembrete aparece nas notificações e é enviado por e-mail para quem criou o cartão. Horário de Brasília.</p>
                            <p data-quadro-error hidden class="mt-3 text-xs text-rose-300" role="alert"></p>
                            <button type="submit" class="quadro-primary mt-5 w-full">Salvar</button><button type="button" data-quadro-card-dates-clear class="quadro-tool mt-3 w-full">Remover datas e lembrete</button>
                        </div>
                        <div data-quadro-card-editor="image" popover="auto" class="quadro-filter-popover quadro-cover-popover app-scroll">
                            <header class="mb-5 flex items-center justify-between"><h3 class="flex-1 text-center text-base font-semibold">Capa</h3><button type="button" data-quadro-card-panel-close aria-label="Fechar opções de capa" class="rounded-lg p-1 text-slate-400 hover:bg-white/10"><x-card-icon name="close" /></button></header>
                            <h4 class="mb-3 text-xs font-medium text-slate-400">Cores</h4>
                            <div class="grid grid-cols-5 gap-2">
                                @foreach (['#4bce97' => 'Verde', '#f5cd47' => 'Amarelo', '#fea362' => 'Laranja', '#f87168' => 'Vermelho', '#9f8fef' => 'Violeta', '#579dff' => 'Azul', '#6cc3e0' => 'Ciano', '#94c748' => 'Lima', '#e774bb' => 'Rosa', '#8590a2' => 'Cinza'] as $color => $name)
                                    <button type="button" data-quadro-cover-swatch="{{ $color }}" aria-label="Capa {{ $name }}" aria-pressed="{{ !$cartao->cover_file_id && $cartao->cover_color === $color ? 'true' : 'false' }}" class="quadro-cover-swatch" style="background-color:{{ $color }}"><span aria-hidden="true"><x-card-icon name="status" /></span></button>
                                @endforeach
                            </div>
                            <label class="mt-4 flex cursor-pointer items-center gap-3 text-sm text-slate-200"><input type="color" data-quadro-card-cover-color value="{{ $cartao->cover_color ?? '#579dff' }}" class="h-8! w-9! cursor-pointer border-0! bg-transparent! p-0!">Cor personalizada</label>
                            <input type="hidden" name="cover_color" value="{{ $cartao->cover_color }}"><input type="hidden" name="cover_file_id" value="{{ $cartao->cover_file_id }}">
                            <h4 class="mb-3 mt-6 text-xs font-medium text-slate-400">Imagem</h4>
                            <button type="button" data-quadro-card-cover-upload class="quadro-tool w-full justify-start! text-sm!"><x-card-icon name="upload" />Enviar do computador</button>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">JPG, PNG, WEBP ou GIF, até 8 MB.</p>
                            @if (collect($cartao->attachments ?? [])->whereIn('mime', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])->isNotEmpty())
                                <h4 class="mb-3 mt-5 text-xs font-medium text-slate-400">Imagens anexadas</h4><div class="grid grid-cols-3 gap-2">@foreach ($cartao->attachments ?? [] as $file)@if (in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif']))<button type="button" data-quadro-cover-file="{{ $file['id'] }}" aria-label="Usar {{ $file['name'] }} como capa" aria-pressed="{{ $cartao->cover_file_id === $file['id'] ? 'true' : 'false' }}" class="overflow-hidden rounded-lg border-2 border-transparent aria-pressed:border-indigo-300"><img src="{{ route('quadros.arquivos.show', [$quadro, $cartao, $file['id']], absolute: false) }}?preview=1" alt="{{ $file['name'] }}" class="h-16 w-full object-cover" loading="lazy"></button>@endif @endforeach</div>
                            @endif
                            @if ($cartao->cover_color || $cartao->cover_file_id)<button type="button" data-quadro-card-cover-clear class="quadro-tool mt-5 w-full text-rose-300!"><x-card-icon name="trash" />Remover capa</button>@endif
                            <p data-quadro-cover-error hidden class="mt-3 text-xs text-rose-300" role="alert"></p>
                        </div>
                        <div data-quadro-card-editor="location" popover="auto" class="quadro-filter-popover app-scroll"><h3 class="mb-4 font-semibold">Local</h3><label class="grid gap-2 text-xs text-slate-400">Endereço ou local<input name="location" maxlength="500" value="{{ $cartao->location }}" class="w-full"></label><button type="submit" class="quadro-primary mt-4 w-full">Salvar</button></div>
                        @if ($cartao->status === 'done')<p class="mb-6 flex items-center gap-2 rounded-xl bg-emerald-400/10 p-3 text-xs text-emerald-300"><x-card-icon name="status" />Cartão concluído</p>@endif
                        <dl class="quadro-card-metadata">
                            <div><dt>Status</dt><dd><button type="button" data-quadro-card-panel="status" class="quadro-status-pill {{ $cartao->status === 'done' ? 'text-emerald-300' : 'text-indigo-200' }}">{{ \App\Models\QuadroCartao::STATUSES[$cartao->status] }}</button></dd></div>
                            <div><dt>Responsáveis</dt><dd class="flex flex-wrap gap-2">@forelse ($assignees as $person)<span class="inline-flex items-center gap-2"><span class="quadro-avatar" title="{{ $person->name }}">{{ mb_strtoupper(mb_substr($person->name, 0, 2)) }}</span>{{ $person->name }}</span>@empty<span class="text-slate-500">Nenhum responsável</span>@endforelse</dd></div>
                            <div><dt>Etiquetas</dt><dd class="flex flex-wrap gap-2">@forelse ($cartao->labels ?? [] as $label)<span class="rounded-lg px-3 py-1.5 text-xs font-semibold" style="{{ $quadro->labelStyle($label) }}">{{ $label }}</span>@empty<span class="text-slate-500">Nenhuma etiqueta</span>@endforelse</dd></div>
                            @if ($cartao->starts_on)<div><dt>Início</dt><dd>{{ $cartao->starts_on->format('d/m/Y') }}</dd></div>@endif
                            <div>
                                <dt>Entrega</dt><dd>{{ $cartao->due_at?->format('d/m/Y H:i') ?? 'Sem data de entrega' }}</dd>
                                @if ($cartao->reminder_minutes !== null)
                                    <dd class="mt-2 flex items-center gap-2 text-xs text-slate-400"><x-card-icon name="bell" />{{ \App\Models\QuadroCartao::REMINDERS[$cartao->reminder_minutes] }}
                                        @if ($cartao->reminder_notified_at)
                                            <span>· enviado</span>
                                        @endif
                                    </dd>
                                @endif
                            </div>
                            @if ($cartao->location)<div class="col-span-full"><dt>Local</dt><dd class="flex items-center gap-2"><x-card-icon name="location" />{{ $cartao->location }}</dd></div>@endif
                        </dl>
                        <section class="quadro-card-section"><div class="mb-4 flex items-center justify-between"><h3>Observações</h3><button type="button" data-quadro-description-edit class="quadro-tool"><x-card-icon name="pencil" />{{ $cartao->description ? 'Editar' : 'Adicionar' }}</button></div><p data-quadro-description-preview class="whitespace-pre-wrap wrap-break-word text-sm leading-relaxed {{ $cartao->description ? 'text-slate-300' : 'text-slate-500' }}">{{ $cartao->description ?: 'Nenhuma observação.' }}</p><div data-quadro-description-editor hidden><textarea name="description" rows="5" maxlength="20000" class="w-full" aria-label="Observações">{{ $cartao->description }}</textarea><button type="submit" class="quadro-primary mt-3">Salvar observações</button></div></section>
                        <div data-quadro-checklists>@foreach ($checklists as $checklist)<x-quadro-checklist :checklist="$checklist" />@endforeach</div>
                        <p data-quadro-checklist-status role="status" class="mt-3 text-xs text-slate-400" hidden></p>
                        <template data-quadro-checklist-item-template><x-quadro-checklist-item /></template>
                        <template data-quadro-checklist-template><x-quadro-checklist :checklist="['id' => '', 'title' => 'Checklist', 'items' => []]" /></template>
                        <p data-quadro-error hidden class="mt-4 text-sm text-rose-300" role="alert"></p>
                    </form>
                    <input data-quadro-card-file type="file" hidden data-url="{{ route('quadros.arquivos.store', [$quadro, $cartao], absolute: false) }}" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
                @else
                    <dl class="quadro-card-metadata"><div><dt>Status</dt><dd>{{ \App\Models\QuadroCartao::STATUSES[$cartao->status] }}</dd></div><div><dt>Entrega</dt><dd>{{ $cartao->due_at?->format('d/m/Y H:i') ?? 'Sem data' }}</dd></div>@if ($cartao->starts_on)<div><dt>Início</dt><dd>{{ $cartao->starts_on->format('d/m/Y') }}</dd></div>@endif<div><dt>Responsáveis</dt><dd>{{ $assignees->pluck('name')->join(', ') ?: 'Nenhum responsável' }}</dd></div><div><dt>Etiquetas</dt><dd>{{ implode(', ', $cartao->labels ?? []) ?: 'Nenhuma etiqueta' }}</dd></div>@if ($cartao->location)<div><dt>Local</dt><dd>{{ $cartao->location }}</dd></div>@endif</dl><section class="quadro-card-section"><h3 class="mb-4">Observações</h3><p class="whitespace-pre-wrap wrap-break-word text-sm text-slate-300">{{ $cartao->description ?: 'Nenhuma observação.' }}</p></section>
                    @foreach ($checklists as $checklist)<section class="quadro-card-section"><h3 class="mb-4">{{ $checklist['title'] }}</h3><ul class="grid gap-3">@foreach ($checklist['items'] as $item)<li class="flex items-center gap-2 text-sm"><x-checkbox :checked="$item['done']" disabled /><span @class(['line-through text-slate-500' => $item['done']])>{{ $item['text'] }}</span></li>@endforeach</ul></section>@endforeach
                @endif
                <section data-quadro-pinned @if ($cartao->pinnedComentarios->isEmpty()) hidden @endif class="quadro-card-section">@include('partials.quadro-pinned')</section>
                <section data-quadro-files class="quadro-card-section" @if (empty($cartao->attachments) && $cartao->curso?->files->isEmpty() !== false) hidden @endif>@include('partials.quadro-files')</section>
                @if ($cartao->legacy_data)<details class="mt-5 text-xs text-slate-500"><summary class="cursor-pointer">Dados anteriores do curso</summary><dl class="mt-3 grid gap-2">@foreach (['platform' => 'Plataforma', 'completed_lessons' => 'Aulas concluídas', 'total_lessons' => 'Total de aulas', 'studied_hours' => 'Horas estudadas', 'rating' => 'Avaliação'] as $key => $label)@if (isset($cartao->legacy_data[$key]))<div>{{ $label }}: {{ $cartao->legacy_data[$key] }}</div>@endif @endforeach</dl></details>@endif
            </div>
            <footer class="quadro-card-footer">@if ($canEdit)<button type="button" data-quadro-card-delete data-url="{{ route('quadros.cartoes.destroy', [$quadro, $cartao], absolute: false) }}" class="quadro-tool text-rose-300!"><x-card-icon name="trash" />Excluir cartão</button><button type="submit" form="{{ $formId }}" class="quadro-primary">Salvar cartão</button>@endif<button type="button" data-quadro-close class="quadro-tool">Fechar</button></footer>
        </div>
        <aside class="quadro-card-conversation">
            <header class="quadro-conversation-header"><div class="flex min-w-0 gap-4"><button type="button" data-quadro-card-tab="comments" aria-selected="true" class="quadro-conversation-tab">Comentários <span data-quadro-comments-count class="quadro-count">{{ $cartao->comentarios->count() }}</span></button><button type="button" data-quadro-card-tab="history" aria-selected="false" class="quadro-conversation-tab">Histórico</button></div><button type="button" data-quadro-close aria-label="Fechar cartão" class="quadro-tool px-2! text-slate-400"><x-card-icon name="close" class="h-5 w-5" /></button></header>
            <div data-quadro-comments class="app-scroll min-h-0 flex-1 overflow-y-auto p-5" aria-live="polite">@include('partials.quadro-comments')</div>
            <div data-quadro-history hidden class="app-scroll min-h-0 flex-1 overflow-y-auto p-5">@include('partials.quadro-card-history')</div>
            @if ($canEdit)<form data-quadro-comment-create action="{{ route('quadros.comentarios.store', [$quadro, $cartao], absolute: false) }}" class="grid shrink-0 gap-3 border-t border-white/10 p-4"><x-quadro-rich-editor :draft-key="'planner:comment-draft:v1:'.auth()->id().':'.$quadro->id.':'.$cartao->id.':new'" :upload-url="route('quadros.arquivos.store', [$quadro, $cartao], absolute: false)" /><p data-quadro-error hidden class="text-xs text-rose-300" role="alert"></p><button type="submit" class="quadro-primary justify-self-end"><x-card-icon name="send" />Enviar</button></form>@endif
        </aside>
    </div>
</div>
