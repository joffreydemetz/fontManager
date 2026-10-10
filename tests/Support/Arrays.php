<?php

namespace JDZ\FontManager\Tests\Support;

/**
 * Exact assertSame() targets for the stdClass trees the providers return.
 */
final class Arrays
{
    /** Objects become arrays, recursively; keys, key order and scalar types are kept */
    public static function export(mixed $value): mixed
    {
        if (\is_object($value)) {
            $value = get_object_vars($value);
        }

        if (\is_array($value)) {
            return array_map(self::export(...), $value);
        }

        return $value;
    }
}
