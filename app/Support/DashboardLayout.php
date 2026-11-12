<?php

namespace App\Support;

class DashboardLayout
{
    public const WIDGETS = [
        'tarefas' => 'Tarefas',
        'financeiro' => 'Financeiro',
        'sonhos' => 'Sonhos',
        'projetos' => 'Projetos',
        'cursos' => 'Cursos',
    ];

    public const TASK_VIEWS = [
        'calendar' => 'Minimalista',
        'week' => 'Semana',
        'month' => 'Calendário amplo',
        'dashboard' => 'Progresso das tarefas',
    ];

    /** @return array{widgets: list<array{id: string, visible: bool, width: string}>, task_view: string} */
    public static function defaults(): array
    {
        return [
            'widgets' => array_map(fn (string $id): array => [
                'id' => $id,
                'visible' => true,
                'width' => $id === 'tarefas' ? 'wide' : 'normal',
            ], array_keys(self::WIDGETS)),
            'task_view' => 'calendar',
        ];
    }
}
