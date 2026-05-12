<?php

namespace SampleProject;

class SampleConditionPlugin
{
    private const CONDITION_CLASS_OPTIONS = [
        'condition-a' => 'feed.condition.option.a',
        'condition-b' => 'feed.condition.option.b',
    ];

    public function build($plugin): void
    {
        $plugin->dropdown('condition', static fn() => self::CONDITION_CLASS_OPTIONS);
    }
}
