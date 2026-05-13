<?php

namespace SampleProject;

class LocalPrefixPresenter
{
    public function renderDefault($translator): void
    {
        $this->createTitle($translator);
    }

    private function createTitle($translator): void
    {
        $prefix = 'local.prefix.';
        $translator->translate($prefix . 'title');
    }
}
