<?php declare(strict_types=1);

namespace Composite\DB\Commands;

use Composite\DB\Generator\EntityClassBuilder;
use Composite\DB\Generator\EnumClassBuilder;
use Composite\DB\Generator\Schema\SQLEnum;
use Composite\DB\Generator\Schema\SQLSchema;
use Composite\DB\Helpers\ClassHelper;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\TableInterface;
use Doctrine\Inflector\Rules\English\InflectorFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class GenerateEntityCommand extends Command
{
    use CommandHelperTrait;

    protected static $defaultName = 'composite-db:generate-entity';

    public function __construct(
        private readonly DatabaseProviderInterface $dbProvider,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('db', InputArgument::REQUIRED, 'Database name')
            ->addArgument('table', InputArgument::OPTIONAL, 'Table name')
            ->addArgument('entity', InputArgument::OPTIONAL, 'Entity full class name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'If existing file should be overwritten');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbName = $input->getArgument('db');
        $db = $this->dbProvider->database($dbName);

        if (!$tableName = $input->getArgument('table')) {
            $tableName = $this->ask(
                $input,
                $output,
                new ChoiceQuestion(
                    'Pick Table: ',
                    array_map(
                        fn(TableInterface $Table) => $Table->getName(),
                        $db->getTables()
                    )
                )
            );
        }
        if (!$db->table($tableName)->exists()) {
            return $this->showError($output, "Table `$tableName` does not exist");
        }
        if (!$entityClass = $input->getArgument('entity')) {
            $entityClass = $this->ask($input, $output, new Question('Enter entity full class name: '));
        }
        $entityClass = str_replace('\\\\', '\\', $entityClass);

        $schema = SQLSchema::generate($db, $tableName);
        $enums = [];
        foreach ($schema->enums as $columnName => $sqlEnum) {
            if ($enumClass = $this->generateEnum($input, $output, $entityClass, $sqlEnum)) {
                $enums[$columnName] = $enumClass;
            }
        }
        $entityBuilder = new EntityClassBuilder($schema, $dbName, $entityClass, $enums);
        $content = $entityBuilder->getClassContent();

        $this->saveClassToFile($input, $output, $entityClass, $content);
        return Command::SUCCESS;
    }

    private function generateEnum(InputInterface $input, OutputInterface $output, string $entityClass, SQLEnum $enum): ?string
    {
        $name = $enum->name;
        $values = $enum->values;
        $this->showAlert($output, "Found enum `$name` with values [" . implode(', ', $values) . "]");
        if (!$this->ask($input, $output, new ConfirmationQuestion('Do you want to generate Enum class?[y/n]: '))) {
            return null;
        }
        $enumShortClassName = ucfirst((new InflectorFactory())->build()->camelize($name));
        $entityNamespace = ClassHelper::extractNamespace($entityClass);
        $proposedClass = $entityNamespace . '\\Enums\\' . $enumShortClassName;
        $enumClass = $this->ask(
            $input,
            $output,
            new Question("Enter enum full class name [skip to use $proposedClass]: ")
        );
        if (!$enumClass) {
            $enumClass = $proposedClass;
        }
        $enumClassBuilder = new EnumClassBuilder($enumClass, $values);

        $content = $enumClassBuilder->getClassContent();
        if (!$this->saveClassToFile($input, $output, $enumClass, $content)) {
            return null;
        }
        return $enumClass;
    }
}