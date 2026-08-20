<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The attributes that may be assigned using mass assignment.
 *
 * The role is intentionally excluded. The Administrator-only user creation
 * workflow validates the role and assigns it directly before saving.
 */
#[Fillable(['name', 'email', 'password'])]

/**
 * The attributes that should be hidden when the model is converted
 * to an array or JSON.
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Determine whether the user is an Administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    /**
     * Determine whether the user is an Assigner.
     */
    public function isAssigner(): bool
    {
        return $this->role === UserRole::Assigner;
    }

    /**
     * Determine whether the user is a Protocol Officer.
     */
    public function isProtocolOfficer(): bool
    {
        return $this->role === UserRole::ProtocolOfficer;
    }

    /**
     * Determine whether the user is a Viewer.
     */
    public function isViewer(): bool
    {
        return $this->role === UserRole::Viewer;
    }

    /**
     * Get assignments created by this user.
     */
    public function assignmentsCreated(): HasMany
    {
        return $this->hasMany(ProtocolAssignment::class, 'assigned_by');
    }

    /**
     * Get assignments received by this user.
     */
    public function assignmentsReceived(): HasMany
    {
        return $this->hasMany(ProtocolAssignment::class, 'assigned_to');
    }

    /**
     * Get the attributes that should be converted to specific data types.
     *
     * The role stored in the database is converted automatically
     * to a UserRole enum whenever it is accessed through this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}