<?php

namespace src\database\queries;

use DateTime;
use src\database\objects\Where as WhereObject;

trait Where
{
    protected array $where = [];

    public function where(string $key, string $comparator, null|bool|int|float|string|array|DateTime $value, bool $escapeKey = true): static
    {
        $this->where[] = new WhereObject($key, $comparator, $value, $escapeKey);

        return $this;
    }

    public function and(): static
    {
        $this->where[] = 'AND';

        return $this;
    }

    public function or(): static
    {
        $this->where[] = 'OR';

        return $this;
    }
}
