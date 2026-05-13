<?php

namespace SampleProject;

final class PropertyFetchLabel
{
    public function __construct(
        public readonly string $title,
    ) {
    }
}

class PropertyFetchPresenter
{
    public function renderDefault($translator): void
    {
        $label = new PropertyFetchLabel('property.fetch.title');

        $translator->translate($label->title);
    }
}
