<?php

namespace App\Http\Controllers;

use App\Models\FinanceiroActivity;
use App\Models\Lancamento;
use App\Models\LancamentoFile;
use App\Support\Carteira;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LancamentoFileController extends Controller
{
    private function ensureSection(Lancamento $lancamento): void
    {
        abort_unless(Carteira::canSee($lancamento->type === 'entrada' ? 'entradas' : 'saidas'), 403);
    }

    public function store(Request $request, Lancamento $lancamento): RedirectResponse
    {
        $this->ensureSection($lancamento);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $file = $request->file('file');
        $path = $file->store('financeiro-comprovantes', 'local');
        abort_unless($path, 500);
        $lancamento->files()->create(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'size' => $file->getSize()]);
        FinanceiroActivity::record('Anexou um comprovante a “'.$lancamento->description.'”.');

        return back();
    }

    public function download(Lancamento $lancamento, LancamentoFile $arquivo): StreamedResponse
    {
        $this->ensureSection($lancamento);
        abort_unless($arquivo->lancamento_id === $lancamento->id, 404);

        return Storage::disk('local')->download($arquivo->path, $arquivo->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Lancamento $lancamento, LancamentoFile $arquivo): RedirectResponse
    {
        $this->ensureSection($lancamento);
        abort_unless($arquivo->lancamento_id === $lancamento->id, 404);
        Storage::disk('local')->delete($arquivo->path);
        $arquivo->delete();
        FinanceiroActivity::record('Removeu um comprovante.');

        return back();
    }
}
