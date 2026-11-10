@php($inputClass = 'w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-400/30')
<details class="rounded-xl border border-white/10 p-3 text-sm text-slate-300" open>
    <summary class="cursor-pointer text-indigo-300">Acompanhamento do aprendizado</summary>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="grid gap-1.5">Categoria<input name="category" maxlength="255" placeholder="Ex.: Programação" class="{{ $inputClass }}"></label>
        <label class="grid gap-1.5">Acompanhar por<select name="progress_unit" class="{{ $inputClass }}"><option value="lessons">Aulas</option><option value="modules">Módulos</option></select></label>
        <label class="grid gap-1.5">Total de aulas ou módulos<input type="number" name="total_lessons" min="1" max="1000000" step="1" class="{{ $inputClass }}"></label>
        <label class="grid gap-1.5">Aulas ou módulos concluídos<input type="number" name="completed_lessons" min="0" max="1000000" step="1" value="0" required class="{{ $inputClass }}"></label>
        <label class="grid gap-1.5">Carga horária total (h)<input type="number" name="duration_hours" min="0.01" max="999999.99" step="0.01" class="{{ $inputClass }}"></label>
        <label class="grid gap-1.5">Horas estudadas<input type="number" name="studied_hours" min="0" max="999999.99" step="0.01" value="0" required class="{{ $inputClass }}"></label>
        <label class="grid gap-1.5">Próxima revisão<input type="date" name="next_review_at" class="{{ $inputClass }} scheme-dark"></label>
        <label class="grid gap-1.5">Avaliação após concluir<select name="rating" class="{{ $inputClass }}"><option value="">Sem avaliação</option>@for ($score = 1; $score <= 5; $score++)<option value="{{ $score }}">{{ $score }} de 5 estrelas</option>@endfor</select></label>
        <label class="grid gap-1.5 sm:col-span-2">Meu aprendizado<textarea name="notes" rows="5" maxlength="50000" placeholder="Módulo 1 — Fundamentos&#10;Aprendi sobre rotas, controllers e migrations.&#10;&#10;Módulo 2 — Eloquent&#10;Anotações sobre relacionamentos e consultas." class="{{ $inputClass }}"></textarea></label>
    </div>
</details>
