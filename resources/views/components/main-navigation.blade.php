@php
    $navItems = [
        ['route' => 'dashboard', 'label' => 'Dashboard'],
        ['route' => 'sonhos', 'label' => 'Sonhos'],
        ['route' => 'projetos', 'label' => 'Projetos'],
        ['route' => 'cursos', 'label' => 'Planner', 'match' => ['cursos', 'quadros.*']],
        ['route' => 'financeiro', 'label' => 'Financeiro', 'match' => 'financeiro*'],
        ['route' => 'tarefas', 'label' => 'Tarefas'],
        ['route' => 'notas', 'label' => 'Notas'],
    ];
@endphp

<nav aria-label="Navegação principal" class="flex flex-col gap-2">
    @foreach ($navItems as $item)
        @php($isActive = request()->routeIs($item['match'] ?? $item['route']))
        <a href="{{ route($item['route'], absolute: false) }}" @if ($isActive) aria-current="page" @endif class="flex min-h-11 items-center rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-indigo-400 {{ $isActive ? 'bg-indigo-500/25 text-indigo-100 ring-1 ring-indigo-400/30' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
    @can('manage-users')
        <a href="{{ route('admin.usuarios.index', absolute: false) }}" @if (request()->routeIs('admin.usuarios.*')) aria-current="page" @endif class="flex min-h-11 items-center rounded-xl px-4 py-3 text-sm font-semibold {{ request()->routeIs('admin.usuarios.*') ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">Usuários</a>
    @endcan
    @auth
        @php($pendingInvites = auth()->user()->pendingInvitesCount())
        <a href="{{ route('perfil', ($pendingInvites + $unreadNotificationCount) ? ['aba' => 'notificacoes'] : [], absolute: false) }}" @if (request()->routeIs('perfil')) aria-current="page" @endif class="mt-6 flex min-h-11 items-center justify-between rounded-xl border-t border-white/10 px-4 py-3 text-sm font-semibold {{ request()->routeIs('perfil') ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
            Perfil
            <span data-app-notification-count data-count-url="{{ route('perfil.notificacoes.count', absolute: false) }}" data-invites="{{ $pendingInvites }}" data-url="{{ route('perfil', ['aba' => 'notificacoes'], absolute: false) }}" @if (! ($pendingInvites + $unreadNotificationCount)) hidden @endif class="rounded-full bg-rose-500 px-2 py-0.5 text-xs text-white" title="Notificações pendentes">{{ $pendingInvites + $unreadNotificationCount }}</span>
        </a>
        <form method="POST" action="{{ route('logout', absolute: false) }}">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center rounded-xl px-4 py-3 text-sm font-semibold text-slate-400 hover:bg-white/5 hover:text-white">Sair</button>
        </form>
    @endauth
</nav>
