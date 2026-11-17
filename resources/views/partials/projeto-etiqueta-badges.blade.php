@forelse ($projeto->etiquetas->where('selected', true) as $etiqueta)
    <span class="inline-flex max-w-full items-center rounded-md px-2.5 py-1 text-xs font-semibold {{ \App\Models\ProjetoEtiqueta::COLORS[$etiqueta->color]['class'] }}"><span class="truncate">{{ $etiqueta->name }}</span></span>
@empty
    <span class="text-xs text-slate-500">Sem etiquetas</span>
@endforelse
