<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckFormKeys\ClassMethodArgVisitor;
use Exception;
use PhpParser\Error;
use PhpParser\Node;
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
        $directories = $this->directories;
        $indexDirectories = $this->expandDirectories($this->directories);
        $phpFiles = [];
        foreach ($indexDirectories as $directory) {
            if (is_dir($directory)) {
                $phpFiles = array_merge($phpFiles, $this->collectPhpFiles($directory));
            }
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $astMap = $this->parseFiles($phpFiles, $parser);
        $classIndex = ProjectClassIndex::fromAst(array_merge(...array_values($astMap)), new TranslationKeyExpressionResolver());

        $result = [];
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $result[] = $this->analyzeDirectory($directory, $parser, $astMap, $classIndex);
            }
        }
        return array_merge(...$result);
    }

    private function analyzeDirectory(string $directory, $parser, array $astMap, ProjectClassIndex $classIndex): array
    {
        $translateCalls = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if ($file->getExtension() === 'php') {
                $translateCalls[] = $this->analyzeCode($code, $file->getPathname(), $parser, $astMap[$file->getPathname()] ?? null, $classIndex);
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

    private function analyzeCode(string $code, string $filePath, $parser, ?array $ast, ProjectClassIndex $classIndex): array
    {
        $traverser = new NodeTraverser();
        $result = [];

        $traverser->addVisitor(new ClassMethodArgVisitor($result, $filePath, $classIndex, new TranslationKeyExpressionResolver()));

        try {
            $ast ??= $parser->parse($code);
            $traverser->traverse($ast);
        } catch (Exception $e) {
            echo "Error analyzing file $filePath: " . $e->getMessage() . PHP_EOL;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function collectPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @param string[] $phpFiles
     * @return array<string, Node[]>
     */
    private function parseFiles(array $phpFiles, $parser): array
    {
        $astMap = [];
        foreach ($phpFiles as $phpFile) {
            try {
                $parsed = $parser->parse((string) file_get_contents($phpFile));
                if ($parsed !== null) {
                    $astMap[$phpFile] = $parsed;
                }
            } catch (Error $error) {
                echo "Error analyzing file $phpFile: " . $error->getMessage() . PHP_EOL;
            }
        }

        return $astMap;
    }

    /**
     * @param string[] $directories
     * @return string[]
     */
    private function expandDirectories(array $directories): array
    {
        $expandedDirectories = $directories;
        $coreVendorDirectory = './vendor/notcms/core/src';
        if (is_dir($coreVendorDirectory)) {
            $expandedDirectories[] = $coreVendorDirectory;
        }

        return array_values(array_unique($expandedDirectories));
    }
}
