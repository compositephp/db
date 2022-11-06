<?php declare(strict_types=1);

namespace Composite\DB\Commands;

use Composite\DB\ConnectionManager;
use Composite\DB\Generator\EntityClassBuilder;
use Composite\DB\Generator\EnumClassBuilder;
use Composite\DB\Generator\Schema\SQLEnum;
use Composite\DB\Generator\Schema\SQLSchema;
use Composite\DB\Helpers\ClassHelper;
use Doctrine\Inflector\Rules\English\InflectorFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class GenerateEntityCommand extends Command
{
    use CommandHelperTrait;

    protected static $defaultName = 'composite-db:generate-entity';

    protected function configure(): void
    {
        $this
            ->addArgument('connection', InputArgument::REQUIRED, 'Connection name')
            ->addArgument('table', InputArgument::REQUIRED, 'Table name')
            ->addArgument('entity', InputArgument::OPTIONAL, 'Entity full class name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'If existing file should be overwritten');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionName = $input->getArgument('connection');
        $tableName = $input->getArgument('table');
        $connection = ConnectionManager::getConnection($connectionName);

        if (!$entityClass = $input->getArgument('entity')) {
            $entityClass = $this->ask($input, $output, new Question('Enter entity full class name: '));
        }
        $entityClass = str_replace('\\\\', '\\', $entityClass);

        $schema = SQLSchema::generate($connection, $tableName);
        $enums = [];
        foreach ($schema->enums as $columnName => $sqlEnum) {
            if ($enumClass = $this->generateEnum($input, $output, $entityClass, $sqlEnum)) {
                $enums[$columnName] = $enumClass;
            }
        }
        $entityBuilder = new EntityClassBuilder($schema, $connectionName, $entityClass, $enums);
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