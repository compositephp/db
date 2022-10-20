<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Spiral\Reactor\Aggregator\EnumCases;
use Spiral\Reactor\FileDeclaration;
use Spiral\Reactor\Partial\EnumCase;

class EnumClassBuilder
{
    public function __construct(
        private readonly string $enumClass,
        private readonly array $cases,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function getClassContent(): string
    {
        $enumCases = [];
        foreach ($this->cases as $case) {
            $enumCases[] = new EnumCase($case);
        }
        $file = new FileDeclaration();
        $file
            ->addEnum($this->enumClass)
            ->setCases(new EnumCases($enumCases));

        return $file->render();
    }

    /**
     * @throws \Exception
     */
    private function getVars(): array
    {
        if (!preg_match('/^(.+)\\\(\w+)$/', $this->enumClass, $matches)) {
            throw new \Exception("Entity class `$this->enumClass` is incorrect");
        }
        return [
            'phpOpener' => '<?php declare(strict_types=1);',
            'enumNamespace' => $matches[1],
            'enumClassShortname' => $matches[2],
            'cases' => $this->cases,
        ];
    }
}