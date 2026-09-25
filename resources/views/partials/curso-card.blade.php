@php
    $downloadIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0"><path fill-rule="evenodd" d="M10 2a.75.75 0 0 1 .75.75v8.69l2.72-2.72a.75.75 0 1 1 1.06 1.06l-4 4a.75.75 0 0 1-1.06 0l-4-4a.75.75 0 0 1 1.06-1.06l2.72 2.72V2.75A.75.75 0 0 1 10 2ZM4 13.75a.75.75 0 0 0-1.5 0v1.5A2.75 2.75 0 0 0 5.25 18h9.5A2.75 2.75 0 0 0 17.5 15.25v-1.5a.75.75 0 0 0-1.5 0v1.5c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-1.5Z" clip-rule="evenodd" /></svg>';
@endphp

<div
    id="curso-card-{{ $curso->id }}"
    data-board-card
    data-curso-id="{{ $curso->id }}"
    data-status="{{ $curso->status }}"
    data-title="{{ $curso->title }}"
    data-platform="{{ $curso->platform }}"
    data-link="{{ $curso->link }}"
    data-objective="{{ $curso->objective }}"
    data-starts-at="{{ $curso->starts_at?->format('Y-m-d') }}"
    data-due-at="{{ $curso->due_at?->format('Y-m-d') }}"
    draggable="true"
    class="cursor-grab rounded-xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 transition active:cursor-grabbing"
>
    <div class="flex items-start justify-between gap-2">
        <h3 class="wrap-break-word text-sm font-semibold text-white">{{ $curso->title }}</h3>

        <label class="sr-only" for="curso-status-{{ $curso->id }}">Coluna</label>
        <select
            id="curso-status-{{ $curso->id }}"
            data-status-select
            class="shrink-0 rounded-md border border-white/10 bg-slate-950/60 px-2 py-1 text-xs text-slate-300 outline-none transition focus:border-indigo-400/50"
        >
            @foreach (\App\Models\Curso::STATUSES as $key => $meta)
                <option value="{{ $key }}" @selected($curso->status === $key)>{{ $meta['label'] }}</option>
            @endforeach
        </select>
    </div>

    @if ($curso->platform)
        <p class="mt-1 wrap-break-word text-xs text-slate-400">{{ $curso->platform }}</p>
    @endif

    @if ($curso->starts_at || $curso->due_at)
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
            @if ($curso->starts_at)
                <span class="inline-flex items-center gap-1">
                    Início
                    <span class="h-1 w-1 rounded-full bg-slate-500"></span>
                    {{ $curso->starts_at->format('d-m-Y') }}
                </span>
            @endif
            @if ($curso->due_at)
                <span class="inline-flex items-center gap-1">
                    Previsão
                    <span class="h-1 w-1 rounded-full bg-slate-500"></span>
                    {{ $curso->due_at->format('d-m-Y') }}
                </span>
            @endif
        </div>
    @endif

    @if ($curso->objective)
        <div class="mt-2">
            <p data-objective-text class="line-clamp-3 overflow-hidden text-ellipsis wrap-break-word text-xs text-slate-400">{{ $curso->objective }}</p>
            <button
                type="button"
                data-objective-toggle
                aria-expanded="false"
                class="mt-1 text-[11px] font-medium text-indigo-300 transition hover:text-indigo-200"
            >
                Ler mais
            </button>
        </div>
    @endif

    @if ($curso->link)
        <a
            href="{{ $curso->link }}"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-indigo-300 transition hover:text-indigo-200"
        >
            Acessar curso
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                <path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v9.5c0 .414.336.75.75.75h9.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 13.75 18h-9.5A2.25 2.25 0 0 1 2 15.75v-9.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd" />
                <path fill-rule="evenodd" d="M12.75 2a.75.75 0 0 0 0 1.5h3.44l-6.22 6.22a.75.75 0 1 0 1.06 1.06l6.22-6.22v3.44a.75.75 0 0 0 1.5 0V2.75a.75.75 0 0 0-.75-.75h-5.25Z" clip-rule="evenodd" />
            </svg>
        </a>
    @endif

    <div class="mt-3 border-t border-white/10 pt-3">
        <p class="mb-2 text-[11px] font-medium tracking-wide text-slate-400 uppercase">Anexos</p>

        @if ($curso->files->isNotEmpty())
            <ul class="mb-2 space-y-1.5">
                @foreach ($curso->files as $file)
                    <li class="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-slate-950/40 px-2.5 py-1.5">
                        <a
                            href="{{ $file->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex min-w-0 items-center gap-1.5 text-xs text-slate-300 hover:text-white"
                        >
                            {!! $downloadIcon !!}
                            <span class="truncate">{{ $file->original_name }}</span>
                        </a>
                        <button
                            type="button"
                            data-remove-file-url="{{ route('cursos.arquivos.destroy', [$curso, $file], absolute: false) }}"
                            class="shrink-0 text-slate-500 transition hover:text-rose-400"
                            aria-label="Remover anexo"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-indigo-300 transition hover:text-indigo-200">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 0 0-4.242 0l-7 7a3 3 0 0 0 4.241 4.243h.001l.497-.5a.75.75 0 0 1 1.064 1.057l-.498.501-.002.002a4.5 4.5 0 0 1-6.364-6.364l7-7a4.5 4.5 0 0 1 6.368 6.36l-3.455 3.553A2.625 2.625 0 1 1 9.52 9.52l3.45-3.451a.75.75 0 1 1 1.061 1.06l-3.45 3.451a1.125 1.125 0 0 0 1.587 1.595l3.454-3.553a3 3 0 0 0 0-4.242Z" clip-rule="evenodd" />
            </svg>
            Anexar diploma/certificado
            <input
                type="file"
                data-file-input
                data-upload-url="{{ route('cursos.arquivos.store', $curso, absolute: false) }}"
                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*"
                class="hidden"
            >
        </label>
    </div>

    <div class="mt-3 flex items-center justify-end gap-3 text-xs">
        <button type="button" data-edit-trigger class="font-medium text-slate-400 transition hover:text-indigo-300">
            Editar
        </button>
        <button type="button" data-delete-trigger class="font-medium text-slate-400 transition hover:text-rose-400">
            Excluir
        </button>
    </div>
</div>
