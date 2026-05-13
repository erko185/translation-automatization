<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

class ExpressionEvaluationResult
{
    /** @var string[] */
    private array $values;

    /** @var array<string, self> */
    private array $arrayItems;

    /** @var array<string, self> */
    private array $objectProperties;

    /** @var string[] */
    private array $strategies;

    /** @var string[] */
    private array $variablesUsed;

    private bool $resolved;

    private bool $dynamic;

    /**
     * @param string[] $values
     * @param string[] $strategies
     * @param string[] $variablesUsed
     */
    private function __construct(array $values, bool $resolved, bool $dynamic, array $strategies = [], array $variablesUsed = [], array $arrayItems = [], array $objectProperties = [])
    {
        $this->values = array_values(array_unique($values));
        $this->arrayItems = $arrayItems;
        $this->objectProperties = $objectProperties;
        $this->strategies = array_values(array_unique($strategies));
        $this->variablesUsed = array_values(array_unique($variablesUsed));
        $this->resolved = $resolved;
        $this->dynamic = $dynamic;
    }

    public static function resolved(array $values, bool $dynamic = false, array $strategies = [], array $variablesUsed = []): self
    {
        return new self($values, true, $dynamic, $strategies, $variablesUsed);
    }

    /**
     * @param array<string, self> $arrayItems
     */
    public static function resolvedArray(array $values, array $arrayItems, bool $dynamic = false, array $strategies = [], array $variablesUsed = []): self
    {
        return new self($values, true, $dynamic, $strategies, $variablesUsed, $arrayItems);
    }

    /**
     * @param array<string, self> $objectProperties
     */
    public static function resolvedObject(array $objectProperties, bool $dynamic = true, array $strategies = [], array $variablesUsed = []): self
    {
        return new self([], true, $dynamic, $strategies, $variablesUsed, [], $objectProperties);
    }

    public static function unresolved(bool $dynamic = true, array $strategies = [], array $variablesUsed = []): self
    {
        return new self([], false, $dynamic, $strategies, $variablesUsed);
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    public function hasArrayItems(): bool
    {
        return $this->arrayItems !== [];
    }

    public function getArrayItem(string $key): ?self
    {
        return $this->arrayItems[$key] ?? null;
    }

    /**
     * @return array<string, self>
     */
    public function getArrayItems(): array
    {
        return $this->arrayItems;
    }

    public function hasObjectProperties(): bool
    {
        return $this->objectProperties !== [];
    }

    public function getObjectProperty(string $property): ?self
    {
        return $this->objectProperties[$property] ?? null;
    }

    /**
     * @return array<string, self>
     */
    public function getObjectProperties(): array
    {
        return $this->objectProperties;
    }

    /**
     * @return string[]
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * @return string[]
     */
    public function getVariablesUsed(): array
    {
        return $this->variablesUsed;
    }

    public function asDynamic(): self
    {
        if ($this->dynamic) {
            return $this;
        }

        return new self($this->values, $this->resolved, true, $this->strategies, $this->variablesUsed, $this->arrayItems, $this->objectProperties);
    }

    public function withStrategy(string $strategy): self
    {
        return new self(
            $this->values,
            $this->resolved,
            $this->dynamic,
            array_merge($this->strategies, [$strategy]),
            $this->variablesUsed,
            $this->arrayItems,
            $this->objectProperties
        );
    }

    public function withVariable(string $variable): self
    {
        return new self(
            $this->values,
            $this->resolved,
            $this->dynamic,
            $this->strategies,
            array_merge($this->variablesUsed, [$variable]),
            $this->arrayItems,
            $this->objectProperties
        );
    }
}
