@props(['accounts' => []])
<div id="edit-investimento-modal" class="board-modal fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center px-4 py-8"><div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="mb-5 flex items-center justify-between gap-4"><h2 class="text-lg font-semibold text-white">Editar investimento</h2><button type="button" data-modal-close aria-label="Fechar" class="text-slate-400">×</button></div>
        <form id="edit-investimento-form" action="" method="POST" class="grid gap-4">
            @csrf
            @method('PUT')
            @include('partials.financeiro.investimento-fields', ['creating' => false])
            <div class="flex justify-end gap-2"><button type="button" data-modal-close class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300">Cancelar</button><button type="submit" class="rounded-full bg-indigo-500 px-5 py-2 text-sm font-semibold text-white">Salvar investimento</button></div>
        </form>
    </div></div>
</div>
