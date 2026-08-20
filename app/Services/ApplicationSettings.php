<?php

namespace App\Services;

use App\Models\ApplicationSetting;

/**
 * Provides typed access to the application's operational settings.
 *
 * The original project stored all values as strings in a configs table.
 * This service retains that extensibility while preventing controllers from
 * interpreting nullable integers and booleans differently.
 */
class ApplicationSettings
{
    public const ORGANIZATION_NAME = 'organization_name';

    public const ACTIVE_PROTOCOL_YEAR = 'active_protocol_year';

    public const STARTING_PROTOCOL_NUMBER = 'starting_protocol_number';

    public const AUTOMATIC_PROTOCOL_NUMBERING =
        'automatic_protocol_numbering';

    /**
     * Name displayed for the organisation or school.
     */
    public function organizationName(): string
    {
        return (string) $this->value(
            self::ORGANIZATION_NAME,
            'Όνομα Σχολείου'
        );
    }

    /**
     * Year whose protocol numbering is currently active.
     *
     * A null year preserves the original continuous-numbering mode.
     */
    public function activeProtocolYear(): ?int
    {
        $value = $this->value(
            self::ACTIVE_PROTOCOL_YEAR,
            (string) now()->year
        );

        return $value === null || $value === ''
            ? null
            : (int) $value;
    }

    /**
     * First number proposed when no earlier protocol exists in the sequence.
     */
    public function startingProtocolNumber(): int
    {
        return max(1, (int) $this->value(
            self::STARTING_PROTOCOL_NUMBER,
            '1'
        ));
    }

    /**
     * Whether the server must issue the next safe protocol number.
     */
    public function usesAutomaticProtocolNumbering(): bool
    {
        return filter_var(
            $this->value(self::AUTOMATIC_PROTOCOL_NUMBERING, '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Update the organisation or school name.
     */
    public function setOrganizationName(string $name): void
    {
        $this->store(self::ORGANIZATION_NAME, trim($name));
    }

    /**
     * Select a yearly sequence or clear it for continuous numbering.
     */
    public function setActiveProtocolYear(?int $year): void
    {
        $this->store(
            self::ACTIVE_PROTOCOL_YEAR,
            $year === null ? null : (string) $year
        );
    }

    /**
     * Set the first number used by an empty numbering sequence.
     */
    public function setStartingProtocolNumber(int $number): void
    {
        $this->store(
            self::STARTING_PROTOCOL_NUMBER,
            (string) max(1, $number)
        );
    }

    /**
     * Enable or disable server-issued protocol numbering.
     */
    public function setAutomaticProtocolNumbering(bool $enabled): void
    {
        $this->store(
            self::AUTOMATIC_PROTOCOL_NUMBERING,
            $enabled ? '1' : '0'
        );
    }

    /**
     * Read a raw stored value, falling back when the key is absent.
     */
    private function value(string $key, ?string $default): ?string
    {
        $setting = ApplicationSetting::query()
            ->where('key', $key)
            ->first();

        if ($setting === null) {
            return $default;
        }

        return $setting->value;
    }

    /**
     * Persist one setting without creating duplicate keys.
     */
    private function store(string $key, ?string $value): void
    {
        ApplicationSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
