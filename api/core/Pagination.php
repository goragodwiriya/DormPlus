<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

/**
 * ข้อมูลการแบ่งหน้าสำหรับ collection endpoints
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    /**
     * @param int $page
     * @param int $perPage
     */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage
    ) {
    }

    /**
     * อ่าน page / per_page (หรือ limit) จาก query string
     *
     * @param Request $request
     * @param int $defaultPerPage
     */
    public static function fromRequest(
        Request $request,
        int $defaultPerPage = self::DEFAULT_PER_PAGE
    ): self {
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, [
            'options' => ['default' => 1, 'min_range' => 1]
        ]);

        $perPage = filter_var(
            $request->query('per_page', $request->query('limit', $defaultPerPage)),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => $defaultPerPage,
                    'min_range' => 1,
                    'max_range' => self::MAX_PER_PAGE
                ]
            ]
        );

        return new self((int) $page, (int) $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @param int $total
     */
    public function toArray(int $total): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'total_pages' => (int) max(1, ceil($total / $this->perPage))
        ];
    }
}
