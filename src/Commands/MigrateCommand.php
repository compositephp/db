<?php declare(strict_types=1);

namespace Composite\DB\Commands;

use Composite\DB\AbstractEntity;
use Composite\DB\Migrations\CycleBridge;
use Composite\DB\Migrations\MigrationImage;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Config\MigrationConfig;
use Cycle\Migrations\FileRepository;
use Cycle\Migrations\Migrator;
use Cycle\Migrations\Atomizer;
use Spiral\Tokenizer\Tokenizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MigrateCommand extends Command
{
    use CommandHelperTrait;

    protected static $defaultName = 'composite-db:migrate';

    private Migrator $migrator;

    public function __construct(
        private readonly DatabaseProviderInterface $dbProvider,
        private readonly MigrationConfig $migrationConfig,
        private readonly Tokenizer $tokenizer
    )
    {
        $this->migrator = new Migrator($migrationConfig, $dbProvider, new FileRepository($migrationConfig));
        $this->migrator->configure();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::OPTIONAL, 'Entity full class name')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Checks existing database tables and generate migration files')
            ->addOption('migrate', 'm', InputOption::VALUE_NONE, 'Applies migration files to database if new migration exists');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $entityClass = $this->input->getArgument('entity');
        $generate = $input->getOption('generate');
        $migrate = $input->getOption('migrate');
        $anyOptionProvided = $migrate || $generate;

        if ($entityClass) {
            if (!class_exists($entityClass)) {
                return $this->showError("Class `{$entityClass}` does not exists");
            }
            if (!is_subclass_of($entityClass, AbstractEntity::class)) {
                return $this->showError("Class `{$entityClass}` must be subclass of " . AbstractEntity::class);
            }
            $generate = true;
        }
        if (!$generate) {
            $generate = $this->ask(new ConfirmationQuestion('Do you want to generate migrations?[y/n]: '));
        }
        if ($generate) {
            $newMigrationGenerated = false;
            if ($entityClass) {
                $classes = [new \ReflectionClass($entityClass)];
            } else {
                $classes = $this->tokenizer->classLocator()->getClasses(AbstractEntity::class);
            }
            foreach ($classes as $class) {
                if ($filename = $this->generateTableMigration($class)) {
                    $newMigrationGenerated = true;
                    $this->showSuccess("Migration `" . basename($filename) . "` successfully generated");
                }
            }
            if (!$newMigrationGenerated) {
                $this->showSuccess('No new migrations were generated');
            }
        }
        if (!$anyOptionProvided) {
            $migrate = $this->ask(new ConfirmationQuestion('Do you want to apply migrations?[y/n]: '));
        }
        if ($migrate) {
            $newMigrationApplied = false;
            do {
                if ($migration = $this->migrator->run()) {
                    $this->showSuccess("Migration `" . $migration->getState()->getName() . "` successfully applied");
                    $newMigrationApplied = true;
                }
            } while ($migration !== null);
            if (!$newMigrationApplied) {
                $this->showSuccess('No new migrations were applied');
            }
        }
        return Command::SUCCESS;
    }

    private function generateTableMigration(\ReflectionClass $reflectionClass): ?string
    {
        try {
            $bridge = new CycleBridge($reflectionClass);
            $table = $bridge->generateCycleTable($this->dbProvider);
        } catch (\Exception $e) {
            $this->showError($e->getMessage());
            return null;
        }
        if (!$table->getComparator()->hasChanges()) {
            return null;
        }
        $atomizer = new Atomizer\Atomizer(new Atomizer\Renderer());
        $atomizer->addTable($table);
        $image = new MigrationImage($this->migrationConfig, $bridge->dbName);
        $image->build($atomizer);

        return $this->migrator->getRepository()->registerMigration(
            $image->getName(),
            $image->getClass()->getName(),
            $image->getFile()->render()
        );
    }
}
