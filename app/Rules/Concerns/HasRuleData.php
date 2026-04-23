<?php

namespace App\Rules\Concerns;

trait HasRuleData
{
    // DataAwareRule から渡される入力全体。
    protected $data = [];

    public function setData(array $data): static
    {
        // バリデーション時に参照する入力データを保持する。
        $this->data = $data;

        return $this;
    }
}
