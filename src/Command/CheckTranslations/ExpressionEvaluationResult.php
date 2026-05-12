<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

class ExpressionEvaluationResult
{
    /** @var string[] */
    private array $values;

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
    private function __construct(array $values, bool $resolved, bool $dynamic, array $strategies = [], array $variablesUsed = [])
    {
        $this->values = array_values(array_unique($values));
        $this->strategies = array_values(array_unique($strategies));
        $this->variablesUsed = array_values(array_unique($variablesUsed));
        $this->resolved = $resolved;
        $this->dynamic = $dynamic;
    }

    public static function resolved(array $values, bool $dynamic = false, array $strategies = [], array $variablesUsed = []): self
    {
        return new self($values, true, $dynamic, $strategies, $variablesUsed);
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

        return new self($this->values, $this->resolved, true, $this->strategies, $this->variablesUsed);
    }

    public function withStrategy(string $strategy): self
    {
        return new self(
            $this->values,
            $this->resolved,
            $this->dynamic,
            array_merge($this->strategies, [$strategy]),
            $this->variablesUsed
        );
    }

    public function withVariable(string $variable): self
    {
        return new self(
            $this->values,
            $this->resolved,
            $this->dynamic,
            $this->strategies,
            array_merge($this->variablesUsed, [$variable])
        );
    }
}
