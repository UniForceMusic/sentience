<?php

namespace src\requests;

use DateTime;
use ReflectionProperty;
use src\app\Request as StaticRequest;
use src\exceptions\PropertyException;
use src\util\Data;

abstract class Request
{
    public const HEADER = 'header:';
    public const PARAMETER = 'parameter:';
    public const VAR = 'var:';
    public const JSON = 'json:';
    public const FORMDATA = 'formdata:';

    protected StaticRequest $request;
    protected ?array $payload;
    protected array $properties = [];

    public function __construct(StaticRequest $request, ?array $payload = null)
    {
        $this->request = $request;
        $this->payload = is_null($payload)
            ? $request->getJson()
            : $payload;

        $this->hydrateProperties();
    }

    protected function hydrateProperties(): void
    {
        foreach ($this->properties as $property => $key) {
            if (str_starts_with($key, $this::HEADER)) {
                $this->hydratePropertyFromHeader($property, $key);
                continue;
            }

            if (str_starts_with($key, $this::PARAMETER)) {
                $this->hydratePropertyFromParameter($property, $key);
                continue;
            }

            if (str_starts_with($key, $this::VAR )) {
                $this->hydratePropertyFromVar($property, $key);
                continue;
            }

            if (str_starts_with($key, $this::JSON)) {
                $this->hydratePropertyFromJson($property, $key);
                continue;
            }

            if (str_starts_with($key, $this::FORMDATA)) {
                $this->hydratePropertyFromFormData($property, $key);
                continue;
            }

            throw new PropertyException(
                sprintf(
                    'key: %s does not contain type',
                    $key
                )
            );
        }
    }

    protected function hydratePropertyFromHeader(string $property, string $key): void
    {
        $key = $this->stripTypeFromKey($key, $this::HEADER);

        $reflectionProperty = new ReflectionProperty($this, $property);

        $reflectionType = $reflectionProperty->getType();
        $type = ltrim($reflectionType, '?');
        $allowsNull = $reflectionType->allowsNull();

        if (!in_array($type, ['string', 'array'])) {
            throw new PropertyException(
                sprintf(
                    'property: %s must be of type string, ?string, array or ?array',
                    $property
                )
            );
        }

        $header = $this->request->getHeader($key);

        if (in_array($type, ['mixed', ''])) {
            $this->{$property} = $header;
            return;
        }

        if (is_null($header)) {
            if ($allowsNull) {
                $this->{$property} = null;
                return;
            }

            if ($type == 'array') {
                $this->{$property} = [];
                return;
            }

            $this->{$property} = '';
            return;
        }

        if ($type == 'array') {
            $this->{$property} = explode(', ', $header);
            return;
        }

        $this->{$property} = $this->castToType($header, $type);
    }

    protected function hydratePropertyFromParameter(string $property, string $key): void
    {
        $key = $this->stripTypeFromKey($key, $this::PARAMETER);

        $reflectionProperty = new ReflectionProperty($this, $property);

        $reflectionType = $reflectionProperty->getType();
        $type = ltrim($reflectionType, '?');
        $allowsNull = $reflectionType->allowsNull();

        if (!in_array($type, ['string', 'array'])) {
            throw new PropertyException(
                sprintf(
                    'property: %s must be of type string, ?string, array or ?array',
                    $property
                )
            );
        }

        $parameter = $this->request->getParameter($key);

        if (in_array($type, ['mixed', ''])) {
            $this->{$property} = $parameter;
            return;
        }

        if (is_null($parameter)) {
            if ($allowsNull) {
                $this->{$property} = null;
                return;
            }

            if ($type == 'array') {
                $this->{$property} = [];
                return;
            }

            $this->{$property} = '';
            return;
        }

        if ($type == 'array' && gettype($parameter) == 'string') {
            $this->{$property} = [$parameter];
            return;
        }

        if ($type == 'string' && gettype($parameter) == 'array') {
            $this->{$property} = end($parameter);
            return;
        }

        $this->{$property} = $this->castToType($parameter, $type);
    }

    protected function hydratePropertyFromVar(string $property, string $key): void
    {
        $key = $this->stripTypeFromKey($key, $this::VAR );

        $reflectionProperty = new ReflectionProperty($this, $property);

        $reflectionType = $reflectionProperty->getType();
        $type = ltrim($reflectionType, '?');
        $allowsNull = $reflectionType->allowsNull();

        if (!in_array($type, ['string'])) {
            throw new PropertyException(
                sprintf(
                    'property: %s must be of type string or ?string',
                    $property
                )
            );
        }

        $var = $this->request->getVar($key);

        if (in_array($type, ['mixed', ''])) {
            $this->{$property} = $var;
            return;
        }

        if (is_null($var)) {
            if ($allowsNull) {
                $this->{$property} = null;
                return;
            }

            $this->{$property} = '';
            return;
        }

        $this->{$property} = $this->castToType($var, $type);
    }

