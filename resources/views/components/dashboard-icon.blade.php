@props(['name'])
@php
    $paths = [
        'tarefas' => 'M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3M9 3h6v4H9zM8 13l2 2 5-5',
        'financeiro' => 'M3 7h18v13H3zM3 7l14-4v4M16 12h5v4h-5z',
        'sonhos' => 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z',
        'projetos' => 'M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3zM8 12v3m4-5v5m4-2v2',
        'cursos' => 'M3 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-2H3zM21 4h-6a3 3 0 0 0-3 3v14a4 4 0 0 1 4-2h5z',
        'settings' => 'M4 7h16M4 17h16M8 4v6m8 4v6',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['settings'] }}" /></svg>
