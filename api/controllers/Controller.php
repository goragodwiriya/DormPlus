<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Request;

/**
 * ตัวช่วยที่ controller ทุกตัวใช้ร่วมกัน
 */
abstract class Controller
{
    protected function propertyId(): int
    {
        return (int) $_SESSION['property_id'];
    }

    protected function userId(): int
    {
        return (int) $_SESSION['user_id'];
    }

    /**
     * อ่าน {id} จาก route และตรวจว่าเป็นจำนวนเต็มบวก
     *
     * @param array $parameters
     * @param string $key
     */
    protected function id(array $parameters, string $key = 'id'): int
    {
        $value = $parameters[$key] ?? '';

        if (!ctype_digit((string) $value) || (int) $value < 1) {
            throw new NotFoundException();
        }

        return (int) $value;
    }

    /**
     * ดึงค่าตัวกรองจาก query string เฉพาะ key ที่อนุญาต (ตัดช่องว่าง, จำกัดความยาว)
     *
     * @param Request $request
     * @param array $keys
     */
    protected function filters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            $value = $request->query($key);

            if ($value === null || is_array($value)) {
                continue;
            }

            $filters[$key] = mb_substr(trim((string) $value), 0, 120);
        }

        return $filters;
    }
}
