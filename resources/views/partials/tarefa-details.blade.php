@php($fieldClass = 'w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20')
<div data-task-detail-content data-task-url="{{ route('tarefas.show', $task, absolute: false) }}">
    <div class="mb-5 flex items-start justify-between gap-4">
        <div class="min-w-0"><p class="text-xs font-medium text-indigo-300">{{ $task->category?->name ?? 'Sem categoria' }} · {{ $task->completed ? 'Concluída' : 'Pendente' }}</p><h2 id="task-detail-title" class="mt-1 wrap-break-word text-xl font-semibold text-white">{{ $task->title }}</h2></div>
        <button type="button" data-task-detail-close aria-label="Fechar detalhes" class="shrink-0 rounded-full border border-white/10 px-3 py-1.5 text-slate-300 hover:bg-white/10">✕</button>
    </div>
    <form data-task-detail-form action="{{ route('tarefas.update', $task, absolute: false) }}" class="grid gap-4">
        <label class="grid gap-1.5 text-xs text-slate-300">Título<input name="title" required maxlength="255" value="{{ $task->title }}" class="{{ $fieldClass }}"></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="grid gap-1.5 text-xs text-slate-300">Data de início<input name="date" type="date" required value="{{ $task->date->toDateString() }}" class="{{ $fieldClass }} scheme-dark"></label>
            <label class="grid gap-1.5 text-xs text-slate-300">Data de fim<input name="end_date" type="date" min="{{ $task->date->toDateString() }}" value="{{ $task->end_date?->toDateString() }}" class="{{ $fieldClass }} scheme-dark"></label>
        </div>
        @include('partials.tarefa-schedule-fields', ['task' => $task])
        <label class="grid gap-1.5 text-xs text-slate-300">Observações (opcional)<textarea name="notes" rows="3" maxlength="10000" placeholder="Informações importantes para esta tarefa…" class="{{ $fieldClass }} resize-y">{{ $task->notes }}</textarea></label>
        <p data-task-detail-error role="alert" class="hidden text-sm text-rose-300"></p>
        <button type="submit" class="justify-self-end rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400 disabled:opacity-50">Salvar alterações</button>
    </form>
    <div class="mt-4 flex justify-start border-t border-white/10 pt-4"><button type="button" data-task-detail-delete="{{ route('tarefas.destroy', $task, absolute: false) }}" @if ($task->series_id) data-task-series @endif class="rounded-lg border border-rose-400/20 px-3 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-400/10">Excluir tarefa</button></div>
    <section class="mt-5 border-t border-white/10 pt-4">
        <h3 class="text-sm font-semibold text-white">Imagens e arquivos <span class="text-slate-500">({{ $task->files->count() }})</span></h3>
        <ul class="mt-3 grid gap-2">
            @forelse ($task->files as $file)
                <li class="flex min-w-0 items-center gap-3 rounded-lg border border-white/10 bg-slate-950/40 p-2.5">
                    @if (in_array($file->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true))
                        <a href="{{ route('tarefas.arquivos.show', [$task, $file, 'preview' => 1], absolute: false) }}" target="_blank" rel="noopener" class="shrink-0"><img src="{{ route('tarefas.arquivos.show', [$task, $file, 'preview' => 1], absolute: false) }}" alt="{{ $file->original_name }}" class="h-14 w-14 rounded-md object-cover" loading="lazy"></a>
                    @endif
                    <a href="{{ route('tarefas.arquivos.show', [$task, $file], absolute: false) }}" class="min-w-0 flex-1 wrap-anywhere text-xs text-indigo-200 underline">{{ $file->original_name }}<span class="mt-1 block text-[10px] text-slate-500">{{ number_format($file->size / 1024, 1, ',', '.') }} KB · Baixar</span></a>
                    <button type="button" data-task-file-delete="{{ route('tarefas.arquivos.destroy', [$task, $file], absolute: false) }}" class="shrink-0 rounded-lg px-2 py-2 text-xs text-rose-300 hover:bg-rose-400/10">Remover</button>
                </li>
            @empty
                <li class="text-xs text-slate-500">Nenhum anexo. Adicione uma imagem ou documento para consultar depois.</li>
            @endforelse
        </ul>
        <form data-task-file-form action="{{ route('tarefas.arquivos.store', $task, absolute: false) }}" class="mt-4 grid gap-2" enctype="multipart/form-data">
            <label class="grid gap-2 text-xs text-slate-300">Adicionar imagem ou arquivo<input type="file" name="file" required accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip" class="{{ $fieldClass }} file:mr-3 file:rounded-md file:border-0 file:bg-indigo-500/20 file:px-3 file:py-1 file:text-indigo-200"></label>
            <p class="text-[11px] text-slate-500">Até 10 MB por arquivo. As alterações da tarefa serão salvas ao anexar.</p>
            <button type="submit" class="justify-self-start rounded-lg border border-indigo-400/30 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/10 disabled:opacity-50">Salvar e anexar</button>
        </form>
    </section>
</div>
