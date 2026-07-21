<?php

namespace App\Application\Services;

use JsonException;

class CanonicalJson
{
    /**
     * @throws JsonException
     */
    public function encode(mixed $value): string
    {
        return json_encode(
            $this->normalize($value),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->normalize(...), $value);
        }

        ksort($value, SORT_STRING);

        return array_map($this->normalize(...), $value);
    }
}
