<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

class ValidationException extends DomainException
{
    public function __construct(private array $errors)
    {
        parent::__construct('Validation xətası: ' . implode(', ', $errors));
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
