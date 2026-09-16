<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

final class NotFoundException extends HttpException
{
    /**
     * @param string $message
     */
    public function __construct(string $message = 'ไม่พบข้อมูลที่ต้องการ')
    {
        parent::__construct('NOT_FOUND', $message, 404);
    }
}
