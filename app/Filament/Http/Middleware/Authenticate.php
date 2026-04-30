<?php

namespace App\Filament\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Database\Eloquent\Model;

class Authenticate extends Middleware
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        // Check if user is_active
        if (method_exists($user, 'getIsActiveAttribute') && ! $user->is_active) {
            $guard->logout();
            $this->unauthenticated($request, $guards);

            return;
        }

        // Also check is_active attribute directly
        if (isset($user->is_active) && ! $user->is_active) {
            $guard->logout();
            $this->unauthenticated($request, $guards);

            return;
        }

        $panel = Filament::getCurrentOrDefaultPanel();

        abort_if(
            $user instanceof FilamentUser ?
                (! $user->canAccessPanel($panel)) :
                (config('app.env') !== 'local'),
            403,
        );
    }

    protected function redirectTo($request): ?string
    {
        return Filament::getLoginUrl();
    }
}
