<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePinIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isSuperAdmin() && $user->must_change_pin) {
            // Allow access to change-pin page, logout, and livewire requests for change-pin
            $path = $request->path();
            if (!str_contains($path, 'change-pin') && !str_contains($path, 'logout')) {
                // If it's a livewire request on another page, prevent action
                if ($request->header('X-Livewire')) {
                    return redirect()->route('filament.admin.pages.change-pin');
                }
                return redirect()->route('filament.admin.pages.change-pin');
            }
        }

        return $next($request);
    }
}
