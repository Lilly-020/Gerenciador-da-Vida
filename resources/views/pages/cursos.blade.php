<x-layout title="Cursos" align="start">
    <div class="w-full min-w-0 max-w-7xl">
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

        @include('partials.curso-stats')

        <x-filtros alvo="cursos-board" data-label="Período" :categorias="true" :cursos="true" />

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p id="cursos-scroll-hint" class="text-xs text-slate-400">↔ Role para ver todas as colunas. Arraste um curso ou use “Mover para” no menu.</p>
            <div role="group" aria-label="Visualização dos cursos" class="inline-flex rounded-full border border-white/10 bg-slate-900 p-1 text-sm">
                <button type="button" data-course-view="kanban" aria-pressed="true" class="rounded-full px-4 py-1.5 text-slate-300 aria-pressed:bg-indigo-500/25 aria-pressed:text-indigo-200">Kanban</button>
                <button type="button" data-course-view="list" aria-pressed="false" class="rounded-full px-4 py-1.5 text-slate-300 aria-pressed:bg-indigo-500/25 aria-pressed:text-indigo-200">Lista</button>
            </div>
        </div>
        <div id="cursos-board" tabindex="0" aria-label="Cursos organizados por situação" aria-describedby="cursos-scroll-hint" class="course-board flex w-full min-w-0 snap-x snap-proximity items-start gap-4 overflow-x-auto pb-4 outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/50">
            @foreach ($columns as $status => $meta)
                <div data-column="{{ $status }}" class="course-column w-[min(85vw,19rem)] shrink-0 snap-start rounded-2xl border border-white/10 bg-white/5 p-3 transition">
                    <div data-column-header class="mb-3 flex items-center justify-between px-1">
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
        <p id="cursos-empty" class="hidden rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-slate-400">Nenhum curso encontrado. Ajuste os filtros ou adicione um curso.</p>
    </div>

    <x-slot:modals>
        <x-curso-create-modal />
        <x-curso-edit-modal />
    </x-slot:modals>
</x-layout>
