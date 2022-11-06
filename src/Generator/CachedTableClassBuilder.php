<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractCachedTable;
use Composite\DB\TableConfig;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Columns\AbstractColumn;
use Composite\DB\Helpers\ClassHelper;
use Nette\PhpGenerator\Method;

class CachedTableClassBuilder extends AbstractTableClassBuilder
{
    public function getParentNamespace(): string
    {
        return AbstractCachedTable::class;
    }

    public function generate(): void
    {
        $this->file
            ->addNamespace(ClassHelper::extractNamespace($this->tableClass))
            ->addUse(AbstractEntity::class)
            ->addUse(AbstractCachedTable::class)
            ->addUse(TableConfig::class)
            ->addUse($this->schema->class)
            ->addClass(ClassHelper::extractShortName($this->tableClass))
            ->setExtends(AbstractCachedTable::class)
            ->setMethods($this->getMethods());
    }

    private function getMethods(): array
    {
        return array_filter([
            $this->generateGetConfig(),
            $this->generateGetFlushCacheKeys(),
            $this->generateFindOne(),
            $this->generateFindAll(),
            $this->generateCountAll(),
        ]);
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
        $primaryColumns = array_map(
            fn(string $key): AbstractColumn => $this->schema->getColumn($key) ?? throw new \Exception("Primary key column `$key` not found in entity."),
            $this->tableConfig->primaryKeys
        );
        if (count($this->tableConfig->primaryKeys) === 1) {
            $body = 'return $this->createEntity($this->findByPkCachedInternal(' . $this->buildVarsList($this->tableConfig->primaryKeys) . '));';
        } else {
            $body = 'return $this->createEntity($this->findOneCachedInternal(' . $this->buildVarsList($this->tableConfig->primaryKeys) . '));';
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
            ->setComment('@return ' . $this->entityClassShortName . '[]')
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