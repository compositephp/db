<?php declare(strict_types=1);

namespace Composite\DB\Migrations;

use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Atomizer\Atomizer;
use Cycle\Migrations\Config\MigrationConfig;
use Cycle\Migrations\Migration;
use Spiral\Reactor\ClassDeclaration;
use Spiral\Reactor\FileDeclaration;

class MigrationImage
{
    const HASH_DELIMITER = '__';
    protected ClassDeclaration $class;
    protected FileDeclaration $file;
    protected ?string $database = null;
    protected string $name;
    protected string $content;
    protected string $hash;

    public function __construct(
        protected MigrationConfig $migrationConfig,
        string $database
    ) {
        $this->class = new ClassDeclaration('newMigration', 'Migration');

        $this->class->method('up')->setReturn('void')->setPublic();
        $this->class->method('down')->setReturn('void')->setPublic();

        $this->file = new FileDeclaration($migrationConfig->getNamespace());
        $this->file->addUse(Migration::class);
        $this->file->addElement($this->class);
        $this->file->setNamespace('');

        $this->setDatabase($database);
    }

    public function getClass(): ClassDeclaration
    {
        return $this->class;
    }

    public function getFile(): FileDeclaration
    {
        return $this->file;
    }

    public function setDatabase(string $database): void
    {
        $this->database = $database;
        $this->class->constant('DATABASE')->setProtected()->setValue($database);
    }

    public function build(Atomizer $atomizer): void
    {
        $atomizer->declareChanges($this->getClass()->method('up')->getSource());
        $atomizer->revertChanges($this->getClass()->method('down')->getSource());

        $upContent = $this->getClass()->method('up')->getSource()->render();
        $downContent = $this->getClass()->method('down')->getSource()->render();
        $this->hash = md5($upContent . $downContent);

        $classNameParts = ['Migration', ucfirst($this->database)];
        $actionNameParts = [$this->database];
        foreach ($atomizer->getTables() as $table) {
            $classNameParts[] = ucfirst($table->getName());
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
        $actionName = substr(implode('_', $actionNameParts), 0, 128);
        $this->name = $actionName . self::HASH_DELIMITER . $this->hash;

        $classNameParts[] = $this->hash;
        $this->class->setName(implode('', $classNameParts));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
