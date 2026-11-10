<div class="mt-3 grid gap-3 rounded-xl bg-slate-950/40 p-3 text-sm text-slate-300">
    <p>Projeto de <span class="font-semibold text-white">{{ $projeto->user->handle() }}</span> · Administrador</p>
    <p class="text-xs text-slate-400">Administrador gerencia o projeto e os convites. Editor cuida das tarefas e comentários. Visualizador acompanha as informações.</p>
    @foreach (['Participantes' => $accepted, 'Convites pendentes' => $pending] as $label => $invitations)
        <details data-details-key="{{ $label === 'Participantes' ? 'accepted' : 'pending' }}" @if ($label === 'Participantes') open @endif>
            <summary class="cursor-pointer text-xs font-medium text-slate-400">{{ $label }} ({{ $invitations->count() }})</summary>
            <ul class="mt-2 grid gap-2">
                @foreach ($invitations as $participacao)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span class="min-w-0 wrap-break-word">{{ $participacao->participant->handle() }} <span class="text-xs text-slate-400">· {{ \App\Models\Participacao::PROJECT_PERMISSIONS[$participacao->permission] }} @if ($participacao->status === 'pendente') · aguardando aceitar @endif</span></span>
                        @if ($canManage)
                            <div class="flex gap-3 text-xs">
                                @if ($participacao->status === 'pendente')
                                    <form method="POST" action="{{ route('participacoes.reenviar', $participacao, absolute: false) }}">@csrf <button class="text-indigo-300">Reenviar</button></form>
                                @endif
                                <form method="POST" action="{{ route('participacoes.destroy', $participacao, absolute: false) }}">@csrf @method('DELETE') <button class="text-rose-300">{{ $participacao->status === 'pendente' ? 'Cancelar convite' : 'Remover' }}</button></form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach
    @if ($canManage)
        <form method="POST" action="{{ route('participacoes.store', absolute: false) }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="tipo" value="projeto"><input type="hidden" name="projeto_id" value="{{ $projeto->id }}">
            <input type="text" name="apelido" required maxlength="31" autocapitalize="none" autocomplete="off" list="apelido-sugestoes" data-apelido-search aria-label="Apelido de quem convidar" placeholder="Apelido de quem convidar" class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white">
            <select name="permission" aria-label="Permissão" class="rounded-lg border border-white/10 bg-slate-950 px-2 py-2 text-sm text-white">
                @foreach (\App\Models\Participacao::PROJECT_PERMISSIONS as $value => $permissionLabel)
                    <option value="{{ $value }}" @selected($value === 'editar')>{{ $permissionLabel }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Convidar</button>
        </form>
    @endif
</div>
