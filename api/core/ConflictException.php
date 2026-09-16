<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

final class ConflictException extends HttpException
{
    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct('CONFLICT', $message, 409);
    }
}
