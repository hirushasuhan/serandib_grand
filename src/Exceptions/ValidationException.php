<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(array $errors, string $message = "Validation failed")
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
