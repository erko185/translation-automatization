<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node\Expr;

class MethodTranslationCandidate
{
    public Expr $expression;

    public ?string $pluralKey;

    public string $call;

    public ?string $declaringClassName;

    /** @var MethodTranslationGuard[] */
    public array $guards;

    /**
     * @param MethodTranslationGuard[] $guards
     */
    public function __construct(Expr $expression, string $call, ?string $pluralKey = null, array $guards = [], ?string $declaringClassName = null)
    {
        $this->expression = $expression;
        $this->pluralKey = $pluralKey;
        $this->call = $call;
        $this->guards = $guards;
        $this->declaringClassName = $declaringClassName;
    }
}
