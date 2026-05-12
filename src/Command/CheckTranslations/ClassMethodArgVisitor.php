<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckFormKeys;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;
use Efabrica\TranslationsAutomatization\Command\CheckTranslations\ExpressionEvaluationResult;
use Efabrica\TranslationsAutomatization\Command\CheckTranslations\TranslationCallMatch;
use Efabrica\TranslationsAutomatization\Command\CheckTranslations\TranslationCallMatcher;
use Efabrica\TranslationsAutomatization\Command\CheckTranslations\TranslationKeyExpressionResolver;
use PhpParser\Node\FunctionLike;
use PhpParser\PrettyPrinter\Standard;

class ClassMethodArgVisitor extends NodeVisitorAbstract
{
    private $keys = [];

    private $filePath;

    private $className;

    /** @var array<int, array<string, ExpressionEvaluationResult>> */
    private array $variableScopes = [[]];

    private TranslationCallMatcher $translationCallMatcher;

    private TranslationKeyExpressionResolver $expressionResolver;

    private Standard $prettyPrinter;

    /** @var array<string, ExpressionEvaluationResult> */
    private array $classConstants = [];

    public function __construct(
        array &$keys,
        string $filePath,
        ?TranslationKeyExpressionResolver $expressionResolver = null,
        ?TranslationCallMatcher $translationCallMatcher = null
    )
    {
        $this->keys = &$keys;
        $this->filePath = $filePath;
        $this->className = (string)pathinfo($filePath, PATHINFO_FILENAME);
        $this->expressionResolver = $expressionResolver ?? new TranslationKeyExpressionResolver();
        $this->translationCallMatcher = $translationCallMatcher ?? new TranslationCallMatcher();
        $this->prettyPrinter = new Standard();
    }

    public function enterNode(Node $node)
    {
        $this->enterScope($node);
        $this->translationCallMatcher->collectUse($node);
        $this->collectClassConstants($node);
        $this->collectVariableAssignment($node);
        if ($node instanceof New_ && ($match = $this->translationCallMatcher->matchConstructor($node)) !== null) {
            $args = $node->args;
            if (isset($args[$match->argSelector]) && $args[$match->argSelector]->value instanceof String_) {
                $key = $args[$match->argSelector]->value->value;
                $this->addKey($args[$match->argSelector]->getStartLine(), $match->call, [$key]);
            }
        }
        if ($node instanceof MethodCall) {
            foreach ($this->translationCallMatcher->matchMethodCall($node, $this->className) as $match) {
                $this->extractKeyFromArgument($node, $match);
            }
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof FunctionLike) {
            array_pop($this->variableScopes);
        }
    }

    private function extractKeyFromArgument(MethodCall $node, TranslationCallMatch $match): void
    {
        $args = $node->args;
        $selectedArg = $this->findArgumentBySelector($args, $match->argSelector);

        if ($selectedArg === null) {
            return;
        }

        // find in funciton return array values
        if ($selectedArg->value instanceof Closure &&
            isset($selectedArg->value) && isset($selectedArg->value)
        ) {
            $method = $node->name->name;
            $closure = $selectedArg->value;
            if ($closure->stmts !== null) {
                $return = reset($closure->stmts);
                if ($return instanceof Return_ && $return->expr instanceof Array_ && $return->expr->items !== null) {
                    $items = $return->expr->items;
                    foreach ($items as $item) {
                        $itemResult = $this->getExpressionResolver()->resolve($item->value, $this->getCurrentScope());
                        if ($itemResult->isResolved()) {
                            foreach ($itemResult->getValues() as $key) {
                                $this->addKey($item->value->getAttribute('startLine'), $method, [$key], null, $itemResult->isDynamic(), true, $this->printExpression($item->value), $itemResult->getStrategies(), $itemResult->getVariablesUsed());
                            }
                        } else {
                            $this->addKey($item->value->getAttribute('startLine'), $method, [], null, true, false, $this->printExpression($item->value), $itemResult->getStrategies(), $itemResult->getVariablesUsed());
                        }
                    }
                }
            }

            return;
        }

        if ($selectedArg->value instanceof ArrowFunction) {
            $this->extractKeysFromArrowFunction($node, $selectedArg->value);
            return;
        }

        $method = $node->name->name;
        $arg = null;
        if (is_int($match->argSelector) && $method === 'translate' && isset($args[$match->argSelector + 1]) && $args[$match->argSelector + 1]->value instanceof Node\Expr\Array_) {
            $arg = $args[$match->argSelector + 1]->value->items[0]->key->value;
        }

        $result = $this->getExpressionResolver()->resolve($selectedArg->value, $this->getCurrentScope());
        if (!$result->isResolved()) {
            $this->addKey($selectedArg->getStartLine(), $method, [], $arg, true, false, $this->printExpression($selectedArg->value), $result->getStrategies(), $result->getVariablesUsed());
            return;
        }

        foreach ($result->getValues() as $key) {
            if ($this->translationCallMatcher->allowsEmptyTranslation($match->context, $match->argSelector, $method, $key)) {
                continue;
            }

            $this->addKey($selectedArg->getStartLine(), $method, [$key], $arg, $result->isDynamic(), true, $this->printExpression($selectedArg->value), $result->getStrategies(), $result->getVariablesUsed());
        }
    }

