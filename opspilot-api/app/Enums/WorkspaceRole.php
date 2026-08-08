<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Approver = 'approver';
    case Requester = 'requester';
    case Auditor = 'auditor';

    public function canBeInvited(): bool
    {
        return $this !== self::Owner;
    }

    public static function fromLegacy(string $role): self
    {
        return match ($role) {
            'owner' => self::Owner,
            'admin' => self::Admin,
            default => self::Requester,
        };
    }
}
