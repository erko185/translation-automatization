<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use SplFileInfo;

class LatteTranslationAnalyzer
{
    private LatteTranslationExpressionResolver $expressionResolver;

    public function __construct(?LatteTranslationExpressionResolver $expressionResolver = null)
    {
        $this->expressionResolver = $expressionResolver ?? new LatteTranslationExpressionResolver();
    }

    public function analyze(SplFileInfo $file): array
    {
        $translateCalls = [];
        $variables = [];
        $lines = file($file->getPathname()) ?: [];

        foreach ($lines as $lineNumber => $lineContent) {
            $this->collectVariables($lineContent, $variables);

            if (preg_match_all('/\{_\s*([^}]+)\}/', $lineContent, $underscoreMatches) !== false) {
                foreach ($underscoreMatches[1] as $expression) {
                    $translateCalls = array_merge(
                        $translateCalls,
                        $this->buildTranslationCalls($file->getPathname(), $lineNumber + 1, trim($expression), $variables)
                    );
                }
            }

            if (preg_match_all('/\{[^}]*?((?:\'[^\']*\'|"[^"]*"|\$[A-Za-z_][A-Za-z0-9_]*)(?:\s*\.\s*(?:\'[^\']*\'|"[^"]*"|\$[A-Za-z_][A-Za-z0-9_]*))*)\s*\|\s*translate\b[^}]*\}/', $lineContent, $filterMatches) === false) {
                continue;
            }

            foreach ($filterMatches[1] as $expression) {
                $translateCalls = array_merge(
                    $translateCalls,
                    $this->buildTranslationCalls($file->getPathname(), $lineNumber + 1, trim($expression), $variables)
                );
            }
        }

        return $translateCalls;
    }

    /**
     * @param array<string, string> $variables
     * @return array<int, array<string, mixed>>
     */
    private function buildTranslationCalls(string $filePath, int $lineNumber, string $expression, array $variables): array
    {
        $result = $this->expressionResolver->resolve($expression, $variables);
        if ($result->isResolved()) {
            $translateCalls = [];
            foreach ($result->getValues() as $key) {
                $translateCalls[] = [
                    'resolvedKeys' => [$key],
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'call' => 'in_latte',
                    'arg' => null,
                    'isDynamic' => $result->isDynamic(),
                    'isResolved' => true,
                    'sourceExpression' => $expression,
                ];
            }

            return $translateCalls;
        }

        return [[
            'resolvedKeys' => [],
            'file' => $filePath,
            'line' => $lineNumber,
            'call' => 'in_latte',
            'arg' => null,
            'isDynamic' => true,
            'isResolved' => false,
            'sourceExpression' => $expression,
        ]];
    }

    /**
     * @param array<string, string> $variables
     */
    private function collectVariables(string $lineContent, array &$variables): void
    {
        if (preg_match_all('/\{var\s+\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([^}]+)\}/', $lineContent, $matches, PREG_SET_ORDER) === false) {
            return;
        }

        foreach ($matches as $match) {
            $result = $this->expressionResolver->resolve(trim($match[2]), $variables);
            if ($result->isResolved() && count($result->getValues()) === 1) {
                $variables[$match[1]] = $result->getValues()[0];
            }
        }
    }
}
