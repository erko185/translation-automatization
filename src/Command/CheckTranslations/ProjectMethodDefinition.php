<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node;

class ProjectMethodDefinition
{
    public string $className;

    public string $methodName;

    /** @var string[] */
    public array $parameterNames;

    /** @var array<string, string> */
    public array $parameterTypes;

    /** @var Node\Stmt[] */
    public array $statements;

    public ?string $returnType;

    /**
     * @param string[] $parameterNames
     * @param array<string, string> $parameterTypes
     * @param Node\Stmt[] $statements
     */
    public function __construct(string $className, string $methodName, array $parameterNames, array $parameterTypes, array $statements, ?string $returnType = null)
    {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->parameterNames = $parameterNames;
        $this->parameterTypes = $parameterTypes;
        $this->statements = $statements;
        $this->returnType = $returnType;
    }
}
