<?php

namespace App\Models;

use EslamRedaDiv\FilamentCopilot\Concerns\HasCopilotChat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'my_id', 'lead_id', 'assigned_cities', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasCopilotChat, HasFactory, Notifiable;

    /**
     * Boot up the model to hook into lifecycle events natively.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->my_id)) {
                do {
                    $id = random_int(100000, 999999);
                } while (self::where('my_id', $id)->exists());

                $user->my_id = (string) $id;
            }

            if (! isset($user->is_active)) {
                $user->is_active = true;
            }
        });
    }

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
            'assigned_cities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the lead that this user (rep) reports to.
     */
    public function lead()
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    /**
     * Get the reps that report to this user (lead).
     */
    public function reps()
    {
        return $this->hasMany(User::class, 'lead_id');
    }

    /**
     * Get the customers assigned to this user as lead.
     */
    public function leadCustomers()
    {
        return $this->hasMany(Customer::class, 'lead_id');
    }

    /**
     * Get the customers assigned to this user as rep.
     */
    public function repCustomers()
    {
        return $this->hasMany(Customer::class, 'rep_id');
    }

    /**
     * Customers where this user is one of the leads (many-to-many).
     */
    public function customersLed()
    {
        return $this->belongsToMany(Customer::class, 'customer_lead', 'user_id', 'customer_id')->withTimestamps();
    }

    /**
     * Customers where this user is one of the reps (many-to-many).
     */
    public function customersRepped()
    {
        return $this->belongsToMany(Customer::class, 'customer_rep', 'user_id', 'customer_id')->withTimestamps();
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
