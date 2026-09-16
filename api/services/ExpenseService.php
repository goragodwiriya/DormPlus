<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DateTimeImmutable;
use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\ExpenseRepository;

final class ExpenseService
{
    public const CATEGORIES = [
        'utilities', 'salary', 'maintenance', 'supplies', 'tax', 'other'
    ];

    /**
     * @param ExpenseRepository $expenses
     */
    public function __construct(private readonly ExpenseRepository $expenses)
    {
    }

    /**
     * @param int $propertyId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     */
    public function list(int $propertyId, array $filters, int $limit, int $offset): array
    {
        $result = $this->expenses->paginate($propertyId, $filters, $limit, $offset);
        $result['items'] = array_map([$this, 'normalize'], $result['items']);

        if (($filters['month'] ?? '') !== '') {
            $result['month_total'] = $this->expenses->monthTotal($propertyId, $filters['month']);
        }

        return $result;
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function get(int $propertyId, int $id): array
    {
        $expense = $this->expenses->find($propertyId, $id);

        if ($expense === null) {
            throw new NotFoundException('ไม่พบรายการรายจ่าย');
        }

        return $this->normalize($expense);
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function create(int $propertyId, array $input): array
    {
        $id = $this->expenses->create($propertyId, $this->validate($input));

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function update(int $propertyId, int $id, array $input): array
    {
        $this->get($propertyId, $id);
        $this->expenses->update($id, $this->validate($input));

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function delete(int $propertyId, int $id): void
    {
        $this->get($propertyId, $id);
        $this->expenses->delete($id);
    }

    /**
     * @param array $input
     */
    private function validate(array $input): array
    {
        $validator = new Validator($input);

        $validator->required('title', 'รายการ')->max('title', 120);
        $validator->optional('category', 'other')->in('category', self::CATEGORIES);
        $validator->required('amount', 'จำนวนเงิน')->numeric('amount', 0, 100000000);
        $validator->optional('expense_date', (new DateTimeImmutable())->format('Y-m-d'))
            ->date('expense_date');
        $validator->optional('notes')->max('notes', 1000);
        $validator->validate();

        return $validator->values();
    }

    /**
     * @param array $expense
     */
    private function normalize(array $expense): array
    {
        return [
             ...$expense,
            'id' => (int) $expense['id'],
            'amount' => (float) $expense['amount']
        ];
    }
}
