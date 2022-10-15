<?php declare(strict_types=1);

namespace Composite\DB\Generator;

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
        return $this->renderTemplate('EnumTemplate', $this->getVars());
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

    private function renderTemplate(string $templateName, array $variables = []): string
    {
        $filePath = implode(
            DIRECTORY_SEPARATOR,
            [
                __DIR__,
                'Templates',
                "$templateName.php",
            ]
        );
        extract($variables, EXTR_SKIP);
        ob_start();
        include $filePath;
        return ob_get_clean();
    }
}