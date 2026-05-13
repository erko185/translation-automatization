<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node\Expr;

class MethodTranslationGuard
{
    public Expr $expression;

    public bool $expectedValue;

    public function __construct(Expr $expression, bool $expectedValue)
    {
        $this->expression = $expression;
        $this->expectedValue = $expectedValue;
    }
}
