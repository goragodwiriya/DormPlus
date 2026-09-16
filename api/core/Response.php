<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

final class Response
{
    /**
     * @param mixed $data
     * @param nullint $status
     * @param array $pagination
     */
    public static function success(
        mixed $data = null,
        int $status = 200,
        ?array $pagination = null
    ): never {
        $payload = [
            'success' => true,
            'data' => $data
        ];

        if ($pagination !== null) {
            $payload['pagination'] = $pagination;
        }

        self::json($payload, $status);
    }

    /**
     * @param string $code
     * @param string $message
     * @param int $status
     * @param array $fields
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $fields = []
    ): never {
        self::json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields
            ]
        ], $status);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    /**
     * @param array $payload
     * @param int $status
     */
    private static function json(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}