<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckDictionaries;

class CheckDictionariesConfig
{
    /** @var array<string, <string, string>> */
    private $dictionaries;

    public function __construct(array $dictionaries)
    {
        $this->dictionaries = $dictionaries;
    }

    /**
     * @return array<string, <string, string>> [language => [key => translation]]
     */
    public function load(): array
    {
        return $this->dictionaries;
    }
}
