<?php

namespace Efabrica\TranslationsAutomatization\Tests\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckTranslations\CheckTranslationsCommand;
use Efabrica\TranslationsAutomatization\Tests\Command\BaseCommandTest;
use Symfony\Component\Console\Output\OutputInterface;

class CheckTranslationsCommandTest extends BaseCommandTest
{
    public function testCommandReportsResolvedAndUnresolvedDynamicKeys(): void
    {
        $currentWorkingDirectory = getcwd();
        chdir(__DIR__ . '/../../sample-data/check-translations-project');

        try {
            $input = $this->createInput();
            $input->setArgument('config', __DIR__ . '/../../sample-data/check-translations-configs/correct_config.php');
            $output = $this->createOutput();
            $command = new CheckTranslationsCommand();

            $returnCode = $command->run($input, $output);

            $this->assertSame(0, $returnCode);
            $this->assertContains("<comment>0 errors found</comment>\n", $output->getMessages(0));
            $this->assertContains("<comment>1 unresolved dynamic keys found</comment>\n", $output->getMessages(0));

            $verboseMessages = $output->getMessages(OutputInterface::VERBOSITY_VERY_VERBOSE);
            $this->assertContains(
                'Unresolved dynamic translation key in file: ./src/DynamicTranslationsPresenter.php:19 call: "translate" expression: "$unknownPrefix . \'.label\'"' . "\n",
                $verboseMessages
            );
        } finally {
            chdir($currentWorkingDirectory);
        }
    }
}
