<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores one application-wide configuration value.
 *
 * The flexible key/value table preserves the original application's ability
 * to add settings incrementally. Application code should read and write these
 * records through App\Services\ApplicationSettings so values remain typed.
 */
class ApplicationSetting extends Model
{
    /**
     * Settings may be created or updated by their internal key and value.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];
}
