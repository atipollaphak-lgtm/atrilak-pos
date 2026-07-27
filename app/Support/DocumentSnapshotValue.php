<?php

namespace App\Support;

final class DocumentSnapshotValue
{
    public static function resolve(
        mixed $snapshot,
        mixed $liveValue = null,
        mixed $fallback = '-'
    ): mixed {
        if ($snapshot !== null) {
            return $snapshot;
        }

        return $liveValue !== null ? $liveValue : $fallback;
    }
}
