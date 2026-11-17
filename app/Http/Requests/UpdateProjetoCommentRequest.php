<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjetoCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('comentario');
        $project = $this->route('projeto');
        abort_unless($comment->projeto_id === $project->id, 404);

        return $comment->user_id === $this->user()->id && $project->canBeEditedBy($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
