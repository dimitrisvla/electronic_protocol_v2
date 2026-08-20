<?php

namespace App\Enums;

enum ProtocolAssignmentPurpose: string
{
    case Processing = 'processing';
    case Information = 'information';

    /**
     * Return a user-friendly assignment-purpose name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::Information => 'For information',
        };
    }
}
