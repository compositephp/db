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
    protected InputInterface $input;
    protected OutputInterface $output;
    private ?QuestionHelper $questionHelper = null;

    private function showSuccess(string $text): int
    {
        $this->output->writeln("<fg=green>$text</fg=green>");
        return Command::SUCCESS;
    }

    private function showAlert(string $text): int
    {
        $this->output->writeln("<fg=yellow>$text</fg=yellow>");
        return Command::SUCCESS;
    }

    private function showInfo(string $text): void
    {
        $this->output->writeln("<fg=white>$text</fg=white>");
    }

    private function showError(string $text): int
    {
        $this->output->writeln("<fg=red>$text</fg=red>");
        return Command::INVALID;
    }

    protected function getQuestionHelper(): QuestionHelper
    {
        if ($this->questionHelper === null) {
            $this->questionHelper = new QuestionHelper();
        }
        return $this->questionHelper;
    }

    protected function ask(Question $question): mixed
    {
        return $this->getQuestionHelper()->ask($this->input, $this->output, $question);
    }

    private function saveClassToFile(string $class, string $content): bool
    {
        if (!$filePath = $this->getClassFilePath($class)) {
            return false;
        }
        $fileState = 'new';
        if (file_exists($filePath)) {
            $fileState = 'overwrite';
            if (!$this->input->getOption('force')
                && !$this->ask(new ConfirmationQuestion("File `$filePath` is already exists, do you want to overwrite it?[y/n]: "))) {
                return true;
            }
        }
        if (file_put_contents($filePath, $content)) {
            $this->showSuccess("File `$filePath` was successfully generated ($fileState)");
            return true;
        } else {
            $this->showError("Something went wrong can `$filePath` was successfully generated ($fileState)");
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
