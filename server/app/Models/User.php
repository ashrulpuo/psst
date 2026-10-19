<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['username', 'name', 'email', 'avatar_url'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    /**
     * Mirror the column defaults so a freshly created model matches one loaded from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_USER,
        'username' => null,
        'avatar_url' => null,
        'email_verified_at' => null,
        'banned_at' => null,
        'ban_reason' => null,
        'last_login_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * There are no passwords (SSO only). Sanctum's session-invalidation check still needs a stable
     * per-user secret, so derive one from the key instead of reading a column that does not exist.
     */
    public function getAuthPassword(): string
    {
        return hash_hmac('sha256', (string) $this->getKey(), (string) config('app.key'));
    }

    /** No "remember me" tokens: sessions are cookie-only. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /** @return HasMany<Identity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class);
    }

    /** @return HasMany<Thread, $this> */
    public function receivedThreads(): HasMany
    {
        return $this->hasMany(Thread::class, 'receiver_id');
    }

    /** @return HasMany<Thread, $this> */
    public function sentThreads(): HasMany
    {
        return $this->hasMany(Thread::class, 'sender_id');
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
