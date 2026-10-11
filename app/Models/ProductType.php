<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductType extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $fillable = ['name', 'available_grammages', 'is_active'];

    protected function casts(): array
    {
        return [
            'available_grammages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_type_id');
    }

    /**
     * @return array{grammage: int, carton_quantity: int, display_unit: string, packaging: string}|null
     */
    public function grammageEntryFor(int $grammage): ?array
    {
        foreach ($this->available_grammages ?? [] as $entry) {
            if (is_array($entry) && (int) ($entry['grammage'] ?? 0) === $grammage) {
                return [
                    'grammage' => (int) $entry['grammage'],
                    'carton_quantity' => (int) ($entry['carton_quantity'] ?? 1),
                    'display_unit' => (string) ($entry['display_unit'] ?? 'g'),
                    'packaging' => (string) ($entry['packaging'] ?? 'carton'),
                ];
            }
        }

        return null;
    }

    public function cartonQuantityFor(int $grammage): int
    {
        return $this->grammageEntryFor($grammage)['carton_quantity'] ?? 1;
    }

    /**
     * Get the packaging type for a weight: 'carton' or 'bag'.
     */
    public function packagingFor(int $grammage): string
    {
        return $this->grammageEntryFor($grammage)['packaging'] ?? 'carton';
    }

    /**
     * Format a grammage according to the entry's display unit preference.
     * Weights at or above 1000g can be shown in kg; everything else stays in
     * grams. Falls back to a plain gram display when no entry exists.
     */
    public static function formatGrammage(?ProductType $productType, int $grammage): string
    {
        return static::formatGrammageEntry($productType?->grammageEntryFor($grammage), $grammage);
    }

    /**
     * @param  array{grammage?: int, display_unit?: string}|null  $entry
     */
    public static function formatGrammageEntry(?array $entry, ?int $grammage = null): string
    {
        $grammage = $grammage ?? (int) ($entry['grammage'] ?? 0);

        if ($entry && ($entry['display_unit'] ?? 'g') === 'kg' && $grammage >= 1000) {
            $value = $grammage / 1000;

            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'kg';
        }

        return $grammage.'g';
    }

    /**
     * Get the singular packaging label for a weight ('Carton' or 'Bag').
     */
    public static function packagingLabel(?ProductType $productType, int $grammage): string
    {
        return ($productType?->packagingFor($grammage) ?? 'carton') === 'bag' ? 'Bag' : 'Carton';
    }
}
