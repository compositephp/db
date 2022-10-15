<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractTable;

class TableClassBuilder extends AbstractTableClassBuilder
{
    public function getParentNamespace(): string
    {
        return AbstractTable::class;
    }

    public function generate(): void
    {
        $this->generateGetSchema();
        $this->generateFindOne();
        $this->generateFindAll();
        $this->generateCountAll();
    }

    protected function generateFindOne(): void
    {
        if (!$primaryColumns = $this->schema->getPrimaryKeyColumns()) {
            return;
        }
        $primaryColumnVars = array_map(fn ($column) => $column->name, $primaryColumns);
        $method = $this->class->method('findByPk');
        if (count($primaryColumns) === 1) {
            $source = 'return $this->createEntity($this->findByPkInternal(' . $this->buildVarsList($primaryColumnVars) . '));';
        } else {
            $source = 'return $this->createEntity($this->findOneInternal(' . $this->buildVarsList($primaryColumnVars) . '));';
        }
        $method
            ->setPublic()
            ->setReturn('?' . $this->entityClassShortName)
            ->setSource($source);
        $this->addMethodParameters($method, $primaryColumns);
    }

    protected function generateFindAll(): void
    {
        $method = $this->class->method('findAll');
        $method
            ->setPublic()
            ->setComment([
                '@return ' . $this->entityClassShortName . '[]',
            ])
            ->setReturn('array')
            ->setSource('return $this->createEntities($this->findAllInternal());');
    }

    protected function generateCountAll(): void
    {
        $method = $this->class->method('countAll');
        $method
            ->setPublic()
            ->setReturn('int')
            ->setSource('return $this->countAllInternal();');
    }
}