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
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'apelido'])]
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

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->apelido_key = $user->apelido === null ? null : Str::lower($user->apelido);
        });
    }

    /**
     * Find a user by apelido, ignoring case and an optional leading "@".
     */
    public static function findByApelido(string $apelido): ?self
    {
        return self::where('apelido_key', Str::lower(ltrim(trim($apelido), '@')))->first();
    }

    /**
     * How this user is shown to others: "@apelido", or their name while
     * they haven't picked one.
     */
    public function handle(): string
    {
        return $this->apelido ? '@'.$this->apelido : $this->name;
    }

    /**
     * Invitations this user sent.
     *
     * @return HasMany<Participacao, $this>
     */
    public function participacoesEnviadas(): HasMany
    {
        return $this->hasMany(Participacao::class, 'owner_id');
    }

    /**
     * Invitations this user received.
     *
     * @return HasMany<Participacao, $this>
     */
    public function participacoesRecebidas(): HasMany
    {
        return $this->hasMany(Participacao::class, 'participant_id');
    }

    public function pendingInvitesCount(): int
    {
        return $this->participacoesRecebidas()->where('status', Participacao::STATUS_PENDENTE)->count();
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
