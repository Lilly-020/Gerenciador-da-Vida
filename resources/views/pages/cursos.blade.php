<x-layout title="Cursos" align="start">
    <div class="w-full max-w-7xl">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Cursos</h1>
                <p class="mt-1 text-sm text-slate-400">Arraste os cards entre as colunas para acompanhar o andamento.</p>
            </div>
            <button
                type="button"
                id="open-new-curso-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Adicionar curso
            </button>
        </div>

        <div id="cursos-board" class="flex items-start gap-4 overflow-x-auto pb-4">
            @foreach ($columns as $status => $meta)
                <div data-column="{{ $status }}" class="w-72 shrink-0 rounded-2xl border border-white/10 bg-white/5 p-3 transition">
                    <div class="mb-3 flex items-center justify-between px-1">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-white">
                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                            {{ $meta['label'] }}
                        </span>
                        <span data-column-count class="rounded-full bg-white/10 px-2 py-0.5 text-xs text-slate-400">
                            {{ $cursos->get($status, collect())->count() }}
                        </span>
                    </div>

                    <div data-column-cards class="flex min-h-20 flex-col gap-3">
                        @foreach ($cursos->get($status, collect()) as $curso)
                            @include('partials.curso-card', ['curso' => $curso])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <x-slot:modals>
        <x-curso-create-modal />
        <x-curso-edit-modal />
    </x-slot:modals>
</x-layout>
