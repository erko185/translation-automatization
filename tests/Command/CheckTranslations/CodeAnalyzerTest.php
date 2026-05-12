<?php

namespace Efabrica\TranslationsAutomatization\Tests\Command\CheckTranslations;

use Efabrica\TranslationsAutomatization\Command\CheckTranslations\CodeAnalyzer;
use PHPUnit\Framework\TestCase;

class CodeAnalyzerTest extends TestCase
{
    public function testAnalyzeDirectoriesResolvesDynamicPhpAndLatteKeys(): void
    {
        $basePath = __DIR__ . '/../../sample-data/check-translations-project';

        $results = (new CodeAnalyzer([$basePath . '/src', $basePath . '/templates']))->analyzeDirectories();

        $resolvedKeys = [];
        $unresolvedExpressions = [];
        foreach ($results as $result) {
            foreach ($result['resolvedKeys'] ?? [] as $resolvedKey) {
                $resolvedKeys[] = $resolvedKey;
            }

            if (($result['isResolved'] ?? true) === false) {
                $unresolvedExpressions[] = $result['sourceExpression'];
            }
        }

        $this->assertContains('checkout.summary.title', $resolvedKeys);
        $this->assertContains('checkout.payment.label', $resolvedKeys);
        $this->assertContains('feed.condition.option.a', $resolvedKeys);
        $this->assertContains('feed.condition.option.b', $resolvedKeys);
        $this->assertContains('program.template.title', $resolvedKeys);
        $this->assertContains('latte.dynamic.key', $resolvedKeys);
        $this->assertContains('onair.app.admin_module.presenters.show_presenter.edit.title', $resolvedKeys);
        $this->assertContains('$unknownPrefix . ".label"', $unresolvedExpressions);
        $this->assertNotContains('$item->id', $unresolvedExpressions);
        $this->assertNotContains('showDetail', $resolvedKeys);
    }
}
