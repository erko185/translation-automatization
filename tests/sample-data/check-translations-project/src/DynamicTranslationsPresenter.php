<?php

namespace SampleProject;

class DynamicTranslationsPresenter
{
    private const MENU_TITLE = 'program.template.title';

    public function renderDefault($translator): void
    {
        $section = 'summary';
        $prefix = 'checkout.';
        $translator->translate($prefix . $section . '.title');

        $template = 'checkout.%s.label';
        $translator->translate(sprintf($template, 'payment'));

        $latteKey = 'unused';
        $translator->translate($latteKey);
        $translator->translate(self::MENU_TITLE);

        $translator->translate($unknownPrefix . '.label');
    }
}
