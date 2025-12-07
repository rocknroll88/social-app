<?php

declare(strict_types=1);

namespace App\Traits;

trait HasShardKeyTrait
{
    public static function generateShardKey(string $seed): int
    {
        return unpack('N', hash('crc32b', $seed, true))[1];
    }
}
