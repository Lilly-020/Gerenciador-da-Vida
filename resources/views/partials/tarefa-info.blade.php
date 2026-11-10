<div data-task-info-content data-task-url="{{ route('tarefas.show', $task, absolute: false) }}">
    <div class="flex items-start gap-3">
        <span class="relative mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center">
            <input type="checkbox" data-task-info-toggle="{{ route('tarefas.update', $task, absolute: false) }}" @checked($task->completed) aria-label="{{ $task->completed ? 'Marcar como pendente' : 'Concluir tarefa' }}" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-500 bg-slate-950 checked:border-indigo-400 checked:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 disabled:opacity-50">
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="pointer-events-none absolute h-3.5 w-3.5 text-white opacity-0 peer-checked:opacity-100"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </span>
        <div class="min-w-0 flex-1"><p class="text-[11px] font-medium text-indigo-300">{{ $task->category?->name ?? 'Sem categoria' }} · {{ $task->completed ? 'Concluída' : 'Pendente' }}</p><h2 id="task-info-title" class="mt-1 wrap-break-word text-base font-semibold text-white {{ $task->completed ? 'line-through opacity-60' : '' }}">{{ $task->title }}</h2></div>
        <button type="button" data-task-info-close aria-label="Fechar resumo" class="-mr-1 -mt-1 shrink-0 rounded-full border border-white/10 px-2.5 py-1 text-slate-300 hover:bg-white/10">✕</button>
    </div>
    <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl border border-white/5 bg-slate-950/40 p-3 text-xs">
        <div><dt class="text-slate-500">Início</dt><dd class="mt-1 text-slate-200">{{ $task->date->format('d/m/Y') }}{{ $task->start_time ? ' · '.substr($task->start_time, 0, 5) : ' · Dia todo' }}</dd></div>
        <div><dt class="text-slate-500">Fim</dt><dd class="mt-1 text-slate-200">{{ ($task->end_date ?? $task->date)->format('d/m/Y') }}{{ $task->end_time ? ' · '.substr($task->end_time, 0, 5) : '' }}</dd></div>
    </dl>
    @if ($task->notes)<p class="mt-3 max-h-32 overflow-y-auto whitespace-pre-wrap wrap-anywhere text-sm leading-relaxed text-slate-300">{{ $task->notes }}</p>@endif
    @if ($task->series_id)<p class="mt-3 text-xs text-indigo-300">↻ Tarefa com repetição</p>@endif
    @if ($task->files->isNotEmpty())
        <div class="mt-4 border-t border-white/10 pt-3"><h3 class="mb-2 text-xs font-medium text-slate-400">Anexos ({{ $task->files->count() }})</h3><ul class="grid max-h-40 gap-2 overflow-y-auto">@foreach ($task->files as $file)<li><a href="{{ route('tarefas.arquivos.show', [$task, $file], absolute: false) }}" class="flex items-center gap-2 rounded-lg border border-white/5 p-2 text-xs text-indigo-200 hover:bg-white/5">@if (in_array($file->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true))<img src="{{ route('tarefas.arquivos.show', [$task, $file, 'preview' => 1], absolute: false) }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover" loading="lazy">@endif<span class="min-w-0 wrap-anywhere">{{ $file->original_name }}</span></a></li>@endforeach</ul></div>
    @endif
    <p data-task-info-error role="alert" class="mt-3 hidden text-xs text-rose-300"></p>
    <div class="mt-4 flex flex-wrap gap-2 border-t border-white/10 pt-3">
        <button type="button" data-task-info-edit="{{ route('tarefas.show', $task, absolute: false) }}" class="rounded-lg bg-indigo-500 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-400">Editar</button>
        <button type="button" data-task-info-delete="{{ route('tarefas.destroy', $task, absolute: false) }}" @if ($task->series_id) data-task-series @endif class="rounded-lg border border-rose-400/20 px-4 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-400/10">Excluir</button>
    </div>
</div>
