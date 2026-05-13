<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node;
use PhpParser\Node\Expr;

class BooleanExpressionEvaluator
{
    public function evaluate(Expr $expression): ?bool
    {
        if ($expression instanceof Expr\BinaryOp\Identical) {
            return $this->evaluateComparison($expression->left, $expression->right, static fn($left, $right): bool => $left === $right);
        }

        if ($expression instanceof Expr\BinaryOp\NotIdentical) {
            return $this->evaluateComparison($expression->left, $expression->right, static fn($left, $right): bool => $left !== $right);
        }

        if ($expression instanceof Expr\BinaryOp\BooleanAnd) {
            $left = $this->evaluate($expression->left);
            $right = $this->evaluate($expression->right);
            if ($left === null || $right === null) {
                return null;
            }

            return $left && $right;
        }

        if ($expression instanceof Expr\BinaryOp\BooleanOr) {
            $left = $this->evaluate($expression->left);
            $right = $this->evaluate($expression->right);
            if ($left === null || $right === null) {
                return null;
            }

            return $left || $right;
        }

        if ($expression instanceof Expr\BooleanNot) {
            $result = $this->evaluate($expression->expr);

            return $result === null ? null : !$result;
        }

        $scalar = $this->evaluateScalar($expression);
        if ($scalar === null) {
            return null;
        }

        return (bool) $scalar;
    }

    private function evaluateComparison(Expr $leftExpression, Expr $rightExpression, callable $comparator): ?bool
    {
        $left = $this->evaluateScalar($leftExpression);
        $right = $this->evaluateScalar($rightExpression);
        if ($left === null || $right === null) {
            return null;
        }

        return $comparator($left, $right);
    }

    /**
     * @return scalar|null
     */
    private function evaluateScalar(Expr $expression)
    {
        if ($expression instanceof Node\Scalar\String_) {
            return $expression->value;
        }

        if ($expression instanceof Node\Scalar\Int_) {
            return $expression->value;
        }

        if ($expression instanceof Node\Scalar\Float_) {
            return $expression->value;
        }

        if ($expression instanceof Expr\ConstFetch) {
            $name = strtolower($expression->name->toString());
            if ($name === 'true') {
                return true;
            }
            if ($name === 'false') {
                return false;
            }
            if ($name === 'null') {
                return null;
            }
        }

        return null;
    }
}
