<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * LoginRequest — credentials validation + throttle for admin login.
 *
 * Doctrine (mirrors Breeze 1.x's `inertia-common` Auth/LoginRequest):
 *   - Validation rules are intentionally minimal: email + password.
 *     Extra fields (remember-me, captcha, etc.) are not needed in Pass 1
 *     because the admin surface is single-tenant and single-user.
 *   - `authenticate()` runs ONLY after `ensureIsNotRateLimited()` so the
 *     throttle counter increments ONLY on real credential failures —
 *     not on validation errors (e.g. malformed email syntax). This keeps
 *     the 5-attempts-per-minute window meaningful.
 *   - The throttle key is `email + IP` so two admins on the same IP
 *     don't share a counter, and one admin on the same email can't lock
 *     themselves out across sessions.
 *   - On lockout we fire the `Lockout` event AND throw — Breeze canonical.
 *     Future event listener can hook in for alerting.
 */
final class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Throws ValidationException on bad credentials or lockout. Returns
     * void on success — the caller redirects via the controller.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