    protected function hydratePropertyFromJson(string $property, string $key): void
    {
        $key = $this->stripTypeFromKey($key, $this::JSON);

        $reflectionProperty = new ReflectionProperty($this, $property);

        $reflectionType = $reflectionProperty->getType();
        $type = ltrim($reflectionType, '?');
        $allowsNull = $reflectionType->allowsNull();
        $docComment = $reflectionProperty->getDocComment();

        if (is_null($this->payload)) {
            throw new PropertyException(
                sprintf(
                    'unable to parse property: %s because json body is empty',
                    $property
                )
            );
        }

        $data = Data::get($this->payload, $key);

        if (is_null($data) && $allowsNull) {
            $this->{$property} = null;
            return;
        }

        if (in_array($type, ['mixed', ''])) {
            $this->{$property} = $data;
            return;
        }

        if ($type == 'array' && $docComment) {
            $arrayType = $this->getArrayType($property, $docComment);
            $this->{$property} = array_map(
                function ($value) use ($arrayType) {
                    return new $arrayType($this->request, $value);
                },
                $data
            );
            return;
        }

        if ($type == 'object') {
            $this->{$property} = (object) $data;
            return;
        }

        if ($type == 'DateTime') {
            $format = $this->getDatetimeFormat($property, $docComment);
            $dateTime = DateTime::createFromFormat($format, $data);
            if (!$dateTime) {
                throw new PropertyException(
                    sprintf(
                        'unable to parse property: %s because format "%s" does not match "%s"',
                        $property,
                        $format,
                        $data
                    )
                );
            }
            $this->{$property} = $dateTime;
            return;
        }

        if (str_contains($type, __NAMESPACE__)) {
            $this->{$property} = new $type($this->request, $data);
            return;
        }

        if (!$this->isTypeMatch($type, gettype($data))) {
            throw new PropertyException(
                sprintf(
                    'error reading key: %s. found %s while expecting %s',
                    $key,
                    strtolower(gettype($data)),
                    strtolower($type)
                )
            );
        }

        $this->{$property} = $data;
    }

    protected function hydratePropertyFromFormData(string $property, string $key): void
    {
        $key = $this->stripTypeFromKey($key, $this::FORMDATA);

        $reflectionProperty = new ReflectionProperty($this, $property);

        $reflectionType = $reflectionProperty->getType();
        $type = ltrim($reflectionType, '?');
        $allowsNull = $reflectionType->allowsNull();

        $formData = $this->request->getFormData();

        if (is_null($formData)) {
            throw new PropertyException(
                sprintf(
                    'unable to parse property: %s because formdata body is empty',
                    $property
                )
            );
        }

        $data = $formData[$key] ?? null;

        if (in_array($type, ['mixed', ''])) {
            $this->{$property} = $data;
            return;
        }

        if (is_null($data)) {
            if ($allowsNull) {
                $this->{$property} = null;
                return;
            }

            $this->{$property} = '';
            return;
        }

        $this->{$property} = $this->castToType($data, $type);
    }

    protected function getArrayType(string $property, string $docComment): string
    {
        $match = preg_match('/\@var (.*)\[\]/', $docComment, $matches);
        if (!$match) {
            throw new PropertyException(sprintf('unable to determine type of array for property: %s', $property));
        }

        return sprintf('%s\\%s', __NAMESPACE__, $matches[1]);
    }

    protected function getDatetimeFormat(string $property, string $docComment): string
    {
        $match = preg_match('/\@template (.*)\s/', $docComment, $matches);
        if (!$match) {
            throw new PropertyException(sprintf('unable to determine DateTime format for property: %s', $property));
        }

        return trim($matches[1]);
    }

    protected function isTypeMatch(string $reflectionType, string $getType): bool
    {
        return str_contains(
            strtolower($getType),
            strtolower(
                ltrim($reflectionType, '?')
            )
        );
    }

    protected function stripTypeFromKey(string $key, string $type): string
    {
        return substr(
            $key,
            strlen($type)
        );
    }

    protected function castToType(string $value, string $type): mixed
    {
        if ($type == 'string') {
            return $value;
        }

        if ($type == 'int') {
            return (int) trim($value);
        }

        if ($type == 'float') {
            return (float) trim($value);
        }

        if ($type == 'bool') {
            $lcValue = trim(strtolower($value));
            if ($lcValue == 'true') {
                return true;
            }

            if ($lcValue == 'false') {
                return false;
            }

            if ($lcValue == '1') {
                return true;
            }

            if ($lcValue == '0') {
                return false;
            }
        }

        return (string) $value;
    }
}
