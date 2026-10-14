<?php

namespace App\Http\Middleware;

use App\Support\Carteira;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a Financeiro route for users looking at someone else's carteira:
 * the route's section must be one the owner shared, and anything that
 * changes data needs the "pode editar" permission. In one's own carteira
 * it lets everything through.
 */
class EnsureCarteiraAccess
{
    /**
     * @var array<string, string>
     */
    private const SECTION_ROUTES = [
        'dashboard' => 'financeiro',
        'entradas' => 'financeiro.entradas',
        'saidas' => 'financeiro.saidas',
        'custos_fixos' => 'financeiro.custos-fixos',
        'investimentos' => 'financeiro.investimentos',
    ];

    public function handle(Request $request, Closure $next, string $section): Response
    {
        // Entradas and Saídas share their write routes; the controller
        // narrows it down to the lançamento's own type.
        $allowed = $section === 'lancamentos'
            ? Carteira::canSee('entradas') || Carteira::canSee('saidas')
            : Carteira::canSee($section);

        if (! $allowed) {
            $fallback = collect(self::SECTION_ROUTES)->first(fn (string $route, string $key): bool => Carteira::canSee($key));

            if ($request->isMethod('GET') && ! $request->expectsJson() && $fallback) {
                return redirect()->route($fallback);
            }

            abort(403, 'Esta seção não foi compartilhada com você.');
        }

        if (! $request->isMethodSafe() && ! Carteira::canEdit()) {
            abort(403, 'Você só pode visualizar esta carteira.');
        }

        return $next($request);
    }
}
