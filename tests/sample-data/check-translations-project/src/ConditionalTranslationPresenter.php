<?php

namespace SampleProject;

class ConditionalTranslationPresenter
{
    public function renderDefault($translator): void
    {
        $this->createSettingInfoItem('', $translator);
        $this->createSettingInfoItem('conditional.real.key', $translator);
    }

    private function createSettingInfoItem(string $title, $translator): void
    {
        [
            $title === '' ? '' : $translator->translate($title),
        ];
    }
}
