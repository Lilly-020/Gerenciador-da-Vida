<?php

namespace App\Support;

use App\Models\Sonho;

class DreamUniverse
{
    public const array CATEGORIES = [
        'finance' => 'Financeiro',
        'family' => 'Família',
        'career' => 'Carreira',
        'studies' => 'Estudos',
        'travel' => 'Viagens',
        'health' => 'Saúde e bem-estar',
        'personal' => 'Pessoal',
        'other' => 'Outros',
    ];

    /** @return array<string, mixed> */
    public static function dream(Sonho $sonho): array
    {
        $sonho->loadMissing(['tasks', 'user', 'participacoes.participant']);

        return [
            'id' => $sonho->id,
            'title' => $sonho->title,
            'notes' => $sonho->notes,
            'category' => $sonho->category ?? 'other',
            'priority' => $sonho->priority,
            'status' => $sonho->status,
            'status_label' => $sonho->statusMeta()['label'],
            'archived' => $sonho->archived,
            'progress' => $sonho->progress,
            'financial_progress' => $sonho->financial_progress,
            'financial_goal' => $sonho->financial_goal,
            'saved_amount' => $sonho->saved_amount,
            'target_date' => $sonho->target_date?->format('Y-m-d'),
            'created_at' => $sonho->created_at?->format('Y-m-d'),
            'image_url' => $sonho->image_url,
            'owner_name' => $sonho->user->name,
            'can_manage' => $sonho->user_id === auth()->id(),
            'url' => route('sonhos.update', $sonho, absolute: false),
            'tasks_url' => route('sonhos.tarefas.store', $sonho, absolute: false),
            'tasks' => $sonho->tasks->map(fn ($task): array => [
                'id' => $task->id, 'title' => $task->title, 'completed' => $task->completed,
                'url' => route('sonhos.tarefas.update', [$sonho, $task], absolute: false),
            ])->values()->all(),
            'collaboration' => view('components.friend-collaboration', ['item' => $sonho, 'type' => 'sonho'])->render(),
        ];
    }
}
