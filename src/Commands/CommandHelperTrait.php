<?php declare(strict_types=1);

namespace Composite\DB\Commands;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

trait CommandHelperTrait
{
    private function showSuccess(OutputInterface $output, string $text): int
    {
        $output->writeln("<fg=green>$text</fg=green>");
        return Command::SUCCESS;
    }

    private function showAlert(OutputInterface $output, string $text): int
    {
        $output->writeln("<fg=yellow>$text</fg=yellow>");
        return Command::SUCCESS;
    }

    private function showError(OutputInterface $output, string $text): int
    {
        $output->writeln("<fg=red>$text</fg=red>");
        return Command::INVALID;
    }

    protected function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
    {
        return (new QuestionHelper())->ask($input, $output, $question);
    }

    private function saveClassToFile(InputInterface $input, OutputInterface $output, string $class, string $content): bool
    {
        if (!$filePath = $this->getClassFilePath($class)) {
            return false;
        }
        $fileState = 'new';
        if (file_exists($filePath)) {
            $fileState = 'overwrite';
            if (!$input->getOption('force')
                && !$this->ask($input, $output, new ConfirmationQuestion("File `$filePath` is already exists, do you want to overwrite it?[y/n]: "))) {
                return true;
            }
        }
        if (file_put_contents($filePath, $content)) {
            $this->showSuccess($output, "File `$filePath` was successfully generated ($fileState)");
            return true;
        } else {
            $this->showError($output, "Something went wrong can `$filePath` was successfully generated ($fileState)");
            return false;
        }
    }

    protected function getClassFilePath(string $class): ?string
    {
        $class = trim($class, '\\');
        $namespaceParts = explode('\\', $class);

        $loaders = ClassLoader::getRegisteredLoaders();
        $matchedPrefixes = $matchedDirs = [];
        foreach ($loaders as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $dir) {
                $prefixParts = explode('\\', trim($prefix, '\\'));
                foreach ($namespaceParts as $i => $namespacePart) {
                    if (!isset($prefixParts[$i]) || $prefixParts[$i] !== $namespacePart) {
                        break;
                    }
                    if (!isset($matchedPrefixes[$prefix])) {
                        $matchedPrefixes[$prefix] = 0;
                        $matchedDirs[$prefix] = $dir;
                    }
                    $matchedPrefixes[$prefix] += 1;
                }
            }
        }
        if (empty($matchedPrefixes)) {
            throw new \Exception("Failed to determine directory for class `$class` from psr4 autoloading");
        }
        arsort($matchedPrefixes);
        $prefix = key($matchedPrefixes);
        $dirs = $matchedDirs[$prefix];

        $namespaceParts = explode('\\', str_replace($prefix, '', $class));
        $filename = array_pop($namespaceParts) . '.php';

        $relativeDir = implode(
            DIRECTORY_SEPARATOR,
            array_merge(
                $dirs,
                $namespaceParts,
            )
        );
        if (!$realDir = realpath($relativeDir)) {
            $dirCreateResult = mkdir($relativeDir, 0755, true);
            if (!$dirCreateResult) {
                throw new \Exception("Directory `$relativeDir` not exists and failed to create it, please create it manually.");
            }
            $realDir = realpath($relativeDir);
        }
        return $realDir . DIRECTORY_SEPARATOR . $filename;
    }
}
