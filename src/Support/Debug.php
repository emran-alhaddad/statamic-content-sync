<?php

namespace EmranAlhaddad\ContentSync\Support;

class Debug
{
    public static function fmt(mixed $v): string
    {
        return is_scalar($v) || $v === null ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    public static function idPath(mixed $collection, mixed $site, mixed $slug): string
    {
        return self::fmt($collection) . '/' . self::fmt($site) . '/' . self::fmt($slug);
    }
}
