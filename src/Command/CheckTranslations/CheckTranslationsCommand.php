<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckDictionaries\CheckDictionariesConfig;
use Efabrica\TranslationsAutomatization\Exception\InvalidConfigInstanceReturnedException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckTranslationsCommand extends Command
{
    protected function configure()
    {
        $this->setName('check:translations')
            ->setDescription('Compare all translation keys with dictionaries(from files or api) for languages(default en_US)')
            ->addArgument('config', InputArgument::REQUIRED, 'Path to config file. Instance of ' . CheckDictionariesConfig::class . ' have to be returned')
            ->addOption('params', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Params for config in format --params="a=b&c=d"');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!is_file($input->getArgument('config'))) {
            throw new InvalidArgumentException('File "' . $input->getArgument('config') . '" does not exist');
        }
        parse_str($input->getOption('params'), $params);
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
        $dirs = ['./app', './src'];

        $results = (new CodeAnalyzer($dirs))->analyzeDirectories();
        $statistics = [
            'callsTotal' => count($results),
            'resolvedStatic' => 0,
            'resolvedDynamic' => 0,
            'unresolvedDynamic' => 0,
            'strategies' => [],
            'variables' => [],
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
                        // find plural bad key
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
        foreach (array_unique($warnings) as $warning) {
            $output->writeln($warning, OutputInterface::VERBOSITY_VERY_VERBOSE);
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

        foreach ($call['resolutionStrategies'] ?? [] as $strategy) {
            if (!isset($statistics['strategies'][$strategy])) {
                $statistics['strategies'][$strategy] = 0;
            }

            $statistics['strategies'][$strategy]++;
        }

        foreach ($call['variablesUsed'] ?? [] as $variable) {
            if (!isset($statistics['variables'][$variable])) {
                $statistics['variables'][$variable] = 0;
            }

            $statistics['variables'][$variable]++;
        }
    }

    private function writeStatistics(OutputInterface $output, array $statistics): void
    {
        $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('Analysis statistics:', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  calls total: %d', $statistics['callsTotal']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  resolved static: %d', $statistics['resolvedStatic']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  resolved dynamic: %d', $statistics['resolvedDynamic']), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(sprintf('  unresolved dynamic: %d', $statistics['unresolvedDynamic']), OutputInterface::VERBOSITY_VERBOSE);

        if ($statistics['strategies'] !== []) {
            arsort($statistics['strategies']);
            $output->writeln('  strategies:', OutputInterface::VERBOSITY_VERBOSE);
            foreach ($statistics['strategies'] as $strategy => $count) {
                $output->writeln(sprintf('    %s: %d', $strategy, $count), OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        if ($statistics['variables'] !== []) {
            arsort($statistics['variables']);
            $output->writeln('  variables used in dynamic resolution:', OutputInterface::VERBOSITY_VERBOSE);
            foreach ($statistics['variables'] as $variable => $count) {
                $output->writeln(sprintf('    %s: %d', $variable, $count), OutputInterface::VERBOSITY_VERBOSE);
            }
        }
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
