<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User — single canonical admin for the Temple Trust Management System.
 *
 * Phase 4 admin auth lands now (per user decision 2026-08-02). Doctrine:
 *
 *   - ULID primary key (CHAR(26)) to match every other V1 table. The
 *     `HasUlids` trait generates a 26-char Crockford Base32 ULID on
 *     insert; we never assign ids manually.
 *   - `role` is locked to 'admin' in this pass. A migration follow-on
 *     will relax the CHECK constraint when additional roles land.
 *   - `isAdmin()` is the only role predicate the kernel exposes. Any
 *     new role check belongs in a service, not on this model.
 *   - Passwords are hashed by the cast (`'hashed'`), so callers pass
 *     plaintext and Eloquent stores a bcrypt hash. LoginRequest uses
 *     `Hash::check` semantics indirectly via `Auth::attempt`.
 *   - No `SoftDeletes`. Users are operational accounts, not domain
 *     entities; archival semantics live elsewhere.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/AGENTS.md §"Phase 4: Admin Kernel"
 */
final class User extends Authenticatable
{
    use HasFactory;
    use HasUlids;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Single canonical admin role predicate.
     *
     * Returns true iff the user's role column equals 'admin'. This is
     * the only role check the system performs in Pass 1; EnsureUserIsAdmin
     * middleware delegates here. Future role expansion: add typed
     * enums on the model and replace this with `in_array($role, [...])`.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
