<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[Table('tarefa_categorias')]
#[Fillable(['name'])]
class TarefaCategoria extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            $query->where('user_id', Auth::id());
        });
        static::creating(function (self $category): void {
            $category->user_id = Auth::id();
        });
    }
}
