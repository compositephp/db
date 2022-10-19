<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractCachedTable;
use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Schema;
use Nette\PhpGenerator\Helpers;
use Spiral\Reactor\Aggregator\Methods;
use Spiral\Reactor\Partial\Method;

class CachedTableClassBuilder extends AbstractTableClassBuilder
{
    public function getParentNamespace(): string
    {
        return AbstractCachedTable::class;
    }

    public function generate(): void
    {
        $this->file
            ->addNamespace(Helpers::extractNamespace($this->tableClass))
            ->addUse(AbstractEntity::class)
            ->addUse(AbstractCachedTable::class)
            ->addUse(Schema::class)
            ->addUse($this->schema->class)
            ->addClass(Helpers::extractShortName($this->tableClass))
            ->setExtends(AbstractCachedTable::class)
            ->setMethods($this->getMethods());
    }

    private function getMethods(): Methods
    {
        $methods = array_filter([
            $this->generateGetSchema(),
            $this->generateGetFlushCacheKeys(),
            $this->generateFindOne(),
            $this->generateFindAll(),
            $this->generateCountAll(),
        ]);
        return new Methods($methods);
    }

    protected function generateGetFlushCacheKeys(): Method
    {
        $method = (new Method('getFlushCacheKeys'))
            ->setProtected()
            ->setReturnType('array')
            ->addBody('return [')
            ->addBody('    $this->getListCacheKey(),')
            ->addBody('    $this->getCountCacheKey(),')
            ->addBody('];');

        $type = $this->schema->class . '|' . AbstractEntity::class;
        $method
            ->addParameter('entity')
            ->setType($type);
        return $method;
    }

    protected function generateFindOne(): ?Method
    {
        if (!$primaryColumns = $this->schema->getPrimaryKeyColumns()) {
            return null;
        }
        $primaryColumnVars = array_map(fn ($column) => $column->name, $primaryColumns);
        if (count($primaryColumns) === 1) {
            $body = 'return $this->createEntity($this->findByPkCachedInternal(' . $this->buildVarsList($primaryColumnVars) . '));';
        } else {
            $body = 'return $this->createEntity($this->findOneCachedInternal(' . $this->buildVarsList($primaryColumnVars) . '));';
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
            ->setBody('return $this->createEntities($this->findAllCachedInternal());');
    }

    protected function generateCountAll(): Method
    {
        return (new Method('countAll'))
            ->setPublic()
            ->setReturnType('int')
            ->setBody('return $this->countAllCachedInternal();');
    }
}