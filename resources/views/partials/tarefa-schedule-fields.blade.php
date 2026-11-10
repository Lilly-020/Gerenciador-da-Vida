@php($scheduleInput = 'w-full min-w-0 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20')
<div class="grid gap-3 sm:grid-cols-2">
    <label class="grid gap-1.5 text-xs text-slate-300">Horário de início<input name="start_time" type="time" step="60" class="{{ $scheduleInput }} scheme-dark" value="{{ $task?->start_time ? substr($task->start_time, 0, 5) : '' }}"></label>
    <label class="grid gap-1.5 text-xs text-slate-300">Horário de fim<input name="end_time" type="time" step="60" class="{{ $scheduleInput }} scheme-dark" value="{{ $task?->end_time ? substr($task->end_time, 0, 5) : '' }}"></label>
</div>
<p class="mt-2 text-[11px] text-slate-500">Horários opcionais. Sem horário, a tarefa aparece em Dia todo.</p>
<div data-task-category-picker class="mt-4 grid gap-2">
    <label class="grid gap-1.5 text-xs text-slate-300">Categoria (opcional)<select name="category_id" class="{{ $scheduleInput }}"><option value="">Sem categoria</option>@foreach ($taskCategories as $category)<option value="{{ $category->id }}" @selected($task?->category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <div class="flex flex-wrap items-center gap-2"><input data-task-category-name maxlength="100" placeholder="Nome de uma nova categoria" aria-label="Nome da nova categoria" class="{{ $scheduleInput }} flex-1"><button type="button" data-task-category-add="{{ route('tarefas.categorias.store', absolute: false) }}" class="shrink-0 rounded-lg border border-indigo-400/30 px-3 py-2.5 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/10">+ Criar categoria</button></div>
    <p data-task-category-error role="alert" class="hidden text-xs text-rose-300"></p>
</div>
