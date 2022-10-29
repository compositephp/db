<?php declare(strict_types=1);

namespace Composite\DB\Commands;

use Composite\DB\Attributes;
use Composite\DB\Generator\CachedTableClassBuilder;
use Composite\DB\Generator\TableClassBuilder;
use Composite\DB\TableConfig;
use Composite\Entity\AbstractEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

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
        /** @var class-string<AbstractEntity> $entityClass */
        $entityClass = $input->getArgument('entity');
        $reflection = new \ReflectionClass($entityClass);

        if (!$reflection->isSubclassOf(AbstractEntity::class)) {
            return $this->showError($output, "Class `$entityClass` must be subclass of " . AbstractEntity::class);
        }
        $schema = $entityClass::schema();
        $tableConfig = TableConfig::fromEntitySchema($schema);
        $tableName = $tableConfig->tableName;

        if (!$tableClass = $input->getArgument('table')) {
            $proposedClass = preg_replace('/\w+$/', 'Tables', $reflection->getNamespaceName()) . "\\{$tableName}Table";
            $tableClass = $this->ask(
                $input,
                $output,
                new Question("Enter table full class name [skip to use $proposedClass]: ")
            );
            if (!$tableClass) {
                $tableClass = $proposedClass;
            }
        }
        if (str_starts_with($tableClass, '\\')) {
            $tableClass = substr($tableClass, 1);
        }

        if (!preg_match('/^(.+)\\\(\w+)$/', $tableClass)) {
            return $this->showError($output, "Table class `$tableClass` is incorrect");
        }
        if ($input->getOption('cached')) {
            $template = new CachedTableClassBuilder(
                tableClass: $tableClass,
                schema: $schema,
                tableConfig: $tableConfig,
            );
        } else {
            $template = new TableClassBuilder(
                tableClass: $tableClass,
                schema: $schema,
                tableConfig: $tableConfig,
            );
        }
        $template->generate();
        $file = $template->getFile();

        $fileState = 'new';
        if (!$filePath = $this->getClassFilePath($tableClass)) {
            return Command::FAILURE;
        }
        if (file_exists($filePath)) {
            if (!$input->getOption('force')) {
                return $this->showError($output, "File `$filePath` already exists, use --force flag to overwrite it");
            }
            $fileState = 'overwrite';
        }
        file_put_contents($filePath, $file->render());
        return $this->showSuccess($output, "File `$filePath` was successfully generated ($fileState)");
    }
}
