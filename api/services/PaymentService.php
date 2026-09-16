<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DateTimeImmutable;
use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\ContractRepository;
use DormPlus\Api\Repositories\PaymentRepository;
use DormPlus\Api\Repositories\RoomRepository;
use DormPlus\Api\Repositories\TenantRepository;

final class PaymentService
{
    public const TYPES = ['rent', 'electricity', 'water', 'other'];
    public const STATUSES = ['paid', 'pending', 'cancelled'];

    private const TYPE_LABELS = [
        'rent' => 'ค่าเช่า',
        'electricity' => 'ค่าไฟ',
        'water' => 'ค่าน้ำ',
        'other' => 'ค่าใช้จ่ายอื่น'
    ];

    /**
     * @param PaymentRepository $payments
     * @param TenantRepository $tenants
     * @param RoomRepository $rooms
     * @param ContractRepository $contracts
     * @param NotificationService $notifications
     */
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly TenantRepository $tenants,
        private readonly RoomRepository $rooms,
        private readonly ContractRepository $contracts,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * @param int $propertyId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     */
    public function list(int $propertyId, array $filters, int $limit, int $offset): array
    {
        $result = $this->payments->paginate($propertyId, $filters, $limit, $offset);
        $result['items'] = array_map([$this, 'normalize'], $result['items']);

        return $result;
    }

    /**
     * สรุปงวด (เดือน/ปี) สำหรับส่วนหัวของหน้าการเงิน
     *
     * @param int $propertyId
     * @param string $month YYYY-MM
     */
    public function summary(int $propertyId, string $month): array
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $summary = $this->payments->periodSummary($propertyId, $year, $monthNumber);

        return [
            'month' => $month,
            'paid_total' => (float) $summary['paid_total'],
            'pending_total' => (float) $summary['pending_total'],
            'paid_count' => (int) $summary['paid_count'],
            'pending_count' => (int) $summary['pending_count'],
            'cancelled_count' => (int) $summary['cancelled_count']
        ];
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function get(int $propertyId, int $id): array
    {
        $payment = $this->payments->find($propertyId, $id);

        if ($payment === null) {
            throw new NotFoundException('ไม่พบรายการชำระเงิน');
        }

        return $this->normalize($payment);
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function create(int $propertyId, array $input): array
    {
        $data = $this->validate($propertyId, $input);
        $id = $this->payments->create($propertyId, $data);
        $payment = $this->get($propertyId, $id);

        if ($payment['status'] === 'paid') {
            $this->notifications->notify(
                $propertyId,
                'payment',
                'ผู้เช่าชำระ'.self::TYPE_LABELS[$payment['payment_type']],
                sprintf(
                    'ห้อง %s - %s บาท',
                    $payment['room_number'],
                    number_format($payment['amount'], 0)
                ),
                'payment',
                $id
            );
        }

        return $payment;
    }

    /**
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function update(int $propertyId, int $id, array $input): array
    {
        $this->get($propertyId, $id);
        $data = $this->validate($propertyId, $input);
        $this->payments->update($id, $data);

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function cancel(int $propertyId, int $id): array
    {
        $payment = $this->get($propertyId, $id);
        $this->payments->update($id, [ ...$payment, 'status' => 'cancelled']);

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function delete(int $propertyId, int $id): void
    {
        $this->get($propertyId, $id);
        $this->payments->delete($id);
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    private function validate(int $propertyId, array $input): array
    {
        $now = new DateTimeImmutable();
        $validator = new Validator($input);

        $validator->required('tenant_id', 'ผู้เช่า')->integer('tenant_id', 1);
        $validator->optional('room_id')->integer('room_id', 1);
        $validator->optional('contract_id')->integer('contract_id', 1);
        $validator->required('amount', 'จำนวนเงิน')->numeric('amount', 0, 100000000);
        $validator->optional('payment_type', 'rent')->in('payment_type', self::TYPES);
        $validator->optional('payment_date')->dateTime('payment_date');
        $validator->optional('period_month', (int) $now->format('n'))
            ->integer('period_month', 1, 12);
        $validator->optional('period_year', (int) $now->format('Y'))
            ->integer('period_year', 2000, 2200);
        $validator->optional('reference')->max('reference', 60);
        $validator->optional('notes')->max('notes', 1000);
        $validator->optional('status', 'paid')->in('status', self::STATUSES);

        if ($validator->fails()) {
            $validator->validate();
        }

        $data = $validator->values();
        $tenant = $this->tenants->find($propertyId, $data['tenant_id']);

        if ($tenant === null) {
            $validator->addError('tenant_id', 'ไม่พบผู้เช่าที่เลือก');
            $validator->validate();
        }

        // ห้องและสัญญา: ถ้าไม่ระบุ ใช้จากสัญญาที่ active ของผู้เช่า
        if ($data['room_id'] === null) {
            $data['room_id'] = $tenant['room_id'] !== null ? (int) $tenant['room_id'] : null;
        }

        if ($data['room_id'] === null) {
            $validator->addError('room_id', 'ผู้เช่ารายนี้ยังไม่มีห้อง กรุณาเลือกห้องพัก');
        } elseif ($this->rooms->find($propertyId, $data['room_id']) === null) {
            $validator->addError('room_id', 'ไม่พบห้องพักที่เลือก');
        }

        if ($data['contract_id'] === null && $tenant['contract_id'] !== null) {
            $data['contract_id'] = (int) $tenant['contract_id'];
        } elseif (
            $data['contract_id'] !== null
            && $this->contracts->find($propertyId, $data['contract_id']) === null
        ) {
            $validator->addError('contract_id', 'ไม่พบสัญญาเช่าที่อ้างถึง');
        }

        if ($data['status'] === 'paid' && $data['payment_date'] === null) {
            $data['payment_date'] = $now->format('Y-m-d H:i:s');
        }

        if ($data['status'] !== 'paid') {
            $data['payment_date'] = $data['status'] === 'pending' ? null : $data['payment_date'];
        }

        if ($data['reference'] === null) {
            $data['reference'] = sprintf(
                'PAY-%04d%02d-%s',
                $data['period_year'],
                $data['period_month'],
                strtoupper(bin2hex(random_bytes(3)))
            );
        }

        $validator->validate();

        return $data;
    }

    /**
     * @param array $payment
     */
    private function normalize(array $payment): array
    {
        return [
             ...$payment,
            'id' => (int) $payment['id'],
            'tenant_id' => (int) $payment['tenant_id'],
            'room_id' => (int) $payment['room_id'],
            'contract_id' => $payment['contract_id'] !== null ? (int) $payment['contract_id'] : null,
            'amount' => (float) $payment['amount'],
            'period_month' => (int) $payment['period_month'],
            'period_year' => (int) $payment['period_year'],
            'tenant_name' => trim($payment['first_name'].' '.$payment['last_name']),
            'payment_type_label' => self::TYPE_LABELS[$payment['payment_type']] ?? $payment['payment_type']
        ];
    }
}
