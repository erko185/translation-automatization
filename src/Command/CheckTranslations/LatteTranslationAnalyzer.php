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

            if (preg_match_all('/\{_\s*([^}]+)\}/', $lineContent, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $expression) {
                $result = $this->expressionResolver->resolve(trim($expression), $variables);
                if ($result->isResolved()) {
                    foreach ($result->getValues() as $key) {
                        $translateCalls[] = [
                            'resolvedKeys' => [$key],
                            'file' => $file->getPathname(),
                            'line' => $lineNumber + 1,
                            'call' => 'in_latte',
                            'arg' => null,
                            'isDynamic' => $result->isDynamic(),
                            'isResolved' => true,
                            'sourceExpression' => trim($expression),
                        ];
                    }
                    continue;
                }

                $translateCalls[] = [
                    'resolvedKeys' => [],
                    'file' => $file->getPathname(),
                    'line' => $lineNumber + 1,
                    'call' => 'in_latte',
                    'arg' => null,
                    'isDynamic' => true,
                    'isResolved' => false,
                    'sourceExpression' => trim($expression),
                ];
            }
        }

        return $translateCalls;
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
