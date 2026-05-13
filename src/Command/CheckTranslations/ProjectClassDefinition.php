<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node\Expr;

class ProjectClassDefinition
{
    public string $className;

    public ?string $parentClassName;

    /** @var array<string, ProjectMethodDefinition> */
    public array $methods = [];

    /** @var array<string, Expr> */
    public array $constantExpressions = [];

    /** @var array<string, ExpressionEvaluationResult> */
    public array $constantValues = [];

    public function __construct(string $className, ?string $parentClassName)
    {
        $this->className = $className;
        $this->parentClassName = $parentClassName;
    }
}
