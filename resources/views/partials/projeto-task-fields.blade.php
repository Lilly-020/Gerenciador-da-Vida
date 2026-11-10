@php($fieldClass = 'w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-400/30')
<div class="grid gap-3 sm:grid-cols-2">
    <label class="grid gap-1 text-xs text-slate-400 sm:col-span-2">Nome da tarefa
        <input name="title" value="{{ $task?->title }}" data-add-task-input required maxlength="255" class="{{ $fieldClass }}">
    </label>
    <label class="grid gap-1 text-xs text-slate-400">Responsável
        <select name="assignee_id" class="{{ $fieldClass }}">
            <option value="">Sem responsável</option>
            <option value="{{ $projeto->user_id }}" @selected($task?->assignee_id === $projeto->user_id)>{{ $projeto->user->handle() }}</option>
            @foreach ($projeto->participacoes->where('status', 'aceito') as $participacao)
                <option value="{{ $participacao->participant_id }}" @selected($task?->assignee_id === $participacao->participant_id)>{{ $participacao->participant->handle() }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-xs text-slate-400">Prazo da tarefa
        <input type="date" name="due_at" value="{{ $task?->due_at?->format('Y-m-d') }}" class="{{ $fieldClass }} scheme-dark">
    </label>
    @unless ($task)
        <label class="grid gap-1 text-xs text-slate-400 sm:col-span-2">Subtarefa de (opcional)
            <select name="parent_id" class="{{ $fieldClass }}">
                <option value="">Tarefa independente</option>
                @foreach ($projeto->tasks->whereNull('parent_id') as $parent)
                    <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                @endforeach
            </select>
        </label>
    @endunless
    <label class="grid gap-1 text-xs text-slate-400">Tipo de acompanhamento
        <select name="goal_type" class="{{ $fieldClass }}">
            @foreach (['checklist' => 'Checklist', 'numeric' => 'Meta numérica', 'financial' => 'Meta financeira'] as $value => $label)
                <option value="{{ $value }}" @selected(($task?->goal_type ?? 'checklist') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-xs text-slate-400">Unidade (ex.: páginas, km)
        <input name="unit" value="{{ $task?->unit }}" maxlength="30" class="{{ $fieldClass }}">
    </label>
    <label class="grid gap-1 text-xs text-slate-400">Meta (para metas numéricas ou financeiras)
        <input type="number" name="target_value" value="{{ $task?->target_value }}" min="0.01" max="999999999999.99" step="0.01" class="{{ $fieldClass }}">
    </label>
    <label class="grid gap-1 text-xs text-slate-400">Valor atual
        <input type="number" name="current_value" value="{{ $task?->current_value ?? 0 }}" min="0" max="999999999999.99" step="0.01" required class="{{ $fieldClass }}">
    </label>
</div>
