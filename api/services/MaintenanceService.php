<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DateTimeImmutable;
use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\MaintenanceRepository;
use DormPlus\Api\Repositories\RoomRepository;
use DormPlus\Api\Repositories\TenantRepository;

final class MaintenanceService
{
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    /**
     * @param MaintenanceRepository $maintenance
     * @param RoomRepository $rooms
     * @param TenantRepository $tenants
     * @param NotificationService $notifications
     */
    public function __construct(
        private readonly MaintenanceRepository $maintenance,
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
        $result = $this->maintenance->paginate($propertyId, $filters, $limit, $offset);
        $result['items'] = array_map([$this, 'normalize'], $result['items']);
        $result['status_counts'] = $this->maintenance->statusCounts($propertyId);

        return $result;
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function get(int $propertyId, int $id): array
    {
        $request = $this->maintenance->find($propertyId, $id);

        if ($request === null) {
            throw new NotFoundException('ไม่พบรายการแจ้งซ่อม');
        }

        return $this->normalize($request);
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function create(int $propertyId, array $input): array
    {
        $data = $this->validate($propertyId, $input);
        $data['status'] = $data['status'] ?? 'pending';
        $data['reported_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $data['started_at'] = $data['status'] === 'in_progress' ? $data['reported_at'] : null;
        $data['completed_at'] = null;

        $id = $this->maintenance->create($propertyId, $data);
        $request = $this->get($propertyId, $id);

        $this->notifications->notify(
            $propertyId,
            'maintenance',
            'มีแจ้งซ่อมใหม่',
            "ห้อง {$request['room_number']} - {$request['title']}",
            'maintenance',
            $id
        );

        return $request;
    }

    /**
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function update(int $propertyId, int $id, array $input): array
    {
        $existing = $this->get($propertyId, $id);
        $data = $this->validate($propertyId, [ ...$existing, ...$input]);
        $data = $this->applyStatusTimestamps($existing, $data);

        $this->maintenance->update($id, $data);

        return $this->get($propertyId, $id);
    }

    /**
     * เปลี่ยนสถานะ / มอบหมายช่าง / บันทึกค่าใช้จ่าย โดยไม่ต้องส่งข้อมูลทั้งหมด
     *
     * @param int $propertyId
     * @param int $id
     * @param array $input
     */
    public function patch(int $propertyId, int $id, array $input): array
    {
        $existing = $this->get($propertyId, $id);

        $allowed = array_intersect_key(
            $input,
            array_flip(['status', 'assigned_to', 'cost', 'notes', 'priority'])
        );

        return $this->update($propertyId, $id, [ ...$existing, ...$allowed]);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function delete(int $propertyId, int $id): void
    {
        $this->get($propertyId, $id);
        $this->maintenance->delete($id);
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    private function validate(int $propertyId, array $input): array
    {
        $validator = new Validator($input);

        $validator->required('room_id', 'ห้องพัก')->integer('room_id', 1);
        $validator->optional('tenant_id')->integer('tenant_id', 1);
        $validator->required('title', 'หัวข้อ')->max('title', 120);
        $validator->required('description', 'รายละเอียด')->max('description', 2000);
        $validator->optional('priority', 'normal')->in('priority', self::PRIORITIES);
        $validator->optional('status', 'pending')->in('status', self::STATUSES);
        $validator->optional('assigned_to')->max('assigned_to', 120);
        $validator->optional('cost', 0)->numeric('cost', 0, 100000000);
        $validator->optional('notes')->max('notes', 2000);

        if ($validator->fails()) {
            $validator->validate();
        }

        $data = $validator->values();

        if ($this->rooms->find($propertyId, $data['room_id']) === null) {
            $validator->addError('room_id', 'ไม่พบห้องพักที่เลือก');
        }

        if (
            $data['tenant_id'] !== null
            && $this->tenants->find($propertyId, $data['tenant_id']) === null
        ) {
            $validator->addError('tenant_id', 'ไม่พบผู้เช่าที่เลือก');
        }

        $validator->validate();

        return $data;
    }

    /**
     * ตั้ง started_at / completed_at ตามการเปลี่ยนสถานะ
     *
     * @param array $existing
     * @param array $data
     */
    private function applyStatusTimestamps(array $existing, array $data): array
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $data['reported_at'] = $existing['reported_at'];
        $data['started_at'] = $existing['started_at'];
        $data['completed_at'] = $existing['completed_at'];

        if ($data['status'] === 'in_progress' && $data['started_at'] === null) {
            $data['started_at'] = $now;
        }

        if ($data['status'] === 'completed') {
            $data['started_at'] ??= $now;
            $data['completed_at'] ??= $now;
        }

        if (in_array($data['status'], ['pending', 'in_progress'], true)) {
            $data['completed_at'] = null;
        }

        if ($data['status'] === 'pending') {
            $data['started_at'] = null;
        }

        return $data;
    }

    /**
     * @param array $request
     */
    private function normalize(array $request): array
    {
        return [
             ...$request,
            'id' => (int) $request['id'],
            'room_id' => (int) $request['room_id'],
            'tenant_id' => $request['tenant_id'] !== null ? (int) $request['tenant_id'] : null,
            'cost' => (float) $request['cost'],
            'tenant_name' => $request['first_name'] !== null
                ? trim($request['first_name'].' '.$request['last_name'])
                : null
        ];
    }
}
