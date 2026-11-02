<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function workshops(): BelongsToMany
    {
        return $this->belongsToMany(Workshop::class, 'workshop_users')
            ->using(WorkshopUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * The role this person holds at their current workshop, if any.
     */
    public function currentRole(): ?string
    {
        return $this->currentWorkshop()?->pivot?->role;
    }

    public function canInWorkshop(string $ability): bool
    {
        return WorkshopUser::roleCan($this->currentRole(), $ability);
    }

    /**
     * The workshop this person is working in: the one picked in the admin
     * switcher, otherwise the first workshop they belong to.
     */
    public function currentWorkshop(): ?Workshop
    {
        $active = $this->workshops()->wherePivot('status', 'active');
        $chosen = session('current_workshop_id');

        if ($chosen && ($workshop = (clone $active)->where('workshops.id', $chosen)->first())) {
            return $workshop;
        }

        return $active->first();
    }
}
