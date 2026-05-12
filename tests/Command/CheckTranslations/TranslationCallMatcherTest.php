<?php

namespace Efabrica\TranslationsAutomatization\Tests\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckTranslations\TranslationCallMatcher;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class TranslationCallMatcherTest extends TestCase
{
    public function testMatchMethodCallForGridCustomInfo(): void
    {
        $matcher = new TranslationCallMatcher();
        $methodCall = $this->parseMethodCall('<?php $grid->customInfo("label", function () { return ["a"]; });');

        $matches = $matcher->matchMethodCall($methodCall, 'ShowCollectionGrid');

        $this->assertCount(1, $matches);
        $this->assertSame('customInfo', $matches[0]->call);
        $this->assertSame(1, $matches[0]->argSelector);
        $this->assertSame('Grid', $matches[0]->context);
    }

    public function testDoNotMatchGridLink(): void
    {
        $matcher = new TranslationCallMatcher();
        $methodCall = $this->parseMethodCall('<?php $grid->link("showDetail", $item->id);');

        $matches = $matcher->matchMethodCall($methodCall, 'ShowContentGrid');

        $this->assertSame([], $matches);
    }

    public function testMatchConstructorAfterUseCollection(): void
    {
        $matcher = new TranslationCallMatcher();
        $useStatement = $this->parseUse('<?php use Efabrica\WebComponent\Core\Menu\MenuItem;');
        $matcher->collectUse($useStatement);
        $newExpression = $this->parseNew('<?php new MenuItem("menu.key");');

        $match = $matcher->matchConstructor($newExpression);

        $this->assertNotNull($match);
        $this->assertSame('MenuItem', $match->call);
        $this->assertSame(0, $match->argSelector);
    }

    private function parseMethodCall(string $code): MethodCall
    {
        $statement = $this->parseStatements($code)[0];

        return $statement->expr;
    }

    private function parseUse(string $code): Use_
    {
        return $this->parseStatements($code)[0];
    }

    private function parseNew(string $code): New_
    {
        $statement = $this->parseStatements($code)[0];

        return $statement->expr;
    }

    private function parseStatements(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        return $parser->parse($code);
    }
}
