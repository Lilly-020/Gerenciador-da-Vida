@props(['file', 'url', 'deleteUrl' => null])
@php
    $image = in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    $video = in_array($file['mime'], ['video/mp4', 'video/webm', 'video/quicktime', 'application/mp4'], true);
    $pdf = $file['mime'] === 'application/pdf';
@endphp
<article data-quadro-file="{{ $file['id'] }}" data-url="{{ $url }}" data-mime="{{ $file['mime'] }}" data-name="{{ $file['name'] }}" class="quadro-file-row">
    @if ($image || $video)
        <button type="button" data-quadro-file-preview class="quadro-file-thumb" aria-label="Visualizar {{ $file['name'] }}">@if ($image)<img src="{{ $url }}?preview=1" alt="" loading="lazy">@else<x-card-icon name="play" />@endif</button>
    @else
        <div class="quadro-file-thumb"><x-card-icon name="file" /></div>
    @endif
    <div class="min-w-0 flex-1"><p class="wrap-break-word text-sm font-medium">{{ $file['name'] }}</p><p class="mt-1 text-[10px] text-slate-500">{{ \Illuminate\Support\Carbon::parse($file['at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ number_format($file['size'] / 1024, 1, ',', '.') }} KB @if (($file['source'] ?? 'card') === 'comment') · comentário @endif</p></div>
    <button type="button" data-quadro-file-menu class="quadro-tool px-2!" aria-label="Opções de {{ $file['name'] }}" aria-expanded="false"><x-card-icon name="more" /></button>
    <div data-quadro-file-actions popover="auto" class="quadro-filter-popover quadro-file-actions" aria-label="Ações do arquivo">
        @if ($image || $video)<button type="button" data-quadro-file-preview class="quadro-context-item"><x-card-icon name="image" />Visualizar arquivo</button>
        @elseif ($pdf)<a href="{{ $url }}?preview=1" target="_blank" rel="noopener noreferrer" class="quadro-context-item"><x-card-icon name="open" />Visualizar arquivo em outra aba</a>@endif
        <a href="{{ $url }}" download class="quadro-context-item"><x-card-icon name="download" />Baixar arquivo</a>
        @if ($deleteUrl)<button type="button" data-quadro-card-file-delete data-url="{{ $deleteUrl }}" class="quadro-context-item text-rose-300"><x-card-icon name="trash" />Excluir arquivo</button>@endif
    </div>
</article>
