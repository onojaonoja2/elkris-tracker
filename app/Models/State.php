<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = ['name', 'code', 'capital', 'region_id'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function lgas(): HasMany
    {
        return $this->hasMany(Lga::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
