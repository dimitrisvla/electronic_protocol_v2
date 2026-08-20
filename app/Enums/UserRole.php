<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Assigner = 'assigner';
    case ProtocolOfficer = 'protocol_officer';
    case Viewer = 'viewer';

    /**
     * Return the localized user-facing role name.
     */
    public function label(): string
    {
        return (string) __("roles.{$this->value}");
    }
}