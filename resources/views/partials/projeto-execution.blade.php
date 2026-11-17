    <section data-project-section="tasks" class="project-tasks">
        <h3 class="mb-4 text-sm font-semibold text-white">Tarefas <span class="ml-2 text-slate-500">{{ $projeto->tasks->count() }}</span></h3>
        <div class="mt-4 grid gap-2">
            @foreach ($projeto->tasks->whereNull('parent_id') as $rootTask)
                @include('partials.projeto-task', ['task' => $rootTask])
                @foreach ($projeto->tasks->where('parent_id', $rootTask->id) as $childTask)
                    @include('partials.projeto-task', ['task' => $childTask])
                @endforeach
            @endforeach
        </div>
        @if ($canEdit)
            <details data-details-key="new-task" class="mt-3 rounded-xl border border-dashed border-white/15 p-3">
                <summary class="cursor-pointer text-center text-sm text-indigo-300">+ Adicionar nova tarefa</summary>
                <form method="POST" action="{{ route('projetos.tarefas.store', $projeto, absolute: false) }}" data-project-task-form class="mt-4 grid gap-3">
                    @csrf
                    @include('partials.projeto-task-fields', ['task' => null])
                    <button type="submit" class="justify-self-start rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Adicionar tarefa</button>
                </form>
            </details>
        @endif
    </section>
