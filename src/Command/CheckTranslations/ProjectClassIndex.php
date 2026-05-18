<?php

namespace Efabrica\TranslationsAutomatization\Command\CheckTranslations;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

class ProjectClassIndex
{
    /** @var array<string, ProjectClassDefinition> */
    private array $classes = [];

    public function addClass(ProjectClassDefinition $classDefinition): void
    {
        $this->classes[$classDefinition->className] = $classDefinition;
    }

    public function findClass(string $className): ?ProjectClassDefinition
    {
        if (isset($this->classes[$className])) {
            return $this->classes[$className];
        }

        $shortClassName = $this->normalizeClassName($className);

        return $this->classes[$shortClassName] ?? null;
    }

    public function findMethod(string $className, string $methodName): ?ProjectMethodDefinition
    {
        $class = $this->findClass($className);
        while ($class !== null) {
            if (isset($class->methods[$methodName])) {
                return $class->methods[$methodName];
            }

            $class = $class->parentClassName !== null ? $this->findClass($class->parentClassName) : null;
        }

        return null;
    }

    /**
     * @return array<string, ExpressionEvaluationResult>
     */
    public function findClassConstants(string $className): array
    {
        $class = $this->findClass($className);

        return $class !== null ? $class->constantValues : [];
    }

    public function findParentClassName(string $className): ?string
    {
        $class = $this->findClass($className);

        return $class !== null ? $class->parentClassName : null;
    }

    public function findConstantValue(string $className, string $constantName): ?ExpressionEvaluationResult
    {
        $class = $this->findClass($className);
        while ($class !== null) {
            if (isset($class->constantValues[$constantName])) {
                return $class->constantValues[$constantName];
            }

            $class = $class->parentClassName !== null ? $this->findClass($class->parentClassName) : null;
        }

        return null;
    }

    /**
     * @param Node[] $ast
     */
    public static function fromAst(array $ast, TranslationKeyExpressionResolver $resolver): self
    {
        $index = new self();
        foreach ($ast as $node) {
            self::collectClasses($index, $node);
        }

        foreach ($index->classes as $classDefinition) {
            $index->resolveClassConstants($classDefinition->className, $resolver, []);
        }

        return $index;
    }

    private function resolveClassConstants(string $className, TranslationKeyExpressionResolver $resolver, array $resolving): array
    {
        $classDefinition = $this->findClass($className);
        if ($classDefinition === null) {
            return [];
        }

        if ($classDefinition->constantValues !== []) {
            return $classDefinition->constantValues;
        }

        if (isset($resolving[$classDefinition->className])) {
            return [];
        }

        $resolving[$classDefinition->className] = true;
        $constants = $classDefinition->parentClassName !== null
            ? $this->resolveClassConstants($classDefinition->parentClassName, $resolver, $resolving)
            : [];

        foreach ($classDefinition->constantExpressions as $constantName => $expression) {
            $constants[$constantName] = $resolver
                ->withClassConstants($constants)
                ->withClassContext($this, $classDefinition->className)
                ->resolve($expression);
        }

        $classDefinition->constantValues = $constants;

        return $constants;
    }

    private function normalizeClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts) ?: $className;
    }

    private static function collectClasses(self $index, Node $node): void
    {
        if ($node instanceof Class_) {
            $className = $node->name !== null ? $node->name->toString() : null;
            if ($className !== null) {
                $parentClassName = $node->extends !== null ? $node->extends->toString() : null;
                $classDefinition = new ProjectClassDefinition($className, $parentClassName);
                foreach ($node->stmts as $statement) {
                    if ($statement instanceof Node\Stmt\ClassConst) {
                        foreach ($statement->consts as $const) {
                            $classDefinition->constantExpressions[$const->name->toString()] = $const->value;
                        }
                    }

                    if ($statement instanceof ClassMethod) {
                        $parameterNames = [];
                        $parameterTypes = [];
                        foreach ($statement->params as $parameter) {
                            if (!$parameter->var instanceof Node\Expr\Variable || !is_string($parameter->var->name)) {
                                continue;
                            }

                            $parameterNames[] = $parameter->var->name;
                            if ($parameter->type instanceof Node\Name) {
                                $parameterTypes[$parameter->var->name] = $parameter->type->toString();
                            }
                        }

                        $classDefinition->methods[$statement->name->toString()] = new ProjectMethodDefinition(
                            $className,
                            $statement->name->toString(),
                            $parameterNames,
                            $parameterTypes,
                            $statement->stmts ?? [],
                            $statement->returnType instanceof Node\Name ? $statement->returnType->toString() : null
                        );
                    }
                }

                $index->addClass($classDefinition);
            }
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $child = $node->$subNodeName;
            if ($child instanceof Node) {
                self::collectClasses($index, $child);
                continue;
            }

            if (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node) {
                        self::collectClasses($index, $item);
                    }
                }
            }
        }
    }
}
