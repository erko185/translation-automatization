<?php

namespace SampleProject;

class CyclicReturnPresenter
{
    public function renderDefault(): void
    {
        $this->first()->translate('cyclic.return.key');
    }

    private function first(): self
    {
        return $this->second();
    }

    private function second(): self
    {
        return $this->first();
    }

    public function translate(string $key): void
    {
    }
}
