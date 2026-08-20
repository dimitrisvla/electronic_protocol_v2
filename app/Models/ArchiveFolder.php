<?php

namespace App\Models;

use Database\Factories\ArchiveFolderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents one entry in the organisation's archive classification plan.
 *
 * The original application stored these records in the "keepvalues" table.
 * The normalized version uses explicit English property names while retaining
 * the original Greek folder codes, descriptions and retention terminology as
 * data (for example, "Φ.1.1", "Διηνεκές" and "Κατά κρίση").
 */
class ArchiveFolder extends Model
{
    /** @use HasFactory<ArchiveFolderFactory> */
    use HasFactory;

    /**
     * Fields that administrators are allowed to maintain.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'code',
        'description',
        'retention_years',
        'retention_rule',
        'remarks',
        'is_selectable',
        'is_active',
        'sort_order',
    ];

    /**
     * Convert database values to the types expected by the application.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retention_years' => 'integer',
            'is_selectable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The broader archive category containing this folder.
     *
     * Top-level categories, such as Φ.1, have no parent.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The immediate subfolders belonging to this archive category.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    /**
     * The protocols classified under this archive folder.
     */
    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    /**
     * Limit a catalogue query to entries that remain operationally active.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Limit a catalogue query to entries available in folder selectors.
     *
     * The original proposal marks every imported row as selectable to preserve
     * its workflow. The flag also supports future display-only headings without
     * requiring a schema change.
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_selectable', true);
    }

    /**
     * Apply the stable order used by the original archive-folder proposal.
     *
     * A numeric position is necessary because ordinary text sorting would put
     * Φ.10 before Φ.2.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('code');
    }

    /**
     * Determine whether this folder has a calculable retention period.
     */
    public function hasNumericRetention(): bool
    {
        return $this->retention_years !== null;
    }

    /**
     * Determine whether retention is expressed as a non-numeric rule.
     */
    public function hasTextualRetention(): bool
    {
        return $this->retention_rule !== null
            && trim($this->retention_rule) !== '';
    }
}
