<?php

namespace src\database\querybuilders;

interface QueryBuilderInterface
{
    public function select(string $table, array $columns, array $whereConditions, array $whereValues, int $limit): array;

    public function insert(string $table, array $values): array;

    public function update(string $table, array $values, array $whereConditions, array $whereValues): array;

    public function delete(string $table, array $whereConditions, array $whereValues): array;
}

?>