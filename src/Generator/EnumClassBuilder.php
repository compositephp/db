<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Nette\PhpGenerator\EnumCase;
use Nette\PhpGenerator\PhpFile;

class EnumClassBuilder
{
    public function __construct(
        private readonly string $enumClass,
        private readonly array $cases,
    ) {}

    /**
     * @throws \Exception
     */
    public function getClassContent(): string
    {
        $enumCases = [];
        foreach ($this->cases as $case) {
            $enumCases[] = new EnumCase($case);
        }
        $file = new PhpFile();
        $file
            ->setStrictTypes()
            ->addEnum($this->enumClass)
            ->setCases($enumCases);

        return (string)$file;
    }
}