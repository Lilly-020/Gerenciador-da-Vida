<x-layout title="Sonhos" align="start">
    <div class="w-full max-w-3xl">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Meus sonhos</h1>
                <p class="mt-1 text-sm text-slate-400">Transforme seus objetivos em conquistas.</p>
            </div>
            <button
                type="button"
                id="open-new-sonho-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Novo sonho
            </button>
        </div>

        <div class="mb-8">
            @include('partials.sonho-stats', ['stats' => $stats])
        </div>

        <nav aria-label="Situação dos sonhos" class="mb-4 flex flex-wrap gap-2">
            @foreach (['' => 'Todos', 'not_started' => 'Não iniciados', 'in_progress' => 'Em andamento', 'completed' => 'Concluídos', 'archived' => 'Arquivados'] as $status => $label)
                <a href="{{ route('sonhos', ['status' => $status, 'sort' => request('sort')]) }}" @if (request('status', '') === $status) aria-current="page" @endif class="rounded-full border px-3 py-2 text-sm {{ request('status', '') === $status ? 'border-indigo-400/40 bg-indigo-500/20 text-indigo-200' : 'border-white/10 text-slate-400 hover:text-white' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('sonhos') }}" class="mb-4 flex items-center gap-2 text-sm text-slate-300">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <label for="sonhos-sort">Ordenar por</label>
            <select id="sonhos-sort" name="sort" class="rounded-lg border border-white/10 bg-slate-900 p-2">
                @foreach (['' => 'Mais recentes', 'priority' => 'Prioridade', 'target_date' => 'Data-alvo'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="rounded-lg border border-white/10 px-3 py-2">Aplicar</button>
        </form>
        <x-filtros alvo="sonhos-grid" data-label="Criado" />

        <div id="sonhos-grid" class="grid grid-cols-1 gap-4">
            @forelse ($sonhos as $sonho)
                @include('partials.sonho-card', ['sonho' => $sonho])
            @empty
                <div data-sonhos-empty class="col-span-full rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
                    Nenhum sonho nesta situação. Use “Novo sonho” para cadastrar um objetivo.
                </div>
            @endforelse
        </div>
    </div>

    <x-slot:modals>
        <x-sonho-create-modal />
        <x-sonho-edit-modal />
    </x-slot:modals>
</x-layout>
