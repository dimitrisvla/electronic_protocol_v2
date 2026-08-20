<?php

namespace App\Enums;

/** Fields exposed by the advanced protocol search interface. */
enum ProtocolSearchField: string
{
    case Subject = 'subject';
    case Sender = 'sender';
    case Recipient = 'recipient';
    case Notes = 'notes';
    case ArchiveFolder = 'archive_folder';
    case ProcessingOfficer = 'processing_officer';
    case AttachmentName = 'attachment_name';

    public function label(): string
    {
        return (string) __("search.fields.{$this->value}");
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
