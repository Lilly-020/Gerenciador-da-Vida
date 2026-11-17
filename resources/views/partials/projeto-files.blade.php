<div data-project-files>
    @if ($canEdit)
        <form data-project-file-form hidden method="POST" enctype="multipart/form-data" action="{{ route('projetos.arquivos.store', $projeto, absolute: false) }}">
            @csrf
            <input type="file" name="file" data-project-file-input aria-label="Selecionar anexo" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx,.xls,.xlsx">
        </form>
    @endif
    <p data-project-file-status hidden role="status" class="mt-4 text-sm text-indigo-200"></p>
    <p data-project-file-error hidden role="alert" class="mt-4 text-sm text-rose-300"></p>
    @if ($projeto->files->isNotEmpty())
        <section data-project-section="files" class="mt-6 border-t border-white/10 pt-5">
            <h3 class="mb-4 text-base font-semibold text-white">Arquivos <span class="ml-1 text-sm font-normal text-slate-500">{{ $projeto->files->count() }}</span></h3>
            <ul class="grid gap-4">
                @foreach ($projeto->files as $file)
                    @php
                        $downloadUrl = route('projetos.arquivos.download', [$projeto, $file], absolute: false);
                        $openUrl = $file->isPreviewableImage() ? route('projetos.arquivos.preview', [$projeto, $file], absolute: false) : $downloadUrl;
                    @endphp
                    <li class="flex min-w-0 items-center gap-3 sm:gap-4">
                        <a href="{{ $openUrl }}" target="_blank" rel="noopener" aria-label="Abrir {{ $file->original_name }}" class="flex h-16 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-white/10 bg-slate-950 sm:h-20 sm:w-28">
                            @if ($file->isPreviewableImage())
                                <img src="{{ $openUrl }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            @else
                                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-300">{{ pathinfo($file->original_name, PATHINFO_EXTENSION) ?: 'Arquivo' }}</span>
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $openUrl }}" target="_blank" rel="noopener" class="block wrap-break-word text-sm font-semibold text-slate-200 hover:text-indigo-200 sm:text-base">{{ $file->original_name }}</a>
                            <p class="mt-1 text-xs text-slate-500">Adicionado em <time datetime="{{ $file->created_at->toIso8601String() }}">{{ $file->created_at->format('d/m/Y H:i') }}</time> · {{ number_format($file->size / 1024, 1, ',', '.') }} KB</p>
                        </div>
                        <div class="flex shrink-0 gap-1 sm:gap-2">
                            <a href="{{ $openUrl }}" target="_blank" rel="noopener" aria-label="Abrir {{ $file->original_name }} em outra aba" class="project-tool p-2!"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3h7v7m0-7L10 14M10 3H3v18h18v-7" /></svg></a>
                            <details class="relative">
                                <summary aria-label="Ações de {{ $file->original_name }}" class="project-tool cursor-pointer list-none px-2!">⋯</summary>
                                <div class="absolute right-0 z-10 mt-2 grid w-40 gap-1 rounded-xl border border-white/10 bg-slate-900 p-2 text-xs shadow-xl">
                                    <a href="{{ $downloadUrl }}" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-white/5">Baixar arquivo</a>
                                    @if ($canEdit)
                                        <button type="button" data-project-file-delete data-url="{{ route('projetos.arquivos.destroy', [$projeto, $file], absolute: false) }}" class="rounded-lg px-3 py-2 text-left text-rose-300 hover:bg-white/5">Remover anexo</button>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
