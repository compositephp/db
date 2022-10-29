<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\Entity\Columns\AbstractColumn;
use Composite\DB\Helpers\ClassHelper;
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
            ->addNamespace(ClassHelper::extractNamespace($this->tableClass))
            ->addUse(AbstractTable::class)
            ->addUse(TableConfig::class)
            ->addUse($this->schema->class)
            ->addClass(ClassHelper::extractShortName($this->tableClass))
            ->setExtends(AbstractTable::class)
            ->setMethods($this->getMethods());
    }

    private function getMethods(): Methods
    {
        $methods = array_filter([
            $this->generateGetConfig(),
            $this->generateFindOne(),
            $this->generateFindAll(),
            $this->generateCountAll(),
        ]);
        return new Methods($methods);
    }

    protected function generateFindOne(): ?Method
    {
        $primaryColumns = array_map(
            fn(string $key): AbstractColumn => $this->schema->getColumn($key) ?? throw new \Exception("Primary key column `$key` not found in entity."),
            $this->tableConfig->primaryKeys
        );
        if (count($this->tableConfig->primaryKeys) === 1) {
            $body = 'return $this->createEntity($this->findByPkInternal(' . $this->buildVarsList($this->tableConfig->primaryKeys) . '));';
        } else {
            $body = 'return $this->createEntity($this->findOneInternal(' . $this->buildVarsList($this->tableConfig->primaryKeys) . '));';
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