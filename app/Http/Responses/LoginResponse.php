<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Redirect the user to their preferred start section after login.
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        $route = match ($user->settings['start_section'] ?? 'dashboard') {
            'movements' => 'movimientos.index',
            'categories' => 'categorias.index',
            'accounts' => 'cuentas.index',
            'recurring' => 'recurrentes.index',
            default => 'dashboard',
        };

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended(route($route));
    }
}
