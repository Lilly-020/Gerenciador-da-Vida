<?php

namespace App\Http\Controllers;

use App\Models\SocialProfile;
use App\Models\User;
use App\Rules\UniqueUserEmail;
use App\Support\SocialAudience;
use App\Support\SocialImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class PerfilController extends Controller
{
    /**
     * The user's own data, and — under "Notificações" — the invitations
     * they received and sent.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $audience = SocialAudience::people($user)->select('users.id');

        return view('pages.perfil', [
            'user' => $user,
            'socialProfile' => $user->socialProfile,
            'aba' => in_array($request->query('aba'), ['amigos', 'notificacoes'], true) ? 'amigos' : 'perfil',
            'friends' => $user->friends()->whereIn('users.id', clone $audience)->get(),
            'friendships' => $user->friendships()
                ->whereIn('user_low_id', clone $audience)->whereIn('user_high_id', $audience)
                ->with(['lowUser', 'highUser'])->latest()->get(),
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

    public function updateSocialProfile(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        $profile = SocialProfile::firstOrNew(['user_id' => $viewer->id]);
        Gate::authorize('update', $profile);
        $data = $request->validateWithBag('socialProfile', ['bio' => ['present', 'nullable', 'string', 'max:500'], 'avatar' => ['sometimes', SocialImages::rule()], 'cover' => ['sometimes', SocialImages::rule()]]);
        $paths = [];

        try {
            $old = DB::transaction(function () use ($viewer, $data, &$paths): array {
                User::whereKey($viewer->id)->lockForUpdate()->firstOrFail();
                $profile = SocialProfile::firstOrNew(['user_id' => $viewer->id]);
                $old = [];
                $profile->bio = $data['bio'];
                foreach (['avatar', 'cover'] as $field) {
                    if (isset($data[$field])) {
                        $key = $field.'_path';
                        $old[] = $profile->{$key};
                        $profile->{$key} = $paths[] = SocialImages::store($data[$field]);
                    }
                }
                $profile->save();

                return array_filter($old);
            });
        } catch (Throwable $failure) {
            Storage::disk('local')->delete($paths);
            throw $failure;
        }
        Storage::disk('local')->delete($old);

        return redirect()->to(route('perfil', absolute: false).'#perfil-social')->with('status', 'Perfil social atualizado.');
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
