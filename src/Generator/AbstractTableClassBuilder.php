<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\Helpers\ClassHelper;
use Composite\DB\TableConfig;
use Composite\Entity\Columns\AbstractColumn;
use Composite\Entity\Schema;
use Doctrine\Inflector\Rules\English\InflectorFactory;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;

abstract class AbstractTableClassBuilder
{
    protected readonly PhpFile $file;
    protected readonly string $entityClassShortName;

    public function __construct(
        protected readonly string $tableClass,
        protected readonly Schema $schema,
        protected readonly TableConfig $tableConfig,
    )
    {
        $this->entityClassShortName = ClassHelper::extractShortName($this->schema->class);
        $this->file = new PhpFile();
    }

    abstract public function getParentNamespace(): string;
    abstract public function generate(): void;

    final public function getFileContent(): string
    {
        return (string)$this->file;
    }

    protected function generateGetConfig(): Method
    {
        return (new Method('getConfig'))
            ->setProtected()
            ->setReturnType(TableConfig::class)
            ->setBody('return TableConfig::fromEntitySchema(' . $this->entityClassShortName . '::schema());');
    }

    protected function buildVarsList(array $vars): string
    {
        if (count($vars) === 1) {
            $var = current($vars);
            return '$' . $var;
        }
        $vars = array_map(
            fn ($var) => "'$var' => \$" . $var,
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
                ->addParameter($column->name)
                ->setType($column->type);
        }
    }
}