<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

class TranslationKeyExpressionResolver
{
    private int $combinationLimit;

    /** @var array<string, ExpressionEvaluationResult> */
    private array $classConstants = [];

    public function __construct(int $combinationLimit = 25)
    {
        $this->combinationLimit = $combinationLimit;
    }

    /**
     * @param array<string, ExpressionEvaluationResult> $classConstants
     */
    public function withClassConstants(array $classConstants): self
    {
        $clone = clone $this;
        $clone->classConstants = $classConstants;

        return $clone;
    }

    /**
     * @param array<string, ExpressionEvaluationResult> $scope
     */
    public function resolve(Expr $expression, array $scope = []): ExpressionEvaluationResult
    {
        if ($expression instanceof Node\Scalar\String_) {
            return ExpressionEvaluationResult::resolved([$expression->value], false, ['string_literal']);
        }

        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            if (!isset($scope[$expression->name])) {
                return ExpressionEvaluationResult::unresolved(true, ['unknown_variable'], ['$' . $expression->name]);
            }

            return $scope[$expression->name]->asDynamic()
                ->withStrategy('variable_lookup')
                ->withVariable('$' . $expression->name);
        }

        if ($expression instanceof Expr\ClassConstFetch) {
            return $this->resolveClassConstFetch($expression);
        }

        if ($expression instanceof Expr\Array_) {
            return $this->resolveArrayExpression($expression, $scope);
        }

        if ($expression instanceof Expr\BinaryOp\Concat) {
            return $this->combine(
                $this->resolve($expression->left, $scope),
                $this->resolve($expression->right, $scope),
                true
            );
        }

        if ($expression instanceof Node\Scalar\Encapsed) {
            $result = ExpressionEvaluationResult::resolved(['']);
            foreach ($expression->parts as $part) {
                if ($part instanceof Node\Scalar\EncapsedStringPart) {
                    $partResult = ExpressionEvaluationResult::resolved([$part->value], true, ['string_interpolation']);
                } elseif ($part instanceof Expr) {
                    $partResult = $this->resolve($part, $scope)->asDynamic();
                } else {
                    return ExpressionEvaluationResult::unresolved(true, ['string_interpolation']);
                }

                $result = $this->combine($result, $partResult, true);
                if (!$result->isResolved()) {
                    return $result;
                }
            }

            return $result->asDynamic()->withStrategy('string_interpolation');
        }

        if ($expression instanceof Expr\Ternary) {
            $if = $expression->if !== null ? $this->resolve($expression->if, $scope) : $this->resolve($expression->cond, $scope);
            $else = $this->resolve($expression->else, $scope);

            return $this->union([$if, $else], true);
        }

        if ($expression instanceof Expr\BinaryOp\Coalesce) {
            return $this->union([
                $this->resolve($expression->left, $scope),
                $this->resolve($expression->right, $scope),
            ], true);
        }

        if ($expression instanceof Expr\FuncCall) {
            return $this->resolveFunctionCall($expression, $scope);
        }

        if ($expression instanceof Expr\MethodCall) {
            return $this->resolveMethodCall($expression, $scope);
        }

        if ($expression instanceof Expr\StaticCall) {
            return $this->resolveStaticCall($expression, $scope);
        }

