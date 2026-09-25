<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Sonho, $this>
     */
    public function sonhos(): HasMany
    {
        return $this->hasMany(Sonho::class);
    }

    /**
     * @return HasMany<Projeto, $this>
     */
    public function projetos(): HasMany
    {
        return $this->hasMany(Projeto::class);
    }

    /**
     * @return HasMany<Curso, $this>
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * @return HasMany<Tarefa, $this>
     */
    public function tarefas(): HasMany
    {
        return $this->hasMany(Tarefa::class);
    }

    /**
     * @return HasMany<Lancamento, $this>
     */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }

    /**
     * @return HasMany<CustoFixo, $this>
     */
    public function custoFixos(): HasMany
    {
        return $this->hasMany(CustoFixo::class);
    }

    /**
     * @return HasMany<Investimento, $this>
     */
    public function investimentos(): HasMany
    {
        return $this->hasMany(Investimento::class);
    }
}
