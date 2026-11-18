<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = ['not_started' => 'Não iniciado', 'in_progress' => 'Em andamento', 'paused' => 'Pausado', 'completed' => 'Finalizado', 'in_review' => 'Em revisão', 'cancelled' => 'Cancelado'];
        foreach (DB::table('cursos')->whereNotIn('id', DB::table('quadro_cartoes')->whereNotNull('legacy_curso_id')->select('legacy_curso_id'))->distinct()->pluck('user_id') as $userId) {
            $now = now();
            $boardId = DB::table('quadros')->insertGetId(['user_id' => $userId, 'title' => 'Meus cursos', 'category' => 'Estudos', 'cover' => 'violet', 'created_at' => $now, 'updated_at' => $now]);
            foreach ($statuses as $status => $title) {
                $listId = DB::table('quadro_listas')->insertGetId(['quadro_id' => $boardId, 'title' => $title, 'position' => array_search($status, array_keys($statuses), true), 'created_at' => $now, 'updated_at' => $now]);
                foreach (DB::table('cursos')->whereNotIn('id', DB::table('quadro_cartoes')->whereNotNull('legacy_curso_id')->select('legacy_curso_id'))->where('user_id', $userId)->where('status', $status)->orderBy('id')->get() as $position => $course) {
                    $description = implode("\n\n", array_filter([$course->objective, $course->notes, $course->platform ? 'Plataforma: '.$course->platform : null, $course->link]));
                    DB::table('quadro_cartoes')->insert(['quadro_id' => $boardId, 'lista_id' => $listId, 'legacy_curso_id' => $course->id, 'title' => $course->title, 'description' => $description, 'status' => in_array($status, ['completed', 'in_review'], true) ? 'done' : ($status === 'in_progress' ? 'doing' : 'todo'), 'labels' => json_encode($course->category ? [$course->category] : []), 'assignee_ids' => json_encode([$userId]), 'due_at' => $course->due_at, 'legacy_data' => json_encode((array) $course), 'position' => $position, 'created_at' => $course->created_at, 'updated_at' => $course->updated_at]);
                }
            }
        }
    }

    /** Imported cards may have been edited and must survive a rollback. */
    public function down(): void {}
};
