<?php

namespace App\Support;

/**
 * Tiny helper that returns a random Ghanaian-flavoured greeting for the
 * dashboard hero banners (rep + student). Picked per-render so every
 * refresh / re-login / re-navigate gives a different feel without any
 * per-user state to track.
 *
 * Centralising the list here keeps the rep and student views in sync —
 * adding a new greeting only needs an edit to {@see self::GREETINGS}.
 */
final class Greeting
{
    /**
     * Ghanaian-vibe greetings. Order is irrelevant — picked uniformly at
     * random. Mix of formal ("Hello!") and casual ("Yo!", "Wossup!") so
     * each refresh feels different but never out of place.
     */
    public const GREETINGS = [
        'Yo!',
        'Asey!',
        'Wossup!',
        'Hello!',
        'Yello',
    ];

    /**
     * Cryptographically uniform pick (array_rand is fine — no security
     * sensitivity here, but mt_rand-grade entropy is more than enough
     * for a UI greeting).
     */
    public static function random(): string
    {
        $list = self::GREETINGS;

        return $list[array_rand($list)];
    }
}
