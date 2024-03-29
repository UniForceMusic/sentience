<?php

namespace src\util;

use src\app\Request;

class Headers
{
    public const CACHE_PUBLIC = 'public';
    public const CACHE_PRIVATE = 'private';
    public const CACHE_NO_CACHE = 'no-cache';
    public const CACHE_NO_STORE = 'no-store';

    public static function serveFile(): void
    {
        header('Content-Transfer-Encoding: binary');
    }

    public static function contentLength(string $content): void
    {
        header(sprintf('Content-Length: %s', strlen($content)));
    }

    public static function cacheControl(string $type, int $maxAge = 0): void
    {
        header(sprintf('Cache-Control: %s, max-age=%s', $type, $maxAge));
    }

    public static function cors(Request $request, $returnOrigin = false): void
    {
        $originHeader = $request->getHeader('origin');
        $originEnv = Strings::join(', ', env('ACCESS_CONTROL_ALLOW_ORIGIN', '*'));

        $origin = ($returnOrigin && $originHeader)
            ? $originHeader
            : $originEnv;

        header(sprintf('Access-Control-Allow-Origin: %s', $origin));
        header(sprintf('Access-Control-Allow-Credentials: %s', (env('ACCESS_CONTROL_ALLOW_CREDENTIALS', true) ? 'true' : 'false')));
        header(sprintf('Access-Control-Allow-Methods: %s', Strings::join(', ', env('ACCESS_CONTROL_ALLOW_METHODS', '*'))));
        header(sprintf('Access-Control-Allow-Headers: %s', Strings::join(', ', env('ACCESS_CONTROL_ALLOW_HEADERS', '*'))));
    }
}
