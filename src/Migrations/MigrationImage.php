<?php declare(strict_types=1);

namespace Composite\DB\Migrations;

use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Atomizer\Atomizer;
use Cycle\Migrations\Config\MigrationConfig;
use Cycle\Migrations\Migration;
use Spiral\Reactor\Aggregator\Methods;
use Spiral\Reactor\FileDeclaration;
use Spiral\Reactor\Partial\Method;

class MigrationImage
{
    private const HASH_DELIMITER = '__';

    public readonly FileDeclaration $file;
    public readonly string $name;
    public readonly string $className;

    public function __construct(
        private readonly MigrationConfig $migrationConfig,
        private readonly Atomizer $atomizer,
        private readonly string $database
    ) {
        $upMethod = (new Method('up'));
        $downMethod = (new Method('down'));
        $atomizer->declareChanges($upMethod);
        $atomizer->revertChanges($downMethod);

        $hash = md5($upMethod->getBody() . $downMethod->getBody());
        $actionNameParts = $this->getActionNameParts();
        $classNameParts = $this->getClassNameParts();
        $classNameParts[] = $hash;

        $actionName = substr(implode('_', $actionNameParts), 0, 128);

        $this->name = $actionName . self::HASH_DELIMITER . $hash;
        $this->className = implode('', $classNameParts);

        $this->file = new FileDeclaration();
        $this->file
            ->addNamespace($this->migrationConfig->getNamespace())
            ->addUse(Migration::class)
            ->addClass($this->className)
            ->setExtends(Migration::class)
            ->setMethods(new Methods([$upMethod, $downMethod]))
            ->addConstant('DATABASE', $this->database)->setProtected();
    }

    /**
     * @return string[]
     */
    public function getActionNameParts(): array
    {
        $actionNameParts = [$this->database];
        foreach ($this->atomizer->getTables() as $table) {
            if ($table->getStatus() === AbstractTable::STATUS_NEW) {
                $actionNameParts[] = 'create_' . $table->getName();
            } elseif ($table->getStatus() === AbstractTable::STATUS_DECLARED_DROPPED) {
                $actionNameParts[] = 'drop_' . $table->getName();
            } elseif ($table->getComparator()->isRenamed()) {
                $actionNameParts[] = 'rename_' . $table->getInitialName();
            } else {
                $actionNameParts[] = 'alter_' . $table->getName();
                $comparator = $table->getComparator();

                foreach ($comparator->addedColumns() as $column) {
                    $actionNameParts[] = 'add_' . $column->getName();
                }
                foreach ($comparator->droppedColumns() as $column) {
                    $actionNameParts[] = 'rm_' . $column->getName();
                }
                foreach ($comparator->alteredColumns() as $column) {
                    $actionNameParts[] = 'alt_' . $column[0]->getName();
                }
                foreach ($comparator->addedIndexes() as $index) {
                    $actionNameParts[] = 'add_idx_' . $index->getName();
                }
                foreach ($comparator->droppedIndexes() as $index) {
                    $actionNameParts[] = 'rm_idx_' . $index->getName();
                }
                foreach ($comparator->alteredIndexes() as $index) {
                    $actionNameParts[] = 'alt_idx_' . $index[0]->getName();
                }
                foreach ($comparator->addedForeignKeys() as $fk) {
                    $actionNameParts[] = 'add_fk_' . $fk->getName();
                }
                foreach ($comparator->droppedForeignKeys() as $fk) {
                    $actionNameParts[] = 'rm_fk_' . $fk->getName();
                }
                foreach ($comparator->alteredForeignKeys() as $fk) {
                    $actionNameParts[] = 'alt_fk_' . $fk[0]->getName();
                }
            }
        }
        return $actionNameParts;
    }

    /**
     * @return string[]
     */
    private function getClassNameParts(): array
    {
        $classNameParts = ['Migration', ucfirst($this->database)];
        foreach ($this->atomizer->getTables() as $table) {
            $classNameParts[] = ucfirst($table->getName());
        }
        return $classNameParts;
    }
}
