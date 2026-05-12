<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckFormKeys\ClassMethodArgVisitor;
use Exception;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CodeAnalyzer
{
    private array $directories;

    private LatteTranslationAnalyzer $latteTranslationAnalyzer;

    public function __construct(array $directories, ?LatteTranslationAnalyzer $latteTranslationAnalyzer = null)
    {
        $this->directories = $directories;
        $this->latteTranslationAnalyzer = $latteTranslationAnalyzer ?? new LatteTranslationAnalyzer();
    }

    public function analyzeDirectories(): array
    {
        $result = [];
        foreach ($this->directories as $directory) {
            if (is_dir($directory)) {
                $result[] = $this->analyzeDirectory($directory);
            }
        }
        return array_merge(...$result);
    }

    private function analyzeDirectory(string $directory): array
    {
        $translateCalls = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if ($file->getExtension() === 'php') {
                $translateCalls[] = $this->analyzeCode($code, $file->getPathname());
            }
            if ($file->getExtension() === 'latte') {
                $translateCalls[] = $this->findInLatte($file);
            }
        }

        return array_merge(...$translateCalls);
    }

    private function findInLatte(SplFileInfo $file): array
    {
        return $this->latteTranslationAnalyzer->analyze($file);
    }

    private function analyzeCode(string $code, string $filePath): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $result = [];

        $traverser->addVisitor(new ClassMethodArgVisitor($result, $filePath, new TranslationKeyExpressionResolver()));

        try {
            $ast = $parser->parse($code);
            $traverser->traverse($ast);
        } catch (Exception $e) {
            echo "Error analyzing file $filePath: " . $e->getMessage() . PHP_EOL;
        }

        return $result;
    }
}
