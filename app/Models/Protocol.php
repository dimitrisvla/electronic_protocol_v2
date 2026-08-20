<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one protocol record from the protocols database table.
 *
 * The model allows Laravel to:
 * - Read and modify protocol records.
 * - Soft-delete protocols.
 * - Convert selected attributes to appropriate PHP types.
 * - Access the protocol's creator.
 * - Access its archive folder.
 * - Access all files attached to the protocol.
 * - Access its processing and information assignments.
 * - Navigate to its related protocols.
 */
class Protocol extends Model
{
    /**
     * SoftDeletes prevents a protocol from being permanently removed.
     *
     * When delete() is called, Laravel fills the deleted_at column
     * instead of physically deleting the database record.
     */
    use SoftDeletes;

    /**
     * Attributes that may be assigned using create(), update(), or fill().
     *
     * Only fields listed here can be mass-assigned by Laravel.
     */
    protected $fillable = [
        'protocol_number',
        'protocol_year',
        'protocol_date',
        'direction',
        'subject',
        'sender',
        'recipient',
        'notes',
        'archive_folder_id',
        'created_by',
    ];

    /**
     * Convert database values to suitable PHP data types.
     *
     * protocol_number and protocol_year become integers.
     * protocol_date becomes a Carbon date object.
     */
    protected function casts(): array
    {
        return [
            'protocol_number' => 'integer',
            'protocol_year' => 'integer',
            'protocol_date' => 'date',
        ];
    }

    /**
     * Get the user who created this protocol.
     *
     * The created_by column in the protocols table references
     * the id column in the users table.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the archive-catalogue entry selected for this protocol.
     *
     * The relationship is optional so existing and unclassified protocols
     * continue to work while the archive workflow is introduced gradually.
     */
    public function archiveFolder(): BelongsTo
    {
        return $this->belongsTo(ArchiveFolder::class);
    }

    /**
     * Get all files attached to this protocol.
     *
     * One protocol can have many ProtocolAttachment records.
     * Laravel automatically connects:
     *
     * protocols.id
     *      to
     * protocol_attachments.protocol_id
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProtocolAttachment::class);
    }

    /**
     * Get every processing and information assignment for this protocol.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProtocolAssignment::class);
    }

    /**
     * Canonical relation rows where this protocol has the smaller id.
     */
    public function relationsAsFirst(): HasMany
    {
        return $this->hasMany(
            ProtocolRelation::class,
            'first_protocol_id'
        );
    }

    /**
     * Canonical relation rows where this protocol has the larger id.
     */
    public function relationsAsSecond(): HasMany
    {
        return $this->hasMany(
            ProtocolRelation::class,
            'second_protocol_id'
        );
    }

    /**
     * Build one query containing every active protocol related to this one.
     *
     * Protocol's SoftDeletes scope automatically hides related records that
     * are currently in the recycle bin without deleting their relation rows.
     */
    public function relatedProtocolsQuery(): Builder
    {
        $relatedAsFirst = ProtocolRelation::query()
            ->select('second_protocol_id')
            ->where('first_protocol_id', $this->getKey());

        $relatedAsSecond = ProtocolRelation::query()
            ->select('first_protocol_id')
            ->where('second_protocol_id', $this->getKey());

        return self::query()
            ->where(function (Builder $query) use (
                $relatedAsFirst,
                $relatedAsSecond
            ): void {
                $query
                    ->whereIn('id', $relatedAsFirst)
                    ->orWhereIn('id', $relatedAsSecond);
            })
            ->orderByDesc('protocol_year')
            ->orderByDesc('protocol_number');
    }

    /**
     * Retrieve all active protocols related to this protocol.
     *
     * @return Collection<int, self>
     */
    public function relatedProtocols(): Collection
    {
        return $this->relatedProtocolsQuery()->get();
    }
}
