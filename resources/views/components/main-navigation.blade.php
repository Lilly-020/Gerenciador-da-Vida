@php
    $navItems = [
        ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
        ['route' => 'sonhos', 'icon' => 'heart', 'label' => 'Sonhos'],
        ['route' => 'projetos', 'icon' => 'folder', 'label' => 'Projetos'],
        ['route' => 'cursos', 'icon' => 'calendar', 'label' => 'Planner', 'match' => ['cursos', 'quadros.*']],
        ['route' => 'financeiro', 'icon' => 'wallet', 'label' => 'Financeiro', 'match' => 'financeiro*'],
        ['route' => 'tarefas', 'icon' => 'checklist', 'label' => 'Tarefas'],
        ['route' => 'social.index', 'icon' => 'people', 'label' => 'Social', 'match' => 'social.*'],
        ['route' => 'notas', 'icon' => 'file', 'label' => 'Notas'],
    ];
@endphp

<nav aria-label="Navegação principal" class="app-main-navigation flex min-h-0 flex-1 flex-col gap-1">
    @foreach ($navItems as $item)
        @php($isActive = request()->routeIs($item['match'] ?? $item['route']))
        <a href="{{ route($item['route'], absolute: false) }}" aria-label="{{ $item['label'] }}" title="{{ $item['label'] }}" @if ($isActive) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-indigo-400 {{ $isActive ? 'bg-indigo-500/25 text-indigo-100 ring-1 ring-indigo-400/30' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
            <x-card-icon :name="$item['icon']" class="size-5" />
            <span class="app-sidebar-label">{{ $item['label'] }}</span>
        </a>
    @endforeach
    @can('manage-users')
        <a href="{{ route('admin.usuarios.index', absolute: false) }}" aria-label="Usuários" title="Usuários" @if (request()->routeIs('admin.usuarios.*')) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold {{ request()->routeIs('admin.usuarios.*') ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
            <x-card-icon name="people" class="size-5" />
            <span class="app-sidebar-label">Usuários</span>
        </a>
    @endcan
    @auth
        <a href="{{ route('perfil', absolute: false) }}" aria-label="Perfil e amigos" title="Perfil e amigos" @if (request()->routeIs('perfil')) aria-current="page" @endif class="mt-6 flex min-h-11 items-center gap-3 rounded-xl border-t border-white/10 px-4 py-3 text-sm font-semibold text-slate-300">
            <x-card-icon name="profile" class="size-5" />
            <span class="app-sidebar-label">Perfil e amigos</span>
        </a>
        <form class="shrink-0" method="POST" action="{{ route('logout', absolute: false) }}">
            @csrf
            <button type="submit" aria-label="Sair" title="Sair" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-slate-400 hover:bg-white/5 hover:text-white">
                <x-card-icon name="logout" class="size-5" />
                <span class="app-sidebar-label">Sair</span>
            </button>
        </form>
    @endauth
</nav>
