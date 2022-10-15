<?php declare(strict_types=1);

namespace Composite\DB\Commands;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes;
use Composite\DB\Entity\Schema;
use Composite\DB\Generator\CachedTableClassBuilder;
use Composite\DB\Generator\TableClassBuilder;
use Spiral\Reactor\FileDeclaration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class GenerateTableCommand extends Command
{
    use CommandHelperTrait;

    protected static $defaultName = 'composite-db:generate-table';

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity full class name')
            ->addArgument('table', InputArgument::OPTIONAL, 'Table full class name')
            ->addOption('cached', 'c', InputOption::VALUE_NONE, 'Generate cache version')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing table class file');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $entityClass = $this->input->getArgument('entity');
        $reflection = new \ReflectionClass($entityClass);

        if (!$reflection->isSubclassOf(AbstractEntity::class)) {
            return $this->showError("Class `$entityClass` must be subclass of " . AbstractEntity::class);
        }
        $schema = $entityClass::schema();
        $tableName = $schema->getTableName();
        $dbName = $schema->getDatabaseName();
        if (!$tableName || !$dbName) {
            return $this->showError("Entity `$entityClass` must have attribute " . Attributes\Table::class);
        }

        if (!$tableClass = $this->input->getArgument('table')) {
            $proposedClass = preg_replace('/\w+$/', 'Tables', $reflection->getNamespaceName()) . "\\{$tableName}Table";
            $tableClass = $this->ask(new ConfirmationQuestion("Enter table full class name [skip to use $proposedClass]: "));
            if (!$tableClass) {
                $tableClass = $proposedClass;
            }
        }

        if (!preg_match('/^(.+)\\\(\w+)$/', $tableClass, $matches)) {
            return $this->showError("Table class `$tableClass` is incorrect");
        }
        $tableNamespace = $matches[1];
        $tableClassShortName = $matches[2];

        $file = new FileDeclaration($tableNamespace);

        if ($this->input->getOption('cached')) {
            $template = new CachedTableClassBuilder(
                schema: $schema,
                tableClassName: $tableClassShortName,
            );
            $file->addUse(AbstractEntity::class);
        } else {
            $template = new TableClassBuilder(
                schema: $schema,
                tableClassName: $tableClassShortName,
            );
        }
        $template->generate();
        $file
            ->setDirectives('strict_types=1')
            ->addUse($template->getParentNamespace())
            ->addUse(Schema::class)
            ->addUse($entityClass)
            ->addElement($template->getClass());

        $fileState = 'new';
        if (!$filePath = $this->getClassFilePath($tableClass)) {
            return Command::FAILURE;
        }
        if (file_exists($filePath)) {
            if (!$this->input->getOption('force')) {
                return $this->showError("File `$filePath` already exists, use --force flag to overwrite it");
            }
            $fileState = 'overwrite';
        }
        file_put_contents($filePath, $file->render());
        return $this->showSuccess("File `$filePath` was successfully generated ($fileState)");
    }
}
