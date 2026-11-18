<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class QuadroCommentMention extends Notification
{
    public function __construct(public int $boardId, public int $cardId, public int $commentId, public string $title, public string $author) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{board_id: int, card_id: int, comment_id: int, title: string, author: string} */
    public function toDatabase(object $notifiable): array
    {
        return ['board_id' => $this->boardId, 'card_id' => $this->cardId, 'comment_id' => $this->commentId, 'title' => $this->title, 'author' => $this->author];
    }
}
