<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuadroCardDueReminder extends Notification
{
    public function __construct(public int $boardId, public int $cardId, public string $title, public string $dueAt, public string $channel = 'database') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lembrete: '.$this->title)
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('O cartão “'.$this->title.'” tem entrega em '.$this->dueAt.'.')
            ->action('Abrir cartão', route('quadros.show', $this->boardId).'#card='.$this->cardId)
            ->line('Você está recebendo este lembrete porque criou o cartão.');
    }

    /** @return array{board_id: int, card_id: int, title: string, due_at: string} */
    public function toDatabase(object $notifiable): array
    {
        return ['board_id' => $this->boardId, 'card_id' => $this->cardId, 'title' => $this->title, 'due_at' => $this->dueAt];
    }
}
