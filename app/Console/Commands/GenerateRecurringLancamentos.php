<?php

namespace App\Console\Commands;

use App\Models\Lancamento;
use Illuminate\Console\Command;

class GenerateRecurringLancamentos extends Command
{
    protected $signature = 'financeiro:recorrencias';

    protected $description = 'Gera ocorrências recorrentes até um ano à frente, respeitando os limites de cada série';

    public function handle(): int
    {
        Lancamento::withoutGlobalScope('owner')->where('is_recurring_template', true)->where('recurrence_active', true)
            ->with('partes')->eachById(function (Lancamento $template): void {
                $template->generateUpcomingOccurrences(10000);
            });
        $this->info('Recorrências atualizadas.');

        return self::SUCCESS;
    }
}
