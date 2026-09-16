<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

use DateTimeImmutable;

/**
 * ตรวจสอบข้อมูลที่รับจาก client แบบ fluent
 *
 * $validator = new Validator($request->json());
 * $validator->required('room_number', 'เลขห้อง')->max('room_number', 20);
 * $validator->validate();
 * $data = $validator->values();
 */
final class Validator
{
    private array $errors = [];
    private array $values = [];

    /**
     * @param array $input
     */
    public function __construct(private readonly array $input)
    {
    }

    /**
     * @param string $field
     * @param string $label
     */
    public function required(string $field, string $label): self
    {
        $value = $this->raw($field);

        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, "กรุณากรอก{$label}");
        }

        $this->values[$field] = is_string($value) ? trim($value) : $value;

        return $this;
    }

    /**
     * ฟิลด์ที่ไม่บังคับ: เก็บค่าเป็น null เมื่อว่าง
     *
     * @param string $field
     * @param mixed $default
     */
    public function optional(string $field, mixed $default = null): self
    {
        $value = $this->raw($field);

        if ($value === null || $value === '') {
            $this->values[$field] = $default;
        } else {
            $this->values[$field] = is_string($value) ? trim($value) : $value;
        }

        return $this;
    }

    /**
     * @param string $field
     * @param int $length
     */
    public function max(string $field, int $length): self
    {
        $value = $this->values[$field] ?? null;

        if (is_string($value) && mb_strlen($value) > $length) {
            $this->addError($field, "ต้องไม่เกิน {$length} ตัวอักษร");
        }

        return $this;
    }

    /**
     * @param string $field
     * @param float|null $min
     * @param float|null $max
     */
    public function numeric(string $field, ?float $min = null, ?float $max = null): self
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return $this;
        }

        if (!is_numeric($value)) {
            $this->addError($field, 'ต้องเป็นตัวเลข');
            return $this;
        }

        $number = (float) $value;

        if ($min !== null && $number < $min) {
            $this->addError($field, "ต้องไม่น้อยกว่า {$min}");
        }

        if ($max !== null && $number > $max) {
            $this->addError($field, "ต้องไม่เกิน {$max}");
        }

        $this->values[$field] = $number;

        return $this;
    }

    /**
     * @param string $field
     * @param int|null $min
     * @param int|null $max
     */
    public function integer(string $field, ?int $min = null, ?int $max = null): self
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return $this;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, 'ต้องเป็นจำนวนเต็ม');
            return $this;
        }

        $number = (int) $value;

        if ($min !== null && $number < $min) {
            $this->addError($field, "ต้องไม่น้อยกว่า {$min}");
        }

        if ($max !== null && $number > $max) {
            $this->addError($field, "ต้องไม่เกิน {$max}");
        }

        $this->values[$field] = $number;

        return $this;
    }

    /**
     * @param string $field
     * @param array $allowed
     */
    public function in(string $field, array $allowed): self
    {
        $value = $this->values[$field] ?? null;

        if ($value !== null && !in_array($value, $allowed, true)) {
            $this->addError($field, 'ค่าที่เลือกไม่ถูกต้อง');
        }

        return $this;
    }

    /**
     * รูปแบบ YYYY-MM-DD
     *
     * @param string $field
     */
    public function date(string $field): self
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return $this;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->addError($field, 'รูปแบบวันที่ไม่ถูกต้อง (YYYY-MM-DD)');
        }

        return $this;
    }

    /**
     * รูปแบบ YYYY-MM-DD HH:MM หรือ YYYY-MM-DD HH:MM:SS
     *
     * @param string $field
     */
    public function dateTime(string $field): self
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return $this;
        }

        $value = str_replace('T', ' ', (string) $value);

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                $this->values[$field] = $date->format('Y-m-d H:i:s');
                return $this;
            }
        }

        $this->addError($field, 'รูปแบบวันที่และเวลาไม่ถูกต้อง');

        return $this;
    }

    /**
     * @param string $field
     */
    public function email(string $field): self
    {
        $value = $this->values[$field] ?? null;

        if ($value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, 'รูปแบบอีเมลไม่ถูกต้อง');
        }

        return $this;
    }

    /**
     * @param string $field
     */
    public function phone(string $field): self
    {
        $value = $this->values[$field] ?? null;

        if ($value !== null && !preg_match('/^[0-9+\-\s()]{6,20}$/', (string) $value)) {
            $this->addError($field, 'รูปแบบเบอร์โทรไม่ถูกต้อง');
        }

        return $this;
    }

    /**
     * @param string $field
     */
    public function boolean(string $field): self
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return $this;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($parsed === null) {
            $this->addError($field, 'ค่าต้องเป็น true หรือ false');
            return $this;
        }

        $this->values[$field] = $parsed ? 1 : 0;

        return $this;
    }

    /**
     * เพิ่มข้อผิดพลาดจากกฎเฉพาะของ service
     *
     * @param string $field
     * @param string $message
     */
    public function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * โยน ValidationException เมื่อมีข้อผิดพลาด
     */
    public function validate(): void
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
    }

    /**
     * ค่าที่ผ่านการตรวจสอบและแปลงชนิดแล้ว
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @param string $field
     * @return mixed
     */
    public function value(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    /**
     * @param string $field
     * @return mixed
     */
    private function raw(string $field): mixed
    {
        return $this->input[$field] ?? null;
    }
}
