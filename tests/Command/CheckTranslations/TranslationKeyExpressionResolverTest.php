<?php

namespace Efabrica\TranslationsAutomatization\Tests\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckTranslations\ExpressionEvaluationResult;
use Efabrica\TranslationsAutomatization\Command\CheckTranslations\TranslationKeyExpressionResolver;
use PhpParser\Node\Expr;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class TranslationKeyExpressionResolverTest extends TestCase
{
    private TranslationKeyExpressionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TranslationKeyExpressionResolver();
    }

    public function testResolveConcatenationWithVariable(): void
    {
        $result = $this->resolver->resolve(
            $this->parseExpression('$prefix . "detail"'),
            [
                'prefix' => ExpressionEvaluationResult::resolved(['checkout.']),
            ]
        );

        $this->assertTrue($result->isResolved());
        $this->assertTrue($result->isDynamic());
        $this->assertSame(['checkout.detail'], $result->getValues());
    }

    public function testResolveInterpolatedString(): void
    {
        $result = $this->resolver->resolve(
            $this->parseExpression('"checkout.$section.label"'),
            [
                'section' => ExpressionEvaluationResult::resolved(['payment']),
            ]
        );

        $this->assertTrue($result->isResolved());
        $this->assertSame(['checkout.payment.label'], $result->getValues());
    }

    public function testResolveSprintf(): void
    {
        $result = $this->resolver->resolve($this->parseExpression('sprintf("checkout.%s.label", "payment")'));

        $this->assertTrue($result->isResolved());
        $this->assertSame(['checkout.payment.label'], $result->getValues());
    }

    public function testResolveNestedTranslateMethodCallIgnoresTranslationParams(): void
    {
        $result = $this->resolver->resolve(
            $this->parseExpression('$this->translator->translate("skeleton.form.deleted", ["name" => $this->currentImageType->title])')
        );

        $this->assertTrue($result->isResolved());
        $this->assertSame(['skeleton.form.deleted'], $result->getValues());
    }

    public function testResolveNestedTransMethodCall(): void
    {
        $result = $this->resolver->resolve(
            $this->parseExpression('$this->translate("onair.app.admin_module.grids.show_collection_grid.hidden")')
        );

        $this->assertTrue($result->isResolved());
        $this->assertSame(['onair.app.admin_module.grids.show_collection_grid.hidden'], $result->getValues());
    }

    public function testResolveClassConstantFetch(): void
    {
        $result = $this->resolver
            ->withClassConstants(['MENU_TITLE' => ExpressionEvaluationResult::resolved(['program.template.title'])])
            ->resolve($this->parseExpression('self::MENU_TITLE'));

        $this->assertTrue($result->isResolved());
        $this->assertSame(['program.template.title'], $result->getValues());
    }

    public function testUnresolvedUnknownVariable(): void
    {
        $result = $this->resolver->resolve($this->parseExpression('$missing . ".label"'));

        $this->assertFalse($result->isResolved());
        $this->assertTrue($result->isDynamic());
    }

    private function parseExpression(string $expression): Expr
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $statements = $parser->parse('<?php ' . $expression . ';');

        return $statements[0]->expr;
    }
}
