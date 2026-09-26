@props(['title' => null, 'align' => 'center'])

<x-shell :title="$title" :align="$align">
    <x-slot:header>
        <header class="flex items-center gap-2 px-4 pt-10 sm:px-6">
            @php
                $navItems = [
                    ['route' => 'sonhos', 'label' => 'Sonhos'],
                    ['route' => 'projetos', 'label' => 'Projetos'],
                    ['route' => 'cursos', 'label' => 'Cursos'],
                    ['route' => 'financeiro', 'label' => 'Financeiro', 'match' => 'financeiro*'],
                    ['route' => 'tarefas', 'label' => 'Tarefas'],
                ];
            @endphp

            <div class="flex-1" aria-hidden="true"></div>

            <nav
                id="nav-tabs"
                class="relative flex min-w-0 items-center gap-1 overflow-x-auto rounded-full border border-white/10 bg-slate-900/70 p-1.5 shadow-xl shadow-black/30 backdrop-blur scrollbar-none"
            >
                <span
                    id="nav-indicator"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-1.5 left-0 z-0 w-0 rounded-full bg-indigo-500/25 opacity-0 shadow-inner shadow-indigo-900/40 ring-1 ring-inset ring-indigo-400/30"
                ></span>

                @foreach ($navItems as $item)
                    @php $isActive = request()->routeIs($item['match'] ?? $item['route']); @endphp
                    <a
                        href="{{ route($item['route'], absolute: false) }}"
                        data-nav-link
                        @if ($isActive) aria-current="page" @endif
                        class="relative z-10 shrink-0 rounded-full px-5 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors duration-200 {{ $isActive ? 'text-indigo-100' : 'text-slate-300 hover:text-white' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex flex-1 justify-end">
                @auth
                    <form method="POST" action="{{ route('logout', absolute: false) }}">
                        @csrf
                        <button
                            type="submit"
                            class="shrink-0 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:text-white"
                        >
                            Sair
                        </button>
                    </form>
                @endauth
            </div>
        </header>
    </x-slot:header>

    {{ $slot }}

    <x-slot:modals>
        {{ $modals ?? '' }}
    </x-slot:modals>
</x-shell>
