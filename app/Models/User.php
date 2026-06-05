<?php

namespace App\Models;

use EslamRedaDiv\FilamentCopilot\Concerns\HasCopilotChat;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'my_id', 'lead_id', 'stockist_id', 'assigned_cities', 'is_active', 'sms_notifications'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasCopilotChat, HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active ?? true;
    }

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

            if (! isset($user->is_active) && Schema::hasColumn('users', 'is_active')) {
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
            'sms_notifications' => 'boolean',
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

    public function stockist()
    {
        return $this->belongsTo(Stockist::class, 'stockist_id');
    }

    public function lga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function managedWarehouses()
    {
        return $this->hasMany(Warehouse::class, 'manager_id');
    }

    public function salesWarehouses()
    {
        return $this->hasMany(Warehouse::class, 'sales_person_id');
    }

    public function agentStocks()
    {
        return $this->hasMany(AgentStock::class, 'user_id');
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
