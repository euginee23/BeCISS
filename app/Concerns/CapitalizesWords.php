<?php

namespace App\Concerns;

trait CapitalizesWords
{
    /**
     * Upper-case the first letter of every word.
     *
     * Unlike Str::title(), the rest of each word is left untouched, so values
     * that are already correctly cased ("dela Cruz III", "McDonald") survive.
     */
    public static function capitalizeWords(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        return preg_replace_callback(
            '/(?<=^|[\s\-\'’.])\p{L}/u',
            static fn (array $matches): string => mb_strtoupper($matches[0]),
            $trimmed,
        );
    }
}
