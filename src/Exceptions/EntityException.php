<?php declare(strict_types=1);

namespace Composite\DB\Exceptions;

class EntityException extends \Exception
{
    public function __construct(string $message = "", ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function fromThrowable(\Throwable $throwable): static
    {
        return new static('', $throwable);
    }

    public static function tableNotFound(string $entityClass): static
    {
        return new self("Table attribute not defined in entity `$entityClass`");
    }
}