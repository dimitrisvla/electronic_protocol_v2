<?php

namespace App\Models;

use App\Enums\ProtocolAssignmentPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolAssignment extends Model
{
    /**
     * Attributes that may be assigned using create(), update(), or fill().
     */
    protected $fillable = [
        'protocol_id',
        'purpose',
        'assigned_by',
        'assigned_to',
        'due_at',
        'completed_at',
        'superseded_at',
    ];

    /**
     * Convert stored assignment values to useful PHP types.
     */
    protected function casts(): array
    {
        return [
            'purpose' => ProtocolAssignmentPurpose::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * Get the protocol to which this assignment belongs.
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * Get the user who created the assignment.
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who received the assignment.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Limit the query to unfinished processing assignments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->where('purpose', ProtocolAssignmentPurpose::Processing->value)
            ->whereNull('completed_at')
            ->whereNull('superseded_at');
    }

    /**
     * Limit the query to information-only assignments.
     */
    public function scopeInformation(Builder $query): Builder
    {
        return $query->where(
            'purpose',
            ProtocolAssignmentPurpose::Information->value
        );
    }

    /**
     * Limit the query to completed processing assignments.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query
            ->where('purpose', ProtocolAssignmentPurpose::Processing->value)
            ->whereNotNull('completed_at')
            ->whereNull('superseded_at');
    }

    /**
     * Limit the query to processing assignments replaced by reassignment.
     */
    public function scopeSuperseded(Builder $query): Builder
    {
        return $query
            ->where('purpose', ProtocolAssignmentPurpose::Processing->value)
            ->whereNotNull('superseded_at');
    }
}