        return ExpressionEvaluationResult::unresolved(true, ['unsupported_expression']);
    }

    private function resolveClassConstFetch(Expr\ClassConstFetch $expression): ExpressionEvaluationResult
    {
        if (!$expression->name instanceof Node\Identifier) {
            return ExpressionEvaluationResult::unresolved(true, ['unknown_class_constant']);
        }

        $constantName = $expression->name->toString();
        if (!isset($this->classConstants[$constantName])) {
            return ExpressionEvaluationResult::unresolved(true, ['unknown_class_constant:' . $constantName]);
        }

        return $this->classConstants[$constantName]
            ->asDynamic()
            ->withStrategy('class_constant_fetch')
            ->withVariable('self::' . $constantName);
    }

    /**
     * @param array<string, ExpressionEvaluationResult> $scope
     */
    private function resolveArrayExpression(Expr\Array_ $expression, array $scope): ExpressionEvaluationResult
    {
        if ($expression->items === null) {
            return ExpressionEvaluationResult::resolved([], true, ['array_literal']);
        }

        $values = [];
        $strategies = ['array_literal'];
        $variablesUsed = [];
        foreach ($expression->items as $item) {
            if ($item === null) {
                continue;
            }

            $itemResult = $this->resolve($item->value, $scope);
            if (!$itemResult->isResolved()) {
                return ExpressionEvaluationResult::unresolved(
                    true,
                    array_merge($strategies, $itemResult->getStrategies()),
                    array_merge($variablesUsed, $itemResult->getVariablesUsed())
                );
            }

            $values = array_merge($values, $itemResult->getValues());
            $strategies = array_merge($strategies, $itemResult->getStrategies());
            $variablesUsed = array_merge($variablesUsed, $itemResult->getVariablesUsed());
            if (count($values) > $this->combinationLimit) {
                return ExpressionEvaluationResult::unresolved(
                    true,
                    array_merge($strategies, ['combination_limit_exceeded']),
                    $variablesUsed
                );
            }
        }

        return ExpressionEvaluationResult::resolved($values, true, $strategies, $variablesUsed);
    }

    /**
     * @param array<string, ExpressionEvaluationResult> $scope
     */
    private function resolveFunctionCall(Expr\FuncCall $expression, array $scope): ExpressionEvaluationResult
    {
        if (!$expression->name instanceof Name) {
            return ExpressionEvaluationResult::unresolved(true, ['unknown_function']);
        }

        $functionName = strtolower($expression->name->toString());
        if ($functionName !== 'sprintf') {
            return ExpressionEvaluationResult::unresolved(true, ['unsupported_function:' . $functionName]);
        }

        $arguments = $expression->args;
        if ($arguments === [] || !isset($arguments[0])) {
            return ExpressionEvaluationResult::unresolved(true, ['sprintf']);
        }

        $format = $this->resolve($arguments[0]->value, $scope);
        if (!$format->isResolved() || count($format->getValues()) !== 1) {
            return ExpressionEvaluationResult::unresolved(true, ['sprintf'], $format->getVariablesUsed());
        }

        $resolvedArguments = [];
        foreach (array_slice($arguments, 1) as $argument) {
            $resolvedArgument = $this->resolve($argument->value, $scope);
            if (!$resolvedArgument->isResolved() || count($resolvedArgument->getValues()) !== 1) {
                return ExpressionEvaluationResult::unresolved(true, ['sprintf'], $resolvedArgument->getVariablesUsed());
            }

            $resolvedArguments[] = $resolvedArgument->getValues()[0];
        }

        $formatted = @sprintf($format->getValues()[0], ...$resolvedArguments);
        if (!is_string($formatted)) {
            return ExpressionEvaluationResult::unresolved(true, ['sprintf']);
        }

        return ExpressionEvaluationResult::resolved([$formatted], true, ['sprintf'], $format->getVariablesUsed());
    }

    /**
     * @param array<string, ExpressionEvaluationResult> $scope
     */
    private function resolveMethodCall(Expr\MethodCall $expression, array $scope): ExpressionEvaluationResult
    {
        if (!$expression->name instanceof Node\Identifier) {
            return ExpressionEvaluationResult::unresolved(true, ['unknown_method_call']);
        }

        $methodName = strtolower($expression->name->toString());
        if (!in_array($methodName, ['translate', 'trans'], true)) {
            return ExpressionEvaluationResult::unresolved(true, ['unsupported_method_call:' . $methodName]);
        }

        return $this->resolveTranslateLikeArguments($expression->args, $scope, 'nested_' . $methodName . '_method_call');
    }

    /**
     * @param array<string, ExpressionEvaluationResult> $scope
     */
    private function resolveStaticCall(Expr\StaticCall $expression, array $scope): ExpressionEvaluationResult
    {
        if (!$expression->name instanceof Node\Identifier) {
            return ExpressionEvaluationResult::unresolved(true, ['unknown_static_call']);
        }

        $methodName = strtolower($expression->name->toString());
        if (!in_array($methodName, ['translate', 'trans'], true)) {
            return ExpressionEvaluationResult::unresolved(true, ['unsupported_static_call:' . $methodName]);
        }

        return $this->resolveTranslateLikeArguments($expression->args, $scope, 'nested_' . $methodName . '_static_call');
    }

    /**
     * @param array<int, Node\Arg> $arguments
     * @param array<string, ExpressionEvaluationResult> $scope
     */
    private function resolveTranslateLikeArguments(array $arguments, array $scope, string $strategy): ExpressionEvaluationResult
    {
        if (!isset($arguments[0])) {
            return ExpressionEvaluationResult::unresolved(true, [$strategy]);
        }

        return $this->resolve($arguments[0]->value, $scope)->asDynamic()->withStrategy($strategy);
    }

    private function combine(ExpressionEvaluationResult $left, ExpressionEvaluationResult $right, bool $dynamic): ExpressionEvaluationResult
    {
        if (!$left->isResolved() || !$right->isResolved()) {
            return ExpressionEvaluationResult::unresolved(
                $dynamic,
                array_merge($left->getStrategies(), $right->getStrategies(), ['concat']),
                array_merge($left->getVariablesUsed(), $right->getVariablesUsed())
            );
        }

        $combined = [];
        foreach ($left->getValues() as $leftValue) {
            foreach ($right->getValues() as $rightValue) {
                $combined[] = $leftValue . $rightValue;
                if (count($combined) > $this->combinationLimit) {
                    return ExpressionEvaluationResult::unresolved(
                        $dynamic,
                        array_merge($left->getStrategies(), $right->getStrategies(), ['concat', 'combination_limit_exceeded']),
                        array_merge($left->getVariablesUsed(), $right->getVariablesUsed())
                    );
                }
            }
        }

        return ExpressionEvaluationResult::resolved(
            $combined,
            $dynamic || $left->isDynamic() || $right->isDynamic(),
            array_merge($left->getStrategies(), $right->getStrategies(), ['concat']),
            array_merge($left->getVariablesUsed(), $right->getVariablesUsed())
        );
    }

    /**
     * @param ExpressionEvaluationResult[] $results
     */
    private function union(array $results, bool $dynamic): ExpressionEvaluationResult
    {
        $values = [];
        $strategies = $this->mergeStrategies($results, ['branch_union']);
        $variablesUsed = $this->mergeVariablesUsed($results);
        foreach ($results as $result) {
            if (!$result->isResolved()) {
                return ExpressionEvaluationResult::unresolved(
                    $dynamic,
                    $strategies,
                    $variablesUsed
                );
            }

            foreach ($result->getValues() as $value) {
                $values[] = $value;
                if (count($values) > $this->combinationLimit) {
                    return ExpressionEvaluationResult::unresolved(
                        $dynamic,
                        array_merge($strategies, ['combination_limit_exceeded']),
                        $variablesUsed
                    );
                }
            }
        }

        return ExpressionEvaluationResult::resolved(
            $values,
            $dynamic,
            $strategies,
            $variablesUsed
        );
    }

    /**
     * @param ExpressionEvaluationResult[] $results
     * @param string[] $append
     * @return string[]
     */
    private function mergeStrategies(array $results, array $append = []): array
    {
        $strategies = [];
        foreach ($results as $result) {
            $strategies = array_merge($strategies, $result->getStrategies());
        }

        return array_values(array_unique(array_merge($strategies, $append)));
    }

    /**
     * @param ExpressionEvaluationResult[] $results
     * @return string[]
     */
    private function mergeVariablesUsed(array $results): array
    {
        $variablesUsed = [];
        foreach ($results as $result) {
            $variablesUsed = array_merge($variablesUsed, $result->getVariablesUsed());
        }

        return array_values(array_unique($variablesUsed));
    }
}
