<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DateTimeImmutable;
use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\ValidationException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\ContractRepository;
use DormPlus\Api\Repositories\RoomRepository;
use DormPlus\Api\Repositories\TenantRepository;
use PDO;
use Throwable;

final class ContractService
{
    public const STATUSES = ['active', 'expired', 'terminated', 'pending'];

    /**
     * @param PDO $pdo
     * @param ContractRepository $contracts
     * @param RoomRepository $rooms
     * @param TenantRepository $tenants
     * @param NotificationService $notifications
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly ContractRepository $contracts,
        private readonly RoomRepository $rooms,
        private readonly TenantRepository $tenants,
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
        $result = $this->contracts->paginate($propertyId, $filters, $limit, $offset);
        $result['items'] = array_map([$this, 'normalize'], $result['items']);
        $result['status_counts'] = $this->contracts->statusCounts($propertyId);

        return $result;
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function get(int $propertyId, int $id): array
    {
        $contract = $this->contracts->find($propertyId, $id);

        if ($contract === null) {
            throw new NotFoundException('ไม่พบสัญญาเช่า');
        }

        return $this->normalize($contract);
    }

    /**
     * สร้างสัญญา + จัดผู้เช่าเข้าห้อง + เปลี่ยนสถานะห้อง ใน transaction เดียว
     *
     * @param int $propertyId
     * @param array $input
     */
    public function create(int $propertyId, array $input): array
    {
        $data = $this->validate($propertyId, $input);

        $this->pdo->beginTransaction();

        try {
            $id = $this->contracts->create($propertyId, $data);

            if ($data['status'] === 'active') {
                $this->rooms->updateStatus($data['room_id'], 'occupied');
                $this->tenants->updateStatus($data['tenant_id'], 'active');
            } elseif ($data['status'] === 'pending') {
                $this->rooms->updateStatus($data['room_id'], 'reserved');
            }

            $contract = $this->get($propertyId, $id);

            $this->notifications->notify(
                $propertyId,
                'contract',
                'สร้างสัญญาเช่าใหม่',
                "ห้อง {$contract['room_number']} - {$contract['tenant_name']}",
                'contract',
                $id
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $contract;
    }

    /**
     * แก้ไขเงื่อนไขสัญญา (ผู้เช่าและห้องเปลี่ยนไม่ได้ ให้สิ้นสุดแล้วสร้างใหม่)
     *
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function update(int $propertyId, int $id, array $input): array
    {
        $existing = $this->get($propertyId, $id);

        $data = $this->validate($propertyId, [
             ...$input,
            'tenant_id' => $existing['tenant_id'],
            'room_id' => $existing['room_id']
        ], $id);

        $this->pdo->beginTransaction();

        try {
            $this->contracts->update($id, $data);
            $this->syncRoomStatus($existing, $data['status']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->get($propertyId, $id);
    }

    /**
     * สิ้นสุดสัญญา: ยกเลิก (terminated) หรือหมดอายุ (expired) และคืนห้องเป็นว่าง
     *
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function end(int $propertyId, int $id, array $input): array
    {
        $contract = $this->get($propertyId, $id);

        if (!in_array($contract['status'], ['active', 'pending'], true)) {
            throw new ValidationException(['status' => 'สัญญานี้สิ้นสุดไปแล้ว']);
        }

        $validator = new Validator($input);
        $validator->optional('status', 'terminated')->in('status', ['terminated', 'expired']);
        $validator->optional('end_date')->date('end_date');
        $validator->validate();

        $this->pdo->beginTransaction();

        try {
            $this->contracts->updateStatus(
                $id,
                $validator->value('status'),
                $validator->value('end_date')
            );
            $this->releaseRoom($contract);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->get($propertyId, $id);
    }

    /**
     * ต่อสัญญา: ปิดสัญญาเดิมเป็นหมดอายุ แล้วสร้างสัญญาใหม่ต่อจากวันสิ้นสุดเดิม
     *
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function renew(int $propertyId, int $id, array $input): array
    {
        $contract = $this->get($propertyId, $id);

        if ($contract['status'] !== 'active') {
            throw new ValidationException(['status' => 'ต่อสัญญาได้เฉพาะสัญญาที่ใช้งานอยู่']);
        }

        $previousEnd = new DateTimeImmutable($contract['end_date']);
        $defaultStart = $previousEnd->modify('+1 day');

        $validator = new Validator($input);
        $validator->optional('start_date', $defaultStart->format('Y-m-d'))->date('start_date');
        $validator->required('end_date', 'วันสิ้นสุดสัญญาใหม่')->date('end_date');
        $validator->optional('monthly_rent', $contract['monthly_rent'])
            ->numeric('monthly_rent', 0, 10000000);
        $validator->optional('deposit', $contract['deposit'])->numeric('deposit', 0, 10000000);
        $validator->optional('electricity_rate', $contract['electricity_rate'])
            ->numeric('electricity_rate', 0, 1000);
        $validator->optional('water_rate', $contract['water_rate'])
            ->numeric('water_rate', 0, 1000);
        $validator->optional('notes')->max('notes', 1000);

        if (
            !$validator->fails()
            && $validator->value('end_date') <= $validator->value('start_date')
        ) {
            $validator->addError('end_date', 'วันสิ้นสุดต้องอยู่หลังวันเริ่มสัญญา');
        }

        $validator->validate();
        $data = $validator->values();

        $this->pdo->beginTransaction();

        try {
            $this->contracts->updateStatus($id, 'expired');

            $newId = $this->contracts->create($propertyId, [
                'tenant_id' => $contract['tenant_id'],
                'room_id' => $contract['room_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit' => $data['deposit'],
                'electricity_rate' => $data['electricity_rate'],
                'water_rate' => $data['water_rate'],
                'status' => 'active',
                'notes' => $data['notes']
            ]);

            $this->rooms->updateStatus($contract['room_id'], 'occupied');

            $this->notifications->notify(
                $propertyId,
                'contract',
                'ต่อสัญญาเช่า',
                "ห้อง {$contract['room_number']} - {$contract['tenant_name']} ถึง {$data['end_date']}",
                'contract',
                $newId
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->get($propertyId, $newId);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function delete(int $propertyId, int $id): void
    {
        $contract = $this->get($propertyId, $id);

        $this->pdo->beginTransaction();

        try {
            $this->contracts->delete($id);

            if (in_array($contract['status'], ['active', 'pending'], true)) {
                $this->releaseRoom($contract);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * สัญญาที่ใกล้หมดอายุ
     *
     * @param int $propertyId
     * @param int $days
     */
    public function expiring(int $propertyId, int $days): array
    {
        return array_map([$this, 'normalize'], $this->contracts->expiringWithin($propertyId, $days));
    }

    /**
     * @param int $propertyId
     * @param array $input
     * @param int|null $ignoreId
     */
    private function validate(int $propertyId, array $input, ?int $ignoreId = null): array
    {
        $validator = new Validator($input);

        $validator->required('tenant_id', 'ผู้เช่า')->integer('tenant_id', 1);
        $validator->required('room_id', 'ห้องพัก')->integer('room_id', 1);
        $validator->required('start_date', 'วันเริ่มสัญญา')->date('start_date');
        $validator->required('end_date', 'วันสิ้นสุดสัญญา')->date('end_date');
        $validator->required('monthly_rent', 'ค่าเช่ารายเดือน')
            ->numeric('monthly_rent', 0, 10000000);
        $validator->optional('deposit', 0)->numeric('deposit', 0, 10000000);
        $validator->optional('electricity_rate', 0)->numeric('electricity_rate', 0, 1000);
        $validator->optional('water_rate', 0)->numeric('water_rate', 0, 1000);
        $validator->optional('status', 'active')->in('status', self::STATUSES);
        $validator->optional('notes')->max('notes', 1000);

        if ($validator->fails()) {
            $validator->validate();
        }

        $data = $validator->values();

        if ($data['end_date'] < $data['start_date']) {
            $validator->addError('end_date', 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มสัญญา');
        }

        $room = $this->rooms->find($propertyId, $data['room_id']);

        if ($room === null) {
            $validator->addError('room_id', 'ไม่พบห้องพักที่เลือก');
        }

        $tenant = $this->tenants->find($propertyId, $data['tenant_id']);

        if ($tenant === null) {
            $validator->addError('tenant_id', 'ไม่พบผู้เช่าที่เลือก');
        } elseif ($tenant['status'] === 'blacklisted') {
            $validator->addError('tenant_id', 'ผู้เช่ารายนี้อยู่ในบัญชีดำ');
        }

        if (in_array($data['status'], ['active', 'pending'], true)) {
            if ($room !== null && $this->contracts->activeForRoom($data['room_id'], $ignoreId) !== null) {
                $validator->addError('room_id', 'ห้องนี้มีสัญญาเช่าที่ใช้งานอยู่แล้ว');
            }

            if ($room !== null && $ignoreId === null && $room['status'] === 'maintenance') {
                $validator->addError('room_id', 'ห้องนี้อยู่ระหว่างซ่อมบำรุง');
            }

            if ($tenant !== null && $this->contracts->activeForTenant($data['tenant_id'], $ignoreId) !== null) {
                $validator->addError('tenant_id', 'ผู้เช่ารายนี้มีสัญญาเช่าที่ใช้งานอยู่แล้ว');
            }
        }

        $validator->validate();

        return $data;
    }

    /**
     * ปรับสถานะห้องให้สอดคล้องเมื่อสถานะสัญญาเปลี่ยนจากการแก้ไข
     *
     * @param array $contract
     * @param string $newStatus
     */
    private function syncRoomStatus(array $contract, string $newStatus): void
    {
        if ($newStatus === $contract['status']) {
            return;
        }

        if ($newStatus === 'active') {
            $this->rooms->updateStatus($contract['room_id'], 'occupied');
            $this->tenants->updateStatus($contract['tenant_id'], 'active');
        } elseif ($newStatus === 'pending') {
            $this->rooms->updateStatus($contract['room_id'], 'reserved');
        } else {
            $this->releaseRoom($contract);
        }
    }

    /**
     * คืนห้องเป็นว่าง และตั้งผู้เช่าเป็นไม่ใช้งานหากไม่มีสัญญาอื่น
     *
     * @param array $contract
     */
    private function releaseRoom(array $contract): void
    {
        if ($this->contracts->activeForRoom($contract['room_id'], $contract['id']) === null) {
            $this->rooms->updateStatus($contract['room_id'], 'available');
        }

        if ($this->contracts->activeForTenant($contract['tenant_id'], $contract['id']) === null) {
            $this->tenants->updateStatus($contract['tenant_id'], 'inactive');
        }
    }

    /**
     * @param array $contract
     */
    private function normalize(array $contract): array
    {
        return [
             ...$contract,
            'id' => (int) $contract['id'],
            'tenant_id' => (int) $contract['tenant_id'],
            'room_id' => (int) $contract['room_id'],
            'monthly_rent' => (float) $contract['monthly_rent'],
            'deposit' => (float) ($contract['deposit'] ?? 0),
            'electricity_rate' => (float) ($contract['electricity_rate'] ?? 0),
            'water_rate' => (float) ($contract['water_rate'] ?? 0),
            'days_remaining' => isset($contract['days_remaining'])
                ? (int) $contract['days_remaining']
                : null,
            'tenant_name' => trim(($contract['first_name'] ?? '').' '.($contract['last_name'] ?? ''))
        ];
    }
}
