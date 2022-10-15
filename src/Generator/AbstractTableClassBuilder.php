<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\Entity\Columns\AbstractColumn;
use Composite\DB\Entity\Schema;
use Doctrine\Inflector\Rules\English\InflectorFactory;
use Spiral\Reactor\ClassDeclaration;
use Spiral\Reactor\Partial\Method;

abstract class AbstractTableClassBuilder
{
    protected readonly ClassDeclaration $class;
    protected readonly string $entityClassShortName;

    public function __construct(
        protected readonly Schema $schema,
        string $tableClassName,
    )
    {
        $parentShortName = substr(strrchr($this->getParentNamespace(), "\\"), 1);
        $this->entityClassShortName = substr(strrchr($schema->class, "\\"), 1);
        $this->class = new ClassDeclaration($tableClassName, $parentShortName);
    }

    abstract public function getParentNamespace(): string;
    abstract public function generate(): void;

    final public function getClass(): ClassDeclaration
    {
        return $this->class;
    }

    protected function generateGetSchema(): void
    {
        $this->class
            ->method('getSchema')
            ->setProtected()
            ->setStatic()
            ->setReturn('Schema')
            ->setSource('return ' . $this->entityClassShortName . '::schema();');
    }

    protected function buildVarsList(array $vars): string
    {
        if (count($vars) === 1) {
            $var = current($vars);
            return '$' . $var;
        }
        $vars = array_map(
            fn ($var) => "'$var' => \$" . (new InflectorFactory())->build()->camelize($var),
            $vars
        );
        return '[' . implode(', ', $vars) . ']';
    }

    /**
     * @param AbstractColumn[] $columns
     */
    protected function addMethodParameters(Method $method, array $columns): void
    {
        foreach ($columns as $column) {
            $method
                ->parameter($column->name)
                ->setType($column->type);
        }
    }
}