<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

class LatteTranslationExpressionResolver
{
    /**
     * @param array<string, string> $variables
     */
    public function resolve(string $expression, array $variables = []): ExpressionEvaluationResult
    {
        $expression = $this->extractTranslationKeyExpression($expression);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $expression) === 1) {
            return ExpressionEvaluationResult::resolved([$expression], false, ['latte_expression']);
        }

        $segments = $this->splitByConcatenation($expression);
        if ($segments === null) {
            return ExpressionEvaluationResult::unresolved(true, ['latte_expression']);
        }

        $value = '';
        $dynamic = false;
        foreach ($segments as $segment) {
            $resolvedSegment = $this->resolveSegment($segment, $variables);
            if ($resolvedSegment === null) {
                return ExpressionEvaluationResult::unresolved(true, ['latte_expression']);
            }

            $value .= $resolvedSegment['value'];
            $dynamic = $dynamic || $resolvedSegment['dynamic'];
        }

        return ExpressionEvaluationResult::resolved([$value], $dynamic, ['latte_expression']);
    }

    /**
     * @return string[]|null
     */
    private function splitByConcatenation(string $expression): ?array
    {
        $segments = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;

        $length = strlen($expression);
        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];

            if ($character === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $buffer .= $character;
                continue;
            }

            if ($character === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $buffer .= $character;
                continue;
            }

            if ($character === '.' && !$inSingleQuote && !$inDoubleQuote) {
                $segments[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        if ($inSingleQuote || $inDoubleQuote) {
            return null;
        }

        $segments[] = trim($buffer);

        return $segments;
    }

    private function extractTranslationKeyExpression(string $expression): string
    {
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $squareBracketDepth = 0;
        $roundBracketDepth = 0;
        $curlyBracketDepth = 0;

        $length = strlen($expression);
        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];

            if ($character === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $buffer .= $character;
                continue;
            }

            if ($character === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $buffer .= $character;
                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($character === '[') {
                    $squareBracketDepth++;
                } elseif ($character === ']') {
                    $squareBracketDepth--;
                } elseif ($character === '(') {
                    $roundBracketDepth++;
                } elseif ($character === ')') {
                    $roundBracketDepth--;
                } elseif ($character === '{') {
                    $curlyBracketDepth++;
                } elseif ($character === '}') {
                    $curlyBracketDepth--;
                } elseif (
                    $character === ','
                    && $squareBracketDepth === 0
                    && $roundBracketDepth === 0
                    && $curlyBracketDepth === 0
                ) {
                    break;
                }
            }

            $buffer .= $character;
        }

        return trim($buffer);
    }

    /**
     * @param array<string, string> $variables
     * @return array{value: string, dynamic: bool}|null
     */
    private function resolveSegment(string $segment, array $variables): ?array
    {
        if ($segment === '') {
            return ['value' => '', 'dynamic' => false];
        }

        if (preg_match('/^\'(.*)\'$/s', $segment, $match) === 1) {
            return ['value' => stripslashes($match[1]), 'dynamic' => false];
        }

        if (preg_match('/^"(.*)"$/s', $segment, $match) === 1) {
            return ['value' => stripslashes($match[1]), 'dynamic' => false];
        }

        if (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)$/', $segment, $match) === 1 && isset($variables[$match[1]])) {
            return ['value' => $variables[$match[1]], 'dynamic' => true];
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $segment) === 1) {
            return ['value' => $segment, 'dynamic' => false];
        }

        return null;
    }
}
