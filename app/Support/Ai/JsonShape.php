<?php

namespace App\Support\Ai;

/**
 * JSON objects are unordered (RFC 8259) and MySQL JSON columns hand object keys back in their own order, so a value
 * read from the database must never be compared with one built in code by key order. canonical() gives both sides
 * one shape: object keys sorted recursively, lists kept in order, scalar types untouched (so "1" !== 1 still holds).
 */
final class JsonShape
{
    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map(self::canonical(...), $value);
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    /** Strict equality of two JSON values regardless of object key order. */
    public static function same(mixed $a, mixed $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }
}
