<?php

namespace App\Http\Controllers;

use App\Models\Participacao;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PerfilController extends Controller
{
    /**
     * The user's own data, and — under "Notificações" — the invitations
     * they received and sent.
     */
    public function show(Request $request): View
    {
        $user = $request->user();
        $recebidas = $user->participacoesRecebidas()->with(['owner', 'projeto'])->latest()->get();

        return view('pages.perfil', [
            'user' => $user,
            'aba' => $request->query('aba') === 'notificacoes' ? 'notificacoes' : 'perfil',
            'pendentes' => $recebidas->where('status', Participacao::STATUS_PENDENTE),
            'aceitas' => $recebidas->where('status', Participacao::STATUS_ACEITO),
            'enviadas' => $user->participacoesEnviadas()->with(['participant', 'projeto'])->latest()->get(),
        ]);
    }

    /**
     * Update name, apelido and e-mail. The apelido is unique regardless of
     * case ("Lilly" and "lilly" are the same apelido).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'apelido' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_.]+$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (User::where('apelido_key', Str::lower($value))->whereKeyNot($user->id)->exists()) {
                        $fail('Esse apelido já está em uso.');
                    }
                },
            ],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'name.required' => 'Informe o seu nome.',
            'apelido.required' => 'Escolha um apelido.',
            'apelido.min' => 'O apelido precisa ter pelo menos 3 caracteres.',
            'apelido.max' => 'O apelido pode ter no máximo 30 caracteres.',
            'apelido.regex' => 'Use só letras, números, ponto e sublinhado no apelido.',
            'email.required' => 'Informe o seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Esse e-mail já está em uso.',
        ]);

        $user->update($data);

        return back()->with('status', 'Perfil atualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('senha', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Informe a sua senha atual.',
            'current_password.current_password' => 'A senha atual não confere.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação não é igual à nova senha.',
            'password.min' => 'A nova senha precisa ter pelo menos 8 caracteres.',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Senha alterada.');
    }

    /**
     * Apelido search for the "convidar" fields: up to 5 other users whose
     * apelido starts with the typed text, ignoring case.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termo = preg_replace('/[^a-z0-9_.]/', '', Str::lower((string) $request->query('q')));

        if (strlen($termo) < 2) {
            return response()->json([]);
        }

        return response()->json(
            User::whereLike('apelido_key', $termo.'%')
                ->whereKeyNot($request->user()->id)
                ->orderBy('apelido_key')
                ->limit(20)
                ->get()
                // "_" is a LIKE wildcard, so confirm the prefix for real.
                ->filter(fn (User $user): bool => str_starts_with($user->apelido_key, $termo))
                ->take(5)
                ->map(fn (User $user): array => ['apelido' => $user->apelido, 'name' => $user->name])
                ->values(),
        );
    }
}
