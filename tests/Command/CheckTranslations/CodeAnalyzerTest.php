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
        $this->assertContains('conditional.real.key', $resolvedKeys);
        $this->assertContains('cyclic.return.key', $resolvedKeys);
        $this->assertContains('ebox.in_app_rating.field.title.feedback', $resolvedKeys);
        $this->assertContains('ebox.in_app_rating.title.modal', $resolvedKeys);
        $this->assertContains('interprocedural.published', $resolvedKeys);
        $this->assertContains('local.prefix.title', $resolvedKeys);
        $this->assertContains('dictionary.configs.add_missing_configs', $resolvedKeys);
        $this->assertContains('property.fetch.title', $resolvedKeys);
        $this->assertContains('program.template.title', $resolvedKeys);
        $this->assertContains('latte.dynamic.key', $resolvedKeys);
        $this->assertContains('onair.app.admin_module.presenters.show_presenter.edit.title', $resolvedKeys);
        $this->assertContains('$unknownPrefix . \'.label\'', $unresolvedExpressions);
        $this->assertNotContains('$item->id', $unresolvedExpressions);
        $this->assertNotContains('', $resolvedKeys);
        $this->assertNotContains('showDetail', $resolvedKeys);
    }
}
