<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckDictionaries\CheckDictionariesConfig;
use Efabrica\TranslationsAutomatization\Exception\InvalidConfigInstanceReturnedException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckTranslationsDeepCommand extends Command
{
    protected function configure()
    {
        $this->setName('check:translations:deep')
            ->setDescription('Compare translation keys including dynamic-resolution analysis and statistics')
            ->addArgument('config', InputArgument::REQUIRED, 'Path to config file. Instance of ' . CheckDictionariesConfig::class . ' have to be returned')
            ->addOption('params', null, InputOption::VALUE_REQUIRED, 'Params for config in format --params="a=b&c=d"')
            ->addOption('hide-dynamic-warnings', null, InputOption::VALUE_NONE, 'Do not print unresolved dynamic translation warnings');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!is_file($input->getArgument('config'))) {
            throw new InvalidArgumentException('File "' . $input->getArgument('config') . '" does not exist');
        }
        parse_str((string) $input->getOption('params'), $params);
        extract($params);

        $checkDictionariesConfig = require $input->getArgument('config');
        if ($checkDictionariesConfig instanceof InvalidConfigInstanceReturnedException) {
            throw $checkDictionariesConfig;
        }
        if (!$checkDictionariesConfig instanceof CheckDictionariesConfig) {
            throw new InvalidConfigInstanceReturnedException('"' . (is_object($checkDictionariesConfig) ? get_class($checkDictionariesConfig) : $checkDictionariesConfig) . '" is not instance of ' . CheckDictionariesConfig::class);
        }

        $output->writeln('');
        $output->writeln('Loading dictionaries...');
        $dictionaries = $checkDictionariesConfig->load();

        $onlyOneLang = (count($dictionaries) === 1);
        $errors = [];
        $warnings = [];
        $hideDynamicWarnings = (bool) $input->getOption('hide-dynamic-warnings');
        $dirs = ['./app', './src'];

        $results = (new CodeAnalyzer($dirs))->analyzeDirectories();
        $statistics = [
            'callsTotal' => count($results),
            'resolvedStatic' => 0,
            'resolvedDynamic' => 0,
            'unresolvedDynamic' => 0,
            'unresolvedStrategies' => [],
            'unresolvedStrategyExamples' => [],
            'unresolvedVariables' => [],
            'unresolvedVariableExamples' => [],
            'unresolvedFiles' => [],
            'unresolvedFileExamples' => [],
        ];
        foreach ($results as $call) {
            $this->collectStatistics($statistics, $call);
            if (($call['isResolved'] ?? true) === false) {
                $warnings[] = sprintf(
                    'Unresolved dynamic translation key in file: %s:%s' . (isset($call['call']) ? ' call: "%s"' : '%s') . (isset($call['sourceExpression']) ? ' expression: "%s"' : '%s') . '%s%s',
                    $call['file'],
                    $call['line'],
                    $call['call'] ?? '',
                    $call['sourceExpression'] ?? '',
                    $this->formatStrategiesSuffix($call['resolutionStrategies'] ?? []),
                    $this->formatVariablesSuffix($call['variablesUsed'] ?? [])
                );
                continue;
            }

            $keys = $call['resolvedKeys'] ?? [];
            if ($keys === []) {
                continue;
            }

            foreach (array_unique($keys) as $key) {
                if (!is_string($key)) {
                    continue;
                }
                if ($dictionaries === []) {
                    $errors[] = 'No dictionaries found.';
                    break 2;
                }
                foreach ($dictionaries as $lang => $dictionary) {
                    $langText = !$onlyOneLang ? ' for language "' . $lang . '"' : '';
                    if (!isset($dictionary[$key])) {
                        $errors[] = sprintf(
                            'Missing translation for key "%s" ' . $langText . 'in file: %s:%s' . (isset($call['call']) ? ' call: "%s"' : '%s'),
                            $key,
                            $call['file'],
                            $call['line'],
                            $call['call'] ?? ''
                        );
                    } else {
                        $dictionaryTranslate = $dictionary[$key];
                        $pluralKey = $call['arg'] ?? null;
                        $pluralKeyInFile = $pluralKey ? '%' . $pluralKey . '%' : null;
                        if ($pluralKey && strpos($dictionaryTranslate, $pluralKeyInFile) === false) {
                            $errors[] = sprintf(
                                'Translation key "%s" ' . $langText . 'in file: %s:%s call: "%s" has bad plural key: %s for translation: "%s"',
                                $key,
                                $call['file'],
                                $call['line'],
                                $call['call'],
                                $pluralKeyInFile,
                                $dictionaryTranslate
                            );
                        }
                        if ($pluralKey === null && preg_match('/.*%.+%.*/', $dictionaryTranslate) === false) {
                            $errors[] = sprintf(
                                'Translation key "%s" ' . $langText . 'in file: %s:%s call: "%s" has missing plural key for translation: "%s"',
                                $key,
                                $call['file'],
                                $call['line'],
                                $call['call'],
                                $dictionaryTranslate
                            );
                        }
                    }
                }
            }
        }
        $output->writeln('', OutputInterface::VERBOSITY_VERY_VERBOSE);
        foreach (array_unique($errors) as $error) {
            $output->writeln($error, OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        if (!$hideDynamicWarnings) {
            foreach (array_unique($warnings) as $warning) {
                $output->writeln($warning, OutputInterface::VERBOSITY_VERY_VERBOSE);
            }
        }
        $this->writeStatistics($output, $statistics);

        $output->writeln('');
        $output->writeln('<comment>' . count($errors) . ' errors found</comment>');
        $output->writeln('<comment>' . count(array_unique($warnings)) . ' unresolved dynamic keys found</comment>');
        return count($errors);
    }

    private function collectStatistics(array &$statistics, array $call): void
    {
        if (($call['isResolved'] ?? true) === false) {
            $statistics['unresolvedDynamic']++;
        } elseif (($call['isDynamic'] ?? false) === true) {
            $statistics['resolvedDynamic']++;
        } else {
            $statistics['resolvedStatic']++;
        }

        if (($call['isResolved'] ?? true) === false) {
            $this->incrementGroupedStatistic($statistics['unresolvedFiles'], $statistics['unresolvedFileExamples'], (string) ($call['file'] ?? 'unknown'), $call, true);

            foreach ($call['resolutionStrategies'] ?? [] as $strategy) {
                $this->incrementGroupedStatistic($statistics['unresolvedStrategies'], $statistics['unresolvedStrategyExamples'], $strategy, $call);
            }

            foreach ($call['variablesUsed'] ?? [] as $variable) {
                $this->incrementGroupedStatistic($statistics['unresolvedVariables'], $statistics['unresolvedVariableExamples'], $variable, $call);
            }

            return;
        }
    }

    private function writeStatistics(OutputInterface $output, array $statistics): void
    {
        $resolvedTotal = $statistics['resolvedStatic'] + $statistics['resolvedDynamic'];
        $resolutionRate = $statistics['callsTotal'] > 0 ? ($resolvedTotal / $statistics['callsTotal']) * 100 : 0.0;
        $dynamicResolutionRate = ($statistics['resolvedDynamic'] + $statistics['unresolvedDynamic']) > 0
            ? ($statistics['resolvedDynamic'] / ($statistics['resolvedDynamic'] + $statistics['unresolvedDynamic'])) * 100
            : 0.0;

        $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('==================== Analysis Statistics ====================', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('[Overview]', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  Total calls              %6d', $statistics['callsTotal']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  Resolved total           %6d   %s', $resolvedTotal, $this->renderPercentBar($resolutionRate)), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  Resolved static          %6d', $statistics['resolvedStatic']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  Resolved dynamic         %6d', $statistics['resolvedDynamic']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  Unresolved dynamic       %6d', $statistics['unresolvedDynamic']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  Dynamic success rate     %6.1f%%   %s', $dynamicResolutionRate, $this->renderPercentBar($dynamicResolutionRate)), OutputInterface::VERBOSITY_VERBOSE);

        $this->writeGroupedStatistics(
            $output,
            '[What Fails: Unresolved Dynamic Blockers]',
            $statistics['unresolvedStrategies'],
            $statistics['unresolvedStrategyExamples']
        );

        $this->writeGroupedStatistics(
            $output,
            '[Variables Behind Unresolved Cases]',
            $statistics['unresolvedVariables'],
            $statistics['unresolvedVariableExamples']
        );

        $this->writeGroupedStatistics(
            $output,
            '[Top Files With Unresolved Dynamic Keys]',
            $statistics['unresolvedFiles'],
            $statistics['unresolvedFileExamples'],
            true
        );
        $output->writeln('=============================================================', OutputInterface::VERBOSITY_VERBOSE);
    }

    private function writeGroupedStatistics(OutputInterface $output, string $title, array $counts, array $examples, bool $fileMode = false): void
    {
        if ($counts === []) {
            return;
        }

        arsort($counts);
        $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln($title, OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('  ----------------------------------------------------------', OutputInterface::VERBOSITY_VERBOSE);

        $index = 0;
        foreach ($counts as $label => $count) {
            $index++;
            if ($index > 10) {
                break;
            }

            $lineLabel = $fileMode ? $this->shortenPath($label) : $label;
            $output->writeln(sprintf(
                '  %2d. %-32s %6d%s',
                $index,
                $this->truncateLabel($lineLabel, 32),
                $count,
                $this->formatStatisticExample($examples[$label] ?? null)
            ), OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    private function incrementGroupedStatistic(array &$counts, array &$examples, string $key, array $call, bool $preferFileExample = false): void
    {
        if (!isset($counts[$key])) {
            $counts[$key] = 0;
        }

        $counts[$key]++;
        if (!isset($examples[$key])) {
            $examples[$key] = $this->buildStatisticExample($call, $key, $preferFileExample);
        }
    }

    private function buildStatisticExample(array $call, string $label, bool $preferFileExample = false): string
    {
        if ($preferFileExample) {
            return sprintf(
                '%s:%s%s',
                $this->shortenPath((string) ($call['file'] ?? 'unknown')),
                $call['line'] ?? '?',
                isset($call['sourceExpression']) && is_string($call['sourceExpression']) && $call['sourceExpression'] !== ''
                    ? ' -> ' . $call['sourceExpression']
                    : ''
            );
        }

        $expression = $call['sourceExpression'] ?? null;
        if (!is_string($expression) || $expression === '') {
            $resolvedKey = $call['resolvedKeys'][0] ?? null;
            if (is_string($resolvedKey) && $resolvedKey !== '') {
                $expression = $resolvedKey;
            }
        }

        if (!is_string($expression) || $expression === '') {
            $expression = $call['call'] ?? $label;
        }

        return sprintf(
            '%s in %s:%s',
            $expression,
            $this->shortenPath((string) ($call['file'] ?? 'unknown')),
            $call['line'] ?? '?'
        );
    }

    private function formatStatisticExample(?string $example): string
    {
        if ($example === null || $example === '') {
            return '';
        }

        return sprintf("\n      example: %s", $example);
    }

    private function shortenPath(string $path): string
    {
        if (strpos($path, './') === 0) {
            return $path;
        }

        $appPosition = strpos($path, '/app/');
        if ($appPosition !== false) {
            return '.' . substr($path, $appPosition);
        }

        $srcPosition = strpos($path, '/src/');
        if ($srcPosition !== false) {
            return '.' . substr($path, $srcPosition);
        }

        return $path;
    }

    private function truncateLabel(string $label, int $maxLength): string
    {
        if (strlen($label) <= $maxLength) {
            return $label;
        }

        return substr($label, 0, $maxLength - 3) . '...';
    }

    private function renderPercentBar(float $percent): string
    {
        $normalizedPercent = max(0.0, min(100.0, $percent));
        $filled = (int) round($normalizedPercent / 10);
        $empty = 10 - $filled;

        return sprintf('[%s%s] %5.1f%%', str_repeat('#', $filled), str_repeat('.', $empty), $normalizedPercent);
    }

    private function formatStrategiesSuffix(array $strategies): string
    {
        if ($strategies === []) {
            return '';
        }

        return sprintf(' strategies: [%s]', implode(', ', array_unique($strategies)));
    }

    private function formatVariablesSuffix(array $variablesUsed): string
    {
        if ($variablesUsed === []) {
            return '';
        }

        return sprintf(' variables: [%s]', implode(', ', array_unique($variablesUsed)));
    }
}
