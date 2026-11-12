<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

class ScheduleNotaRequest extends StoreTarefaRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return Arr::only(parent::rules(), ['title', 'date', 'start_time', 'end_time', 'end_date']);
    }
}
