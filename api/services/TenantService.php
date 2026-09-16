<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DormPlus\Api\Core\ConflictException;
use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\ContractRepository;
use DormPlus\Api\Repositories\PaymentRepository;
use DormPlus\Api\Repositories\TenantRepository;

final class TenantService
{
    public const STATUSES = ['active', 'inactive', 'blacklisted'];
    public const GENDERS = ['male', 'female', 'other'];

    /**
     * @param TenantRepository $tenants
     * @param ContractRepository $contracts
     * @param PaymentRepository $payments
     */
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly ContractRepository $contracts,
        private readonly PaymentRepository $payments
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
        $result = $this->tenants->paginate($propertyId, $filters, $limit, $offset);
        $result['items'] = array_map([$this, 'normalize'], $result['items']);

        return $result;
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function get(int $propertyId, int $id): array
    {
        $tenant = $this->tenants->find($propertyId, $id);

        if ($tenant === null) {
            throw new NotFoundException('ไม่พบข้อมูลผู้เช่า');
        }

        return $this->normalize($tenant);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function detail(int $propertyId, int $id): array
    {
        return [
            'tenant' => $this->get($propertyId, $id),
            'contracts' => $this->contracts->historyFor('tenant_id', $id),
            'payments' => $this->payments->historyFor('tenant_id', $id, 24)
        ];
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function create(int $propertyId, array $input): array
    {
        $data = $this->validate($input);
        $id = $this->tenants->create($propertyId, $data);

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
        $data = $this->validate($input);
        $this->tenants->update($id, $data);

        return $this->get($propertyId, $id);
    }

    /**
     * @param int $propertyId
     * @param int $id
     */
    public function delete(int $propertyId, int $id): void
    {
        $tenant = $this->get($propertyId, $id);

        if ($tenant['contract_id'] !== null) {
            throw new ConflictException(
                'ผู้เช่ารายนี้มีสัญญาเช่าที่ใช้งานอยู่ กรุณาสิ้นสุดสัญญาก่อน'
            );
        }

        if ($this->tenants->hasRelatedRecords($id)) {
            throw new ConflictException(
                'ผู้เช่ารายนี้มีประวัติสัญญาหรือการชำระเงิน จึงไม่สามารถลบได้ '
                .'แนะนำให้เปลี่ยนสถานะเป็น "ไม่ใช้งาน" แทน'
            );
        }

        $this->tenants->delete($id);
    }

    /**
     * @param int $propertyId
     */
    public function options(int $propertyId): array
    {
        return array_map(static fn(array $tenant): array => [
             ...$tenant,
            'id' => (int) $tenant['id'],
            'name' => trim($tenant['first_name'].' '.$tenant['last_name']),
            'room_id' => $tenant['room_id'] !== null ? (int) $tenant['room_id'] : null,
            'contract_id' => $tenant['contract_id'] !== null ? (int) $tenant['contract_id'] : null,
            'contract_rent' => $tenant['contract_rent'] !== null
                ? (float) $tenant['contract_rent']
                : null
        ], $this->tenants->options($propertyId));
    }

    /**
     * @param array $input
     */
    private function validate(array $input): array
    {
        $validator = new Validator($input);

        $validator->required('first_name', 'ชื่อ')->max('first_name', 80);
        $validator->required('last_name', 'นามสกุล')->max('last_name', 80);
        $validator->optional('gender')->in('gender', self::GENDERS);
        $validator->required('phone', 'เบอร์โทร')->phone('phone');
        $validator->optional('email')->email('email')->max('email', 120);
        $validator->optional('national_id')->max('national_id', 20);
        $validator->optional('address')->max('address', 500);
        $validator->optional('emergency_contact')->max('emergency_contact', 120);
        $validator->optional('emergency_phone')->phone('emergency_phone');
        $validator->optional('status', 'active')->in('status', self::STATUSES);

        $nationalId = $validator->value('national_id');

        if ($nationalId !== null && !preg_match('/^[0-9]{13}$/', (string) $nationalId)) {
            $validator->addError('national_id', 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก');
        }

        $validator->validate();

        return $validator->values();
    }

    /**
     * @param array $tenant
     */
    private function normalize(array $tenant): array
    {
        $normalized = [
             ...$tenant,
            'id' => (int) $tenant['id'],
            'name' => trim($tenant['first_name'].' '.$tenant['last_name']),
            'contract_id' => $tenant['contract_id'] !== null ? (int) $tenant['contract_id'] : null,
            'room_id' => $tenant['room_id'] !== null ? (int) $tenant['room_id'] : null
        ];

        // เลขบัตรประชาชนแสดงเฉพาะ 4 หลักท้ายในรายการ
        if (array_key_exists('national_id', $normalized) && $normalized['national_id'] !== null) {
            $normalized['national_id_masked'] = str_repeat('x', 9)
                .substr((string) $normalized['national_id'], -4);
        }

        return $normalized;
    }
}
