<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Use_;

class TranslationCallMatcher
{
    /** @var array<int, string[]> */
    private const CONSTRUCTOR_TRANSLATION_CLASSES = [
        0 => [
            'Efabrica\WebComponent\Core\Menu\MenuItem',
        ],
    ];

    /** @var array<string, array<int|string, string[]>> */
    private const METHOD_TRANSLATION_SELECTORS = [
        'ALL' => [
            0 => [
                'translate',
                'flashMessage',
            ],
        ],
        'Grid' => [
            0 => [
                'trans',
                'global',
            ],
            1 => [
                'dateTime',
                'text',
                'add',
                'range',
                'dateRange',
                'comparator',
                'number',
                'customInfo',
            ],
            2 => [
                'select',
                'multiselect',
                'choozer',
                'ajaxSelect',
                'checkboxList',
                'multiValueComparator',
                'published',
                'createModal',
                'create',
                'delete',
                'deleteFromRepo',
                'addInfo',
                'column',
            ],
            'label' => [
                'ajaxModal',
                'modal',
            ],
        ],
        'Form' => [
            0 => [
                'setRequired',
            ],
            1 => [
                'addText',
                'addTextArea',
                'addEmail',
                'addInteger',
                'addFloat',
                'addDate',
                'addTime',
                'addDateTime',
                'addUpload',
                'addMultiUpload',
                'addCheckbox',
                'addRadioList',
                'addCheckboxList',
                'addSelect',
                'addColor',
                'addSubmit',
                'addButton',
                'addAjaxTags',
                'addDateTimePicker',
                'custom',
                'addRule',
            ],
            2 => [
                'addChooze',
                'infoBadge',
            ],
        ],
        'Module' => [
            2 => [
                'addResource',
            ],
        ],
        'Plugin' => [
            1 => [
                'dropdown',
                'multiDropdown',
                'string',
                'number',
                'choozer',
                'checkbox',
                'multi',
                'dateTime',
                'text',
                'StringConfigItem',
                'DateTimeConfigItem',
                'NumberConfigItem',
                'ChoozerConfigItem',
            ],
            2 => [
                'dropdown',
            ],
            3 => [
                'dropdown',
            ],
        ],
    ];

    /** @var array<string, array<int|string, string[]>> */
    private const ALLOW_EMPTY_TRANSLATION = [
        'Form' => [
            1 => [
                'addSelect',
                'addTextArea',
            ],
        ],
        'Plugin' => [
            3 => [
                'dropdown',
            ],
        ],
    ];

    /** @var array<int, string> */
    private array $constructorClassMap = [];

    public function collectUse(Node $node): void
    {
        if (!$node instanceof Use_) {
            return;
        }

        foreach ($node->uses as $use) {
            $useName = $use->name->name;
            foreach (self::CONSTRUCTOR_TRANSLATION_CLASSES as $argIndex => $classes) {
                if (in_array($useName, $classes, true)) {
                    $shortName = basename(str_replace('\\', '/', $useName));
                    $this->constructorClassMap[$argIndex] = $shortName;
                }
            }
        }
    }

    public function matchConstructor(New_ $node): ?TranslationCallMatch
    {
        if (!isset($node->class) || !in_array($node->class->name ?? null, $this->constructorClassMap, true)) {
            return null;
        }

        $className = $node->class->name;
        $argIndex = array_search($className, $this->constructorClassMap, true);
        if ($argIndex === false) {
            return null;
        }

        return new TranslationCallMatch($className, $argIndex, $className);
    }

    /**
     * @return TranslationCallMatch[]
     */
    public function matchMethodCall(MethodCall $node, string $className): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->name;
        $matches = [];
        foreach (self::METHOD_TRANSLATION_SELECTORS as $classNamePart => $argposMethods) {
            if ($classNamePart !== 'ALL' && (strpos($className, $classNamePart) === false || substr($className, -strlen($classNamePart)) !== $classNamePart)) {
                continue;
            }

            foreach ($argposMethods as $argSelector => $methods) {
                if (in_array($methodName, $methods, true)) {
                    $matches[] = new TranslationCallMatch($methodName, is_numeric($argSelector) ? (int) $argSelector : $argSelector, $classNamePart);
                }
            }
        }

        return $matches;
    }

    public function allowsEmptyTranslation(string $context, int|string $argSelector, string $method, string $key): bool
    {
        if ($key !== '' && $key !== '--') {
            return false;
        }

        return
            array_key_exists($context, self::ALLOW_EMPTY_TRANSLATION) &&
            array_key_exists($argSelector, self::ALLOW_EMPTY_TRANSLATION[$context]) &&
            in_array($method, self::ALLOW_EMPTY_TRANSLATION[$context][$argSelector], true);
    }
}
