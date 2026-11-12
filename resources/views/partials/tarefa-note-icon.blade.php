@if ($task->notas->isNotEmpty())
    <span data-task-note-icon class="inline-flex shrink-0 align-middle text-indigo-300" role="img" aria-label="Nota vinculada" title="Nota vinculada">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H5a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9zM14 3v6h6M8 13h8M8 17h5" /></svg>
    </span>
@endif
