<?php

namespace SampleProject;

class InterproceduralPresenter
{
    public function renderDefault($translator): void
    {
        $this->notifyTranslatedState('published', $translator);
    }

    private function notifyTranslatedState(string $state, $translator): void
    {
        $translator->translate('interprocedural.' . $state);
    }
}
