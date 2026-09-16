<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

use RuntimeException;

/**
 * ข้อผิดพลาดที่ส่งกลับให้ผู้ใช้ได้โดยตรง (ไม่ใช่ข้อผิดพลาดของระบบ)
 */
class HttpException extends RuntimeException
{
    /**
     * @param string $errorCode
     * @param string $message
     * @param int $status
     * @param array $fields
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status = 400,
        private readonly array $fields = []
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function fields(): array
    {
        return $this->fields;
    }
}
