<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node;
use PhpParser\Node\Expr;

class ExpressionSubstitutor
{
    /**
     * @param array<string, Expr> $substitutions
     */
    public function substitute(Expr $expression, array $substitutions): Expr
    {
        $substituted = $this->substituteNode($expression, $substitutions);

        return $substituted instanceof Expr ? $substituted : clone $expression;
    }

    /**
     * @param array<string, Expr> $substitutions
     */
    private function substituteNode(Node $node, array $substitutions, ?Node $parent = null, ?string $subNodeName = null): Node
    {
        if ($node instanceof Expr\Variable
            && is_string($node->name)
            && isset($substitutions[$node->name])
            && $this->canSubstituteVariable($parent, $subNodeName)
        ) {
            return clone $substitutions[$node->name];
        }

        $clone = clone $node;
        foreach ($clone->getSubNodeNames() as $childName) {
            $child = $clone->$childName;
            if ($child instanceof Node) {
                $clone->$childName = $this->substituteNode($child, $substitutions, $clone, $childName);
                continue;
            }

            if (!is_array($child)) {
                continue;
            }

            foreach ($child as $index => $item) {
                if ($item instanceof Node) {
                    $child[$index] = $this->substituteNode($item, $substitutions, $clone, $childName);
                }
            }

            $clone->$childName = $child;
        }

        return $clone;
    }

    private function canSubstituteVariable(?Node $parent, ?string $subNodeName): bool
    {
        if ($parent instanceof Node\Expr\ClosureUse && $subNodeName === 'var') {
            return false;
        }

        if ($parent instanceof Node\Param && $subNodeName === 'var') {
            return false;
        }

        if (($parent instanceof Node\Expr\Assign || $parent instanceof Node\Expr\AssignRef) && $subNodeName === 'var') {
            return false;
        }

        if ($parent instanceof Node\Stmt\StaticVar && $subNodeName === 'var') {
            return false;
        }

        if ($parent instanceof Node\Stmt\Foreach_ && in_array($subNodeName, ['keyVar', 'valueVar'], true)) {
            return false;
        }

        if ($parent instanceof Node\Stmt\Catch_ && $subNodeName === 'var') {
            return false;
        }

        return true;
    }
}
