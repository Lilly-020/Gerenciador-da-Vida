<?php

namespace App\Http\Requests;

class UpdateQuadroRequest extends StoreQuadroRequest
{
    public function authorize(): bool
    {
        return $this->route('quadro')->canManage($this->user());
    }
}
