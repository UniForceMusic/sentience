<?php

namespace src\database\queries;

trait PrimaryKey
{
    protected string $primaryKey = '';
    protected bool $primaryKeyAutoGenerated = true;

    public function primaryKey(string $primaryKey): static
    {
        $this->primaryKey = $primaryKey;

        return $this;
    }

    public function primaryKeyAutoGenerated(bool $primaryKeyAutoGenerated): static
    {
        $this->primaryKeyAutoGenerated = $primaryKeyAutoGenerated;

        return $this;
    }
}

?>