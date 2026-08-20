<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolAttachment extends Model
{
    protected $fillable = [
        'protocol_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    /**
     * A protocol can have many attachments.
     * An attachment belongs to exactly one protocol and 
     * at most to one uploader.
     */

    /**
     * The protocol that owns this attachment.
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * The user who uploaded this attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}