<?php

namespace Database\Factories;

use App\Models\ArchiveFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchiveFolder>
 */
class ArchiveFolderFactory extends Factory
{
    /**
     * The model represented by this factory.
     *
     * @var class-string<ArchiveFolder>
     */
    protected $model = ArchiveFolder::class;

    /**
     * Create a normal selectable folder with numeric retention.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => 'Φ.'.$this->faker->unique()->numerify('###.###'),
            'description' => $this->faker->sentence(),
            'retention_years' => 5,
            'retention_rule' => null,
            'remarks' => null,
            'is_selectable' => true,
            'is_active' => true,
            'sort_order' => $this->faker->unique()->numberBetween(1, 1000000),
        ];
    }

    /**
     * Create a non-selectable heading used to group child folders.
     */
    public function category(): static
    {
        return $this->state(fn (array $attributes): array => [
            'retention_years' => null,
            'retention_rule' => null,
            'is_selectable' => false,
        ]);
    }

    /**
     * Create a folder retained for a specified number of years.
     */
    public function retainedForYears(int $years): static
    {
        return $this->state(fn (array $attributes): array => [
            'retention_years' => $years,
            'retention_rule' => null,
            'is_selectable' => true,
        ]);
    }

    /**
     * Create a folder governed by a textual retention rule.
     */
    public function withRetentionRule(string $rule = 'Διηνεκές'): static
    {
        return $this->state(fn (array $attributes): array => [
            'retention_years' => null,
            'retention_rule' => $rule,
            'is_selectable' => true,
        ]);
    }

    /**
     * Create a catalogue entry that is preserved but unavailable for new use.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
