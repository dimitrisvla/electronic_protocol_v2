<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * One symmetric relationship between two protocols.
 *
 * Only one row is stored for A-B. The smaller protocol id is always saved as
 * first_protocol_id, so asking for B-A refers to the same database record.
 */
class ProtocolRelation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'first_protocol_id',
        'second_protocol_id',
        'created_by',
    ];

    /**
     * Normalize every Eloquent write, including writes outside connect().
     */
    protected static function booted(): void
    {
        static::saving(function (self $relation): void {
            $firstId = (int) $relation->first_protocol_id;
            $secondId = (int) $relation->second_protocol_id;

            if ($firstId < 1 || $secondId < 1) {
                throw new InvalidArgumentException(
                    'Both related protocols must already exist.'
                );
            }

            if ($firstId === $secondId) {
                throw new InvalidArgumentException(
                    'A protocol cannot be related to itself.'
                );
            }

            if ($firstId > $secondId) {
                $relation->first_protocol_id = $secondId;
                $relation->second_protocol_id = $firstId;
            }
        });
    }

    public function firstProtocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class, 'first_protocol_id')
            ->withTrashed();
    }

    public function secondProtocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class, 'second_protocol_id')
            ->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create the relationship once, regardless of argument order.
     */
    public static function connect(
        Protocol $firstProtocol,
        Protocol $secondProtocol,
        ?User $creator = null
    ): self {
        if (! $firstProtocol->exists || ! $secondProtocol->exists) {
            throw new InvalidArgumentException(
                'Both related protocols must already exist.'
            );
        }

        if ($firstProtocol->is($secondProtocol)) {
            throw new InvalidArgumentException(
                'A protocol cannot be related to itself.'
            );
        }

        [$firstId, $secondId] = collect([
            (int) $firstProtocol->getKey(),
            (int) $secondProtocol->getKey(),
        ])->sort()->values()->all();

        return self::query()->firstOrCreate(
            [
                'first_protocol_id' => $firstId,
                'second_protocol_id' => $secondId,
            ],
            ['created_by' => $creator?->getKey()]
        );
    }

    /**
     * Limit a query to relation rows containing the supplied protocol.
     */
    public function scopeContaining(
        Builder $query,
        Protocol|int $protocol
    ): Builder {
        $protocolId = $protocol instanceof Protocol
            ? $protocol->getKey()
            : $protocol;

        return $query->where(function (Builder $query) use ($protocolId): void {
            $query
                ->where('first_protocol_id', $protocolId)
                ->orWhere('second_protocol_id', $protocolId);
        });
    }

    /**
     * Return the other endpoint when this row contains the supplied protocol.
     */
    public function otherProtocol(Protocol $protocol): Protocol
    {
        if ((int) $this->first_protocol_id === (int) $protocol->getKey()) {
            return $this->secondProtocol;
        }

        if ((int) $this->second_protocol_id === (int) $protocol->getKey()) {
            return $this->firstProtocol;
        }

        throw new InvalidArgumentException(
            'The supplied protocol does not belong to this relationship.'
        );
    }
}
