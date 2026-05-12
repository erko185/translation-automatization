<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

class TranslationCallMatch
{
    public string $call;

    public int|string $argSelector;

    public string $context;

    public function __construct(string $call, int|string $argSelector, string $context)
    {
        $this->call = $call;
        $this->argSelector = $argSelector;
        $this->context = $context;
    }
}
