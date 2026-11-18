@if (! empty($cartao->attachments) || $cartao->curso?->files->isNotEmpty())
    <h3 class="mb-4!">Arquivos</h3>
    <div class="grid gap-4">
        @foreach ($cartao->attachments ?? [] as $file)
            <x-quadro-file :file="$file" :url="route('quadros.arquivos.show', [$quadro, $cartao, $file['id']], absolute: false)" :delete-url="$canEdit ? route('quadros.arquivos.destroy', [$quadro, $cartao, $file['id']], absolute: false) : null" />
        @endforeach
        @foreach ($cartao->curso?->files ?? [] as $legacy)
            @php
                $mime = match (strtolower(pathinfo($legacy->original_name, PATHINFO_EXTENSION))) {
                    'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
                    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'pdf' => 'application/pdf',
                    default => 'application/octet-stream',
                };
            @endphp
            <x-quadro-file :file="['id' => 'legacy-'.$legacy->id, 'name' => $legacy->original_name, 'mime' => $mime, 'size' => $legacy->size, 'at' => $legacy->created_at]" :url="route('quadros.legacy-file', [$quadro, $cartao, $legacy], absolute: false)" />
        @endforeach
    </div>
@endif
