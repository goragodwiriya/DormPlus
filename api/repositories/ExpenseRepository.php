<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class ExpenseRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param array $filters q, month (YYYY-MM), category
     * @param int $limit
     * @param int $offset
     * @return array{items: array, total: int}
     */
    public function paginate(
        int $propertyId,
        array $filters,
        int $limit,
        int $offset
    ): array {
        $where = ['property_id = :property_id'];
        $bindings = ['property_id' => $propertyId];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(title LIKE :q OR notes LIKE :q)';
            $bindings['q'] = '%'.$filters['q'].'%';
        }

        if (($filters['month'] ?? '') !== '') {
            $where[] = 'substr(expense_date, 1, 7) = :month';
            $bindings['month'] = $filters['month'];
        }

        if (($filters['category'] ?? '') !== '') {
            $where[] = 'category = :category';
            $bindings['category'] = $filters['category'];
        }

        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM expenses WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT * FROM expenses
             WHERE {$whereSql}
             ORDER BY date(expense_date) DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':'.$key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    /**
     * @param int $propertyId
     * @param int $id
     * @return mixed
     */
    public function find(int $propertyId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM expenses
             WHERE property_id = :property_id AND id = :id
             LIMIT 1'
        );

        $statement->execute(['property_id' => $propertyId, 'id' => $id]);
        $expense = $statement->fetch();

        return $expense ?: null;
    }

    /**
     * @param int $propertyId
     * @param array $data
     */
    public function create(int $propertyId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO expenses (
                property_id, title, category, amount, expense_date, notes
             ) VALUES (
                :property_id, :title, :category, :amount, :expense_date, :notes
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'title' => $data['title'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'notes' => $data['notes']
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE expenses SET
                title = :title,
                category = :category,
                amount = :amount,
                expense_date = :expense_date,
                notes = :notes,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'title' => $data['title'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'notes' => $data['notes']
        ]);
    }

    /**
     * @param int $id
     */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param int $propertyId
     * @param string $month YYYY-MM
     */
    public function monthTotal(int $propertyId, string $month): float
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses
             WHERE property_id = :property_id
               AND substr(expense_date, 1, 7) = :month'
        );

        $statement->execute(['property_id' => $propertyId, 'month' => $month]);

        return (float) $statement->fetchColumn();
    }
}
