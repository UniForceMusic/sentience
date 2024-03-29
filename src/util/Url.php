<?php

namespace src\util;

class Url
{
    public static function getBaseUrl(): string
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . sprintf('://%s', $_SERVER['HTTP_HOST']);
    }

    public static function getPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'];

        if (str_contains($requestUri, '?')) {
            return trim(
                strstr(
                    $requestUri,
                    '?',
                    true
                ),
                '/'
            );
        }

        return trim($requestUri, '/');
    }

    public static function getQueryString(): ?string
    {
        $requestUri = $_SERVER['REQUEST_URI'];

        if (str_contains($requestUri, '?')) {
            return trim(
                strstr(
                    $requestUri,
                    '?',
                    false
                ),
                '/'
            );
        }

        return null;
    }

    public static function getRequestUrl(): string
    {
        return sprintf('%s/%s', static::getBaseUrl(), static::getPath());
    }

    public static function getIndexUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = static::getRequestUrl();
        $basePath = BASEDIR;

        $urlSplit = explode('/', trim($baseUrl, '/'));
        $pathSplit = explode(DIRECTORY_SEPARATOR, trim($basePath, '/\\'));

        $indexUrlParts = [static::getBaseUrl()];

        foreach ($urlSplit as $urlPart) {
            foreach ($pathSplit as $pathPart) {
                /**
                 * Prevent the index url matching the www directory in hosts like Plesk
                 */
                if ($pathPart == $host) {
                    continue;
                }

                $partsMatch = (($urlPart == $pathPart) && !empty($urlPart));

                if ($partsMatch) {
                    $indexUrlParts[] = $urlPart;
                }
            }
        }

        return implode('/', $indexUrlParts);
    }

    public static function getRequestUri(): string
    {
        return trim(
            str_replace(
                static::getIndexUrl(),
                '',
                static::getRequestUrl()
            ),
            '/'
        );
    }

    public static function urlEncodeParametersFromAssoc(array $parameters): string
    {
        return FormData::encode($parameters, false);
    }

    public static function urlDecodeParametersToAssoc(?string $string): array
    {
        if (!$string) {
            return [];
        }

        $string = Strings::beforeSubstr($string, '#');
        $string = Strings::afterSubstr($string, '?');

        return FormData::decode($string);
    }

    public static function urlDecodeParameters(?string $string): array
    {
        if (!$string) {
            return [];
        }

        $string = Strings::beforeSubstr($string, '#');
        $string = Strings::afterSubstr($string, '?');

        return FormData::decode($string, false);
    }
}
