<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractCachedTable;

class CachedTableClassBuilder extends AbstractTableClassBuilder
{
    public function getParentNamespace(): string
    {
        return AbstractCachedTable::class;
    }

    public function generate(): void
    {
        $this->generateGetSchema();
        $this->generateGetFlushCacheKeys();
        $this->generateFindOne();
        $this->generateFindAll();
        $this->generateCountAll();
    }

    protected function generateGetFlushCacheKeys(): void
    {
        $method = $this->class->method('getFlushCacheKeys');
        $method
            ->parameter('entity')
            ->setType($this->entityClassShortName . '|AbstractEntity');

        $method
            ->setProtected()
            ->setReturn('array')
            ->setSource([
                'return [',
                '    $this->getListCacheKey(),',
                '    $this->getCountCacheKey(),',
                '];',
            ]);
    }

    protected function generateFindOne(): void
    {
        if (!$primaryColumns = $this->schema->getPrimaryKeyColumns()) {
            return;
        }
        $primaryColumnVars = array_map(fn ($column) => $column->name, $primaryColumns);
        $method = $this->class->method('findOne');
        $method
            ->setPublic()
            ->setReturn('?' . $this->entityClassShortName)
            ->setSource('return $this->createEntity($this->findOneCachedInternal(' . $this->buildVarsList($primaryColumnVars) . '));');
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
            ->setSource('return $this->createEntities($this->findAllCachedInternal());');
    }

    protected function generateCountAll(): void
    {
        $method = $this->class->method('countAll');
        $method
            ->setPublic()
            ->setReturn('int')
            ->setSource('return $this->countAllCachedInternal();');
    }
}