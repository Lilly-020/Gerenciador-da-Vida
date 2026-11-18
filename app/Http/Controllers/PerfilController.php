<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\UniqueUserEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        return view('pages.perfil', [
            'user' => $user,
            'aba' => in_array($request->query('aba'), ['amigos', 'notificacoes'], true) ? 'amigos' : 'perfil',
            'friends' => $user->friends()->get(),
            'friendships' => $user->friendships()->with(['lowUser', 'highUser'])->latest()->get(),
        ]);
    }

    public function notificationCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()])->header('Cache-Control', 'private, no-store');
    }

    public function readReminder(Request $request, string $notification): RedirectResponse
    {
        return app(NotificacaoController::class)->open($request, $notification);
    }

    /**
     * Update name, apelido and e-mail. The apelido is unique regardless of
     * case ("Lilly" and "lilly" are the same apelido).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

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
            'email' => ['bail', 'required', 'string', 'email', 'max:255', new UniqueUserEmail($user)],
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
}
