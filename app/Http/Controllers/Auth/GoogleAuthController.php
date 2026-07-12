<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(route('auth.google.callback'))
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('auth.google.callback'))
                ->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in could not be completed. Please try again.',
            ]);
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = trim((string) $googleUser->getId());
        $verified = filter_var(data_get($googleUser->user, 'verified_email', false), FILTER_VALIDATE_BOOL);

        if ($email === '' || $googleId === '' || ! $verified) {
            return redirect()->route('login')->withErrors([
                'google' => 'Casa Paraiso requires a verified Google email address.',
            ]);
        }

        try {
            $user = DB::transaction(function () use ($googleUser, $email, $googleId): User {
                $superAdminEmail = strtolower((string) config('auth.super_admin_email'));
                $byGoogleId = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
                $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

                if ($byGoogleId && $byEmail && ! $byGoogleId->is($byEmail)) {
                    throw new \DomainException('This Google identity is already linked to another account.');
                }

                $user = $byGoogleId ?? $byEmail ?? new User;

                if ($user->exists && ! $user->is_active) {
                    throw new \DomainException('This account is inactive. Please contact the super administrator.');
                }

                if ($user->exists && $user->google_id && $user->google_id !== $googleId) {
                    throw new \DomainException('This email is linked to a different Google identity.');
                }

                $user->fill([
                    'name' => trim((string) $googleUser->getName()) ?: ($user->name ?: Str::before($email, '@')),
                    'email' => $email,
                    'google_id' => $googleId,
                    'role' => $email === $superAdminEmail
                        ? User::ROLE_SUPER_ADMIN
                        : ($user->role ?: User::ROLE_CUSTOMER),
                    'is_active' => true,
                ]);
                $user->email_verified_at = $user->email_verified_at ?? now();
                $user->save();

                if ($user->isCustomer()) {
                    CustomerProfile::provisionFor($user);
                }

                return $user;
            });
        } catch (\DomainException $exception) {
            return redirect()->route('login')->withErrors(['google' => $exception->getMessage()]);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route($user->homeRouteName()));
    }
}
