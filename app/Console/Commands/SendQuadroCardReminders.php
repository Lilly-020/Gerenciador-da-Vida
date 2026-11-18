<?php

namespace App\Console\Commands;

use App\Models\QuadroCartao;
use App\Notifications\QuadroCardDueReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendQuadroCardReminders extends Command
{
    protected $signature = 'quadros:lembretes';

    protected $description = 'Envia lembretes dos cartões ao criador, nas notificações e por e-mail';

    public function handle(): int
    {
        $failed = false;
        QuadroCartao::whereNotNull('reminder_at')->where('reminder_at', '<=', now())->where('due_at', '>=', now()->subMinutes(5))->where('status', '!=', 'done')
            ->where(fn ($query) => $query->whereNull('reminder_notified_at')->orWhereNull('reminder_mailed_at'))
            ->eachById(function (QuadroCartao $card) use (&$failed): void {
                foreach (['database' => 'reminder_notified_at', 'mail' => 'reminder_mailed_at'] as $channel => $column) {
                    try {
                        DB::transaction(function () use ($card, $channel, $column): void {
                            $current = QuadroCartao::whereKey($card->id)->lockForUpdate()->first();
                            if (! $current || $current->{$column} || ! $current->reminder_at || $current->reminder_at->isFuture() || ! $current->due_at || $current->due_at->lt(now()->subMinutes(5)) || $current->status === 'done') {
                                return;
                            }
                            $board = $current->quadro()->withoutGlobalScope('access')->first();
                            $creator = $current->creator;
                            if (! $board || ! $creator || $board->roleFor($creator) === null) {
                                return;
                            }
                            $creator->notifyNow(new QuadroCardDueReminder($board->id, $current->id, $current->title, $current->due_at->format('d/m/Y H:i'), $channel));
                            $current->{$column} = now();
                            $current->save();
                        });
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn('Falha no canal '.$channel.' para o cartão '.$card->id.'. Será tentado novamente.');
                        $failed = true;
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
