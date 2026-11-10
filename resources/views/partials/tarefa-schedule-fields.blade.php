<div class="grid gap-3 sm:grid-cols-3">
    <label class="grid gap-1 text-xs text-slate-400">Tipo<select name="kind">@foreach (\App\Models\Tarefa::KINDS as $value => $label)<option value="{{ $value }}" @selected(($task?->kind ?? 'atividade') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="grid gap-1 text-xs text-slate-400">Início<input name="start_time" type="time" value="{{ $task?->start_time ? substr($task->start_time, 0, 5) : '' }}"></label>
    <label class="grid gap-1 text-xs text-slate-400">Término<input name="end_time" type="time" value="{{ $task?->end_time ? substr($task->end_time, 0, 5) : '' }}"></label>
</div>
<p class="mt-2 text-[11px] text-slate-500">Sem horário, aparece em Dia todo. Sem término, o bloco usa uma hora na agenda. Os horários são no mesmo dia.</p>
