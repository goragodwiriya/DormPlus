<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

final class ValidationException extends HttpException
{
    /**
     * @param array $fields
     * @param string $message
     */
    public function __construct(
        array $fields,
        string $message = 'กรุณาตรวจสอบข้อมูลที่กรอก'
    ) {
        parent::__construct('VALIDATION_ERROR', $message, 422, $fields);
    }
}
