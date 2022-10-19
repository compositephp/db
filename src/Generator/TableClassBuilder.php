<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractTable;
use Composite\DB\Entity\Schema;
use Nette\PhpGenerator\Helpers;
use Spiral\Reactor\Aggregator\Methods;
use Spiral\Reactor\Partial\Method;

class TableClassBuilder extends AbstractTableClassBuilder
{
    public function getParentNamespace(): string
    {
        return AbstractTable::class;
    }

    public function generate(): void
    {
        $this->file
            ->addNamespace(Helpers::extractNamespace($this->tableClass))
            ->addUse(AbstractTable::class)
            ->addUse(Schema::class)
            ->addUse($this->schema->class)
            ->addClass(Helpers::extractShortName($this->tableClass))
            ->setExtends(AbstractTable::class)
            ->setMethods($this->getMethods());
    }

    private function getMethods(): Methods
    {
        $methods = array_filter([
            $this->generateGetSchema(),
            $this->generateFindOne(),
            $this->generateFindAll(),
            $this->generateCountAll(),
        ]);
        return new Methods($methods);
    }

    protected function generateFindOne(): ?Method
    {
        if (!$primaryColumns = $this->schema->getPrimaryKeyColumns()) {
            return null;
        }
        $primaryColumnVars = array_map(fn ($column) => $column->name, $primaryColumns);
        if (count($primaryColumns) === 1) {
            $body = 'return $this->createEntity($this->findByPkInternal(' . $this->buildVarsList($primaryColumnVars) . '));';
        } else {
            $body = 'return $this->createEntity($this->findOneInternal(' . $this->buildVarsList($primaryColumnVars) . '));';
        }
        $method = (new Method('findByPk'))
            ->setPublic()
            ->setReturnType($this->schema->class)
            ->setReturnNullable()
            ->setBody($body);
        $this->addMethodParameters($method, $primaryColumns);
        return $method;
    }

    protected function generateFindAll(): Method
    {
        return (new Method('findAll'))
            ->setPublic()
            ->setComment([
                '@return ' . $this->entityClassShortName . '[]',
            ])
            ->setReturnType('array')
            ->setBody('return $this->createEntities($this->findAllInternal());');
    }

    protected function generateCountAll(): Method
    {
        return (new Method('countAll'))
            ->setPublic()
            ->setReturnType('int')
            ->setBody('return $this->countAllInternal();');
    }
}