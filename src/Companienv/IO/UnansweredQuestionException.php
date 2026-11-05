<?php

declare(strict_types=1);

namespace Companienv\IO;

use RuntimeException;

class UnansweredQuestionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cannot answer a question without a default in non-interactive mode.');
    }
}