    private function findArgumentBySelector(array $args, int|string $argSelector): ?Node\Arg
    {
        if (is_int($argSelector)) {
            return $args[$argSelector] ?? null;
        }

        foreach ($args as $arg) {
            if ($arg->name?->toString() === $argSelector) {
                return $arg;
            }
        }

        return null;
    }

    private function addKey(int $line, string $call, array $resolvedKeys, ?string $arg = null, bool $isDynamic = false, bool $isResolved = true, ?string $sourceExpression = null, array $resolutionStrategies = [], array $variablesUsed = []): void
    {
        $this->keys[] = [
            'file' => $this->filePath,
            'line' => $line,
            'call' => $call,
            'resolvedKeys' => $resolvedKeys,
            'arg' => $arg,
            'isDynamic' => $isDynamic,
            'isResolved' => $isResolved,
            'sourceExpression' => $sourceExpression,
            'resolutionStrategies' => $resolutionStrategies,
            'variablesUsed' => $variablesUsed,
        ];
    }

    private function enterScope(Node $node): void
    {
        if ($node instanceof Closure) {
            $scope = [];
            foreach ($node->uses as $use) {
                if ($use instanceof ClosureUse && is_string($use->var->name)) {
                    $currentScope = $this->getCurrentScope();
                    if (isset($currentScope[$use->var->name])) {
                        $scope[$use->var->name] = $currentScope[$use->var->name];
                    }
                }
            }

            $this->variableScopes[] = $scope;
            return;
        }

        if ($node instanceof ClassMethod || $node instanceof Function_) {
            $this->variableScopes[] = [];
            return;
        }

        if ($node instanceof FunctionLike) {
            $this->variableScopes[] = $this->getCurrentScope();
        }
    }

    private function collectVariableAssignment(Node $node): void
    {
        if (!$node instanceof Assign || !$node->var instanceof Node\Expr\Variable || !is_string($node->var->name)) {
            return;
        }

        $this->variableScopes[array_key_last($this->variableScopes)][$node->var->name] = $this->getExpressionResolver()->resolve(
            $node->expr,
            $this->getCurrentScope()
        );
    }

    private function collectClassConstants(Node $node): void
    {
        if (!$node instanceof ClassConst) {
            return;
        }

        foreach ($node->consts as $const) {
            $this->classConstants[$const->name->toString()] = $this->expressionResolver->withClassConstants($this->classConstants)->resolve(
                $const->value,
                $this->getCurrentScope()
            );
        }
    }

    /**
     * @return array<string, ExpressionEvaluationResult>
     */
    private function getCurrentScope(): array
    {
        return $this->variableScopes[array_key_last($this->variableScopes)];
    }

    private function printExpression(Node $expression): string
    {
        return $this->prettyPrinter->prettyPrintExpr($expression);
    }

    private function getExpressionResolver(): TranslationKeyExpressionResolver
    {
        return $this->expressionResolver->withClassConstants($this->classConstants);
    }

    private function extractKeysFromArrowFunction(MethodCall $node, ArrowFunction $arrowFunction): void
    {
        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : 'unknown';
        $result = $this->getExpressionResolver()->resolve($arrowFunction->expr, $this->getCurrentScope());

        if (!$result->isResolved()) {
            $this->addKey($arrowFunction->getStartLine(), $method, [], null, true, false, $this->printExpression($arrowFunction), $result->getStrategies(), $result->getVariablesUsed());
            return;
        }

        foreach ($result->getValues() as $key) {
            $this->addKey($arrowFunction->getStartLine(), $method, [$key], null, $result->isDynamic(), true, $this->printExpression($arrowFunction), $result->getStrategies(), $result->getVariablesUsed());
        }
    }
}
