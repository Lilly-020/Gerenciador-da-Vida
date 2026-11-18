@props(['quadro' => null, 'categories' => [], 'dialogId' => 'quadro-board-dialog'])
<dialog id="{{ $dialogId }}" class="quadro-dialog" aria-labelledby="{{ $dialogId }}-heading">
    <header class="quadro-dialog-header"><h2 id="{{ $dialogId }}-heading">{{ $quadro ? 'Configurar quadro' : 'Criar quadro' }}</h2><button type="button" data-quadro-close aria-label="Fechar">✕</button></header>
    <form data-quadro-board-form action="{{ $quadro ? route('quadros.update', $quadro, absolute: false) : route('quadros.store', absolute: false) }}" method="POST" enctype="multipart/form-data" class="quadro-dialog-content grid gap-5 app-scroll">
        @csrf
        <div data-quadro-cover-preview class="quadro-cover quadro-cover--{{ $quadro?->cover ?? 'violet' }} relative flex h-36 items-center justify-center gap-3 overflow-hidden rounded-xl">
            @if ($quadro?->cover_path)<img data-quadro-cover-image src="{{ route('quadros.cover', $quadro, absolute: false) }}" alt="" class="absolute inset-0 h-full w-full object-cover">@endif
        </div>
        <fieldset><legend class="mb-2 text-sm font-semibold text-slate-300">Capa</legend><div class="grid grid-cols-6 gap-2">
            @foreach (\App\Models\Quadro::COVERS as $value => $label)
                <label class="cursor-pointer"><input type="radio" name="cover" value="{{ $value }}" @checked(($quadro?->cover ?? 'violet') === $value) class="peer sr-only"><span class="quadro-cover quadro-cover--{{ $value }} block h-9 rounded-lg border-2 border-transparent peer-checked:border-white peer-focus-visible:outline-2 peer-focus-visible:outline-indigo-400" title="{{ $label }}"></span><span class="sr-only">{{ $label }}</span></label>
            @endforeach
        </div></fieldset>
        <label class="grid gap-2 text-xs text-slate-400">Ou enviar uma imagem (JPG, PNG, WEBP ou GIF, até 8 MB)<input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp,.gif" class="w-full min-w-0 text-xs"></label>
        @if ($quadro?->cover_path)<label class="flex items-center gap-2 text-xs text-slate-400"><x-checkbox name="remove_cover" value="1" />Remover imagem e usar a cor selecionada</label>@endif
        <label class="grid gap-2 text-sm text-slate-300">Título do quadro<input name="title" required maxlength="255" value="{{ $quadro?->title }}" class="w-full" placeholder="Ex.: Minha trilha de aprendizado"></label>
        <label class="grid gap-2 text-sm text-slate-300">Categoria (opcional)<input name="category" maxlength="100" list="{{ $dialogId }}-categories" value="{{ $quadro?->category }}" class="w-full" placeholder="Selecione ou crie uma categoria"></label>
        <datalist id="{{ $dialogId }}-categories">@foreach ($categories as $category)<option value="{{ $category }}">@endforeach</datalist>
        @unless ($quadro)<label class="grid gap-2 text-sm text-slate-300">Convidar participante (opcional)<input name="invite_handle" maxlength="31" list="apelido-sugestoes" data-apelido-search class="w-full" placeholder="Apelido de quem você quer convidar"></label><p class="-mt-3 text-xs leading-relaxed text-slate-500">O convite aparece na área de Cursos da pessoa. Ela acessa o quadro depois de aceitar.</p>@endunless
        <p data-quadro-error hidden role="alert" class="text-sm text-rose-300"></p>
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-white/10 pt-4"><button type="button" data-quadro-close class="quadro-tool">Cancelar</button><button type="submit" class="quadro-primary">{{ $quadro ? 'Salvar alterações' : 'Criar quadro' }}</button></footer>
        @if ($quadro && $quadro->user_id === auth()->id())<button type="button" data-quadro-delete data-url="{{ route('quadros.destroy', $quadro, absolute: false) }}" class="justify-self-start text-xs text-rose-300">Excluir quadro</button>@endif
    </form>
</dialog>
