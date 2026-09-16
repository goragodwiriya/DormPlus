<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DormPlus\Api\Core\ConflictException;
use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\ValidationException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\ContractRepository;
use DormPlus\Api\Repositories\MaintenanceRepository;
use DormPlus\Api\Repositories\PaymentRepository;
use DormPlus\Api\Repositories\RoomRepository;

final class RoomService
{
    public const STATUSES = ['available', 'occupied', 'maintenance', 'reserved'];
    public const TYPES = ['standard', 'deluxe', 'suite', 'studio'];

    /**
     * @param RoomRepository $rooms
     * @param ContractRepository $contracts
     * @param PaymentRepository $payments
     * @param MaintenanceRepository $maintenance
     */
    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly ContractRepository $contracts,
        private readonly PaymentRepository $payments,
        private readonly MaintenanceRepository $maintenance
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
        $result = $this->rooms->paginate($propertyId, $filters, $limit, $offset);
        $result['items'] = array_map([$this, 'normalize'], $result['items']);

        return $result;
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function get(int $propertyId, int $id): array
    {
        $room = $this->rooms->find($propertyId, $id);

        if ($room === null) {
            throw new NotFoundException('ไม่พบห้องพักที่ต้องการ');
        }

        return $this->normalize($room);
    }

    /**
     * รายละเอียดห้องพร้อมประวัติสัญญา การชำระเงิน และงานซ่อม
     *
     * @param int $propertyId
     * @param int $id
     */
    public function detail(int $propertyId, int $id): array
    {
        $room = $this->get($propertyId, $id);

        return [
            'room' => $room,
            'contracts' => $this->contracts->historyFor('room_id', $id),
            'payments' => $this->payments->historyFor('room_id', $id),
            'maintenance' => $this->maintenance->forRoom($id)
        ];
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function create(int $propertyId, array $input): array
    {
        $data = $this->validate($propertyId, $input);
        $id = $this->rooms->create($propertyId, $data);

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function update(int $propertyId, int $id, array $input): array
    {
        $existing = $this->get($propertyId, $id);
        $data = $this->validate($propertyId, $input, $id);

        $this->assertStatusAllowed($existing, $data['status']);
        $this->rooms->update($id, $data);

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function changeStatus(int $propertyId, int $id, array $input): array
    {
        $room = $this->get($propertyId, $id);

        $validator = new Validator($input);
        $validator->required('status', 'สถานะ')->in('status', self::STATUSES);
        $validator->validate();

        $this->assertStatusAllowed($room, $validator->value('status'));
        $this->rooms->updateStatus($id, $validator->value('status'));

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function delete(int $propertyId, int $id): void
    {
        $room = $this->get($propertyId, $id);

        if ($room['contract_id'] !== null) {
            throw new ConflictException(
                'ห้องนี้มีสัญญาเช่าที่ใช้งานอยู่ กรุณาสิ้นสุดสัญญาก่อนลบห้อง'
            );
        }

        if ($this->rooms->hasRelatedRecords($id)) {
            throw new ConflictException(
                'ห้องนี้มีประวัติสัญญา การชำระเงิน หรืองานซ่อม จึงไม่สามารถลบได้ '
                .'แนะนำให้เปลี่ยนสถานะเป็น "ซ่อมบำรุง" แทน'
            );
        }

        $this->rooms->delete($id);
    }

    /**
     * ตัวเลือกสำหรับฟอร์ม/ตัวกรอง
     *
     * @param int $propertyId
     */
    public function options(int $propertyId): array
    {
        return [
            'floors' => $this->rooms->floors($propertyId),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'rooms' => array_map(static fn(array $room): array => [
                 ...$room,
                'id' => (int) $room['id'],
                'floor' => (int) $room['floor'],
                'monthly_rent' => (float) $room['monthly_rent']
            ], $this->rooms->options($propertyId))
        ];
    }

    /**
     * @param int $propertyId
     * @param array $input
     * @param int|null $ignoreId
     */
    private function validate(int $propertyId, array $input, ?int $ignoreId = null): array
    {
        $validator = new Validator($input);

        $validator->required('room_number', 'เลขห้อง')->max('room_number', 20);
        $validator->required('floor', 'ชั้น')->integer('floor', 0, 200);
        $validator->optional('type', 'standard')->in('type', self::TYPES);
        $validator->optional('area')->numeric('area', 0, 10000);
        $validator->required('monthly_rent', 'ค่าเช่ารายเดือน')
            ->numeric('monthly_rent', 0, 10000000);
        $validator->optional('status', 'available')->in('status', self::STATUSES);
        $validator->optional('description')->max('description', 1000);

        if (
            !$validator->fails()
            && $this->rooms->numberExists(
                $propertyId,
                (string) $validator->value('room_number'),
                $ignoreId
            )
        ) {
            $validator->addError('room_number', 'มีเลขห้องนี้อยู่แล้ว');
        }

        $validator->validate();

        $data = $validator->values();
        $data['room_number'] = (string) $data['room_number'];

        return $data;
    }

    /**
     * ห้องที่มีสัญญา active ต้องคงสถานะ "มีผู้เช่า" (หรือซ่อมบำรุง)
     * และห้องที่ไม่มีสัญญาจะตั้งเป็น "มีผู้เช่า" ไม่ได้
     *
     * @param array $room
     * @param string $status
     */
    private function assertStatusAllowed(array $room, string $status): void
    {
        $hasContract = $room['contract_id'] !== null;

        if ($hasContract && in_array($status, ['available', 'reserved'], true)) {
            throw new ValidationException([
                'status' => 'ห้องนี้มีสัญญาเช่าที่ใช้งานอยู่ ต้องสิ้นสุดสัญญาก่อนจึงจะเปลี่ยนเป็นห้องว่างได้'
            ]);
        }

        if (!$hasContract && $status === 'occupied') {
            throw new ValidationException([
                'status' => 'ต้องสร้างสัญญาเช่าก่อน ห้องจึงจะมีสถานะ "มีผู้เช่า"'
            ]);
        }
    }

    /**
     * @param array $room
     */
    private function normalize(array $room): array
    {
        return [
             ...$room,
            'id' => (int) $room['id'],
            'floor' => (int) $room['floor'],
            'area' => $room['area'] !== null ? (float) $room['area'] : null,
            'monthly_rent' => (float) $room['monthly_rent'],
            'contract_id' => $room['contract_id'] !== null ? (int) $room['contract_id'] : null,
            'tenant_id' => $room['tenant_id'] !== null ? (int) $room['tenant_id'] : null,
            'tenant_name' => $room['first_name'] !== null
                ? trim($room['first_name'].' '.$room['last_name'])
                : null
        ];
    }
}
