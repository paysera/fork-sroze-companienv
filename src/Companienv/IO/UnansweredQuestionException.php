<?php

declare(strict_types=1);

namespace Companienv\IO;

use RuntimeException;

class UnansweredQuestionException extends RuntimeException
{
    public function __construct(string $question)
    {
        parent::__construct(sprintf(
            'Cannot answer "%s" in non-interactive mode: the question has no default.',
            trim(strip_tags($question))
        ));
    }
}
