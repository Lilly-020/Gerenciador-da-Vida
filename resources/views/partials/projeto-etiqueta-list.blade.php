@forelse ($projeto->etiquetas as $etiqueta)
    <div data-project-label-row data-name="{{ $etiqueta->name }}" data-color="{{ $etiqueta->color }}" data-url="{{ route('projetos.etiquetas.update', [$projeto, $etiqueta], absolute: false) }}" class="flex items-center gap-3">
        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
            <x-checkbox :checked="$etiqueta->selected" :disabled="!$canEdit" data-project-label-toggle aria-label="Selecionar etiqueta {{ $etiqueta->name }}" />
            <span class="min-w-0 flex-1 truncate rounded-lg px-3 py-2.5 text-sm font-semibold {{ \App\Models\ProjetoEtiqueta::COLORS[$etiqueta->color]['class'] }}">{{ $etiqueta->name }}</span>
        </label>
        @if ($canEdit)
            <button type="button" data-project-label-edit aria-label="Editar etiqueta {{ $etiqueta->name }}" class="rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m16 3 5 5-12 12-6 1 1-6zM14 5l5 5" /></svg></button>
            <button type="button" data-project-label-delete aria-label="Excluir etiqueta {{ $etiqueta->name }}" class="rounded-lg p-2 text-rose-300 hover:bg-rose-400/10"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" /></svg></button>
        @endif
    </div>
@empty
    <p class="py-4 text-center text-sm text-slate-500">Nenhuma etiqueta criada neste projeto.</p>
@endforelse
