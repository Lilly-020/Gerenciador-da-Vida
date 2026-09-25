@props(['title' => null, 'align' => 'center'])

<x-shell :title="$title" :align="$align">
    <x-slot:header>
        <header class="relative flex justify-center px-6 pt-10">
            @php
                $navItems = [
                    ['route' => 'sonhos', 'label' => 'Sonhos'],
                    ['route' => 'projetos', 'label' => 'Projetos'],
                    ['route' => 'cursos', 'label' => 'Cursos'],
                    ['route' => 'financeiro', 'label' => 'Financeiro'],
                    ['route' => 'tarefas', 'label' => 'Tarefas'],
                ];
            @endphp
            <nav id="nav-tabs" class="relative inline-flex flex-wrap items-center justify-center gap-1 rounded-full border border-white/10 bg-slate-900/70 p-1.5 shadow-xl shadow-black/30 backdrop-blur">
                <span
                    id="nav-indicator"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-1.5 left-0 z-0 w-0 rounded-full bg-indigo-500/25 opacity-0 shadow-inner shadow-indigo-900/40 ring-1 ring-inset ring-indigo-400/30"
                ></span>

                @foreach ($navItems as $item)
                    @php $isActive = request()->routeIs($item['route']); @endphp
                    <a
                        href="{{ route($item['route'], absolute: false) }}"
                        data-nav-link
                        @if ($isActive) aria-current="page" @endif
                        class="relative z-10 rounded-full px-5 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors duration-200 {{ $isActive ? 'text-indigo-100' : 'text-slate-300 hover:text-white' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            @auth
                <form method="POST" action="{{ route('logout', absolute: false) }}" class="absolute top-10 right-6">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:text-white"
                    >
                        Sair
                    </button>
                </form>
            @endauth
        </header>
    </x-slot:header>

    {{ $slot }}

    <x-slot:modals>
        {{ $modals ?? '' }}
    </x-slot:modals>
</x-shell>
