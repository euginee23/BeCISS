<?php

namespace App\Concerns;

/**
 * Quick Today / This week / This month / This year ranges for report pages
 * that filter on public `$from` and `$to` Y-m-d properties.
 */
trait HasDateRangePresets
{
    /**
     * @return array<string, string>
     */
    public function rangePresets(): array
    {
        return [
            'today' => __('Today'),
            'week' => __('This week'),
            'month' => __('This month'),
            'year' => __('This year'),
        ];
    }

    public function setRange(string $range): void
    {
        [$this->from, $this->to] = $this->presetDates($range);
    }

    /**
     * The preset the current From/To dates match, if any.
     */
    public function activeRange(): ?string
    {
        foreach (array_keys($this->rangePresets()) as $range) {
            if ($this->presetDates($range) === [$this->from, $this->to]) {
                return $range;
            }
        }

        return null;
    }

    /**
     * Each preset runs from the start of its period up to today.
     *
     * @return array{0: string, 1: string}
     */
    private function presetDates(string $range): array
    {
        $start = match ($range) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now(),
        };

        return [$start->toDateString(), now()->toDateString()];
    }
}
