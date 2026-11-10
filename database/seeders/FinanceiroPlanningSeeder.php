<?php

namespace Database\Seeders;

use App\Models\CategoriaFinanceira;
use App\Models\ContaFinanceira;
use App\Models\FinanceiroActivity;
use App\Models\Lancamento;
use App\Models\LancamentoFile;
use App\Models\TransferenciaFinanceira;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class FinanceiroPlanningSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $user = User::first();
        if (! $user || ContaFinanceira::where('user_id', $user->id)->exists()) {
            return;
        }
        $bank = ContaFinanceira::factory()->for($user)->create(['name' => 'Conta de exemplo', 'opening_balance' => 1000]);
        $cash = ContaFinanceira::factory()->for($user)->create(['name' => 'Dinheiro de exemplo', 'kind' => 'dinheiro']);
        TransferenciaFinanceira::factory()->for($user)->create(['source_id' => $bank->id, 'destination_id' => $cash->id, 'amount' => 100]);
        CategoriaFinanceira::factory()->for($user)->create(['name' => 'Planejamento de exemplo']);
        $row = Lancamento::factory()->for($user)->saida()->previsto()->create(['description' => 'Conta a pagar de exemplo', 'account_id' => $bank->id]);
        $path = 'financeiro-comprovantes/exemplo.txt';
        Storage::disk('local')->put($path, 'Comprovante de demonstração.');
        LancamentoFile::factory()->for($row)->create(['path' => $path, 'original_name' => 'exemplo.txt']);
        FinanceiroActivity::factory()->for($user)->create(['actor_id' => $user->id, 'description' => 'Criou exemplos de planejamento financeiro.']);
    }
}
