<?php

namespace App\Rules\Concerns;

trait HasRuleData
{
    protected $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
