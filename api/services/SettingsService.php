<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\SettingsRepository;

final class SettingsService
{
    /**
     * ค่าตั้งต้นของการตั้งค่าที่ระบบรู้จัก
     */
    private const DEFAULTS = [
        'default_monthly_rent' => '3500',
        'electricity_rate' => '8',
        'water_rate' => '18',
        'payment_due_day' => '5',
        'contract_warning_days' => '30',
        'language' => 'th',
        'date_format' => 'd/m/Y',
        'currency' => 'THB'
    ];

    /**
     * @param SettingsRepository $settings
     */
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * @param int $propertyId
     */
    public function all(int $propertyId): array
    {
        $property = $this->settings->property($propertyId);

        if ($property === null) {
            throw new NotFoundException('ไม่พบข้อมูลหอพัก');
        }

        return [
            'property' => $property,
            'settings' => [ ...self::DEFAULTS, ...$this->settings->all($propertyId)]
        ];
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function updateProperty(int $propertyId, array $input): array
    {
        $validator = new Validator($input);
        $validator->required('name', 'ชื่อหอพัก')->max('name', 120);
        $validator->optional('address')->max('address', 500);
        $validator->optional('phone')->phone('phone');
        $validator->optional('email')->email('email')->max('email', 120);
        $validator->optional('description')->max('description', 1000);
        $validator->validate();

        $this->settings->updateProperty($propertyId, $validator->values());

        return $this->all($propertyId);
    }

    /**
     * ค่าเช่า/อัตราสาธารณูปโภค/วันครบกำหนด
     *
     * @param int $propertyId
     * @param array $input
     */
    public function updateRental(int $propertyId, array $input): array
    {
        $validator = new Validator($input);
        $validator->required('default_monthly_rent', 'ค่าเช่าเริ่มต้น')
            ->numeric('default_monthly_rent', 0, 10000000);
        $validator->required('electricity_rate', 'ค่าไฟต่อหน่วย')
            ->numeric('electricity_rate', 0, 1000);
        $validator->required('water_rate', 'ค่าน้ำต่อหน่วย')->numeric('water_rate', 0, 1000);
        $validator->required('payment_due_day', 'วันครบกำหนดชำระ')
            ->integer('payment_due_day', 1, 31);
        $validator->optional('contract_warning_days', 30)
            ->integer('contract_warning_days', 1, 365);
        $validator->validate();

        foreach ($validator->values() as $key => $value) {
            $this->settings->set($propertyId, $key, (string) $value);
        }

        return $this->all($propertyId);
    }

    /**
     * @param int $propertyId
     * @param array $input
     */
    public function updateSystem(int $propertyId, array $input): array
    {
        $validator = new Validator($input);
        $validator->optional('language', 'th')->in('language', ['th', 'en']);
        $validator->optional('date_format', 'd/m/Y')
            ->in('date_format', ['d/m/Y', 'Y-m-d', 'd M Y']);
        $validator->optional('currency', 'THB')->in('currency', ['THB', 'USD']);
        $validator->validate();

        foreach ($validator->values() as $key => $value) {
            $this->settings->set($propertyId, $key, (string) $value);
        }

        return $this->all($propertyId);
    }

    /**
     * @param int $propertyId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function value(int $propertyId, string $key, mixed $default = null): mixed
    {
        return $this->settings->get($propertyId, $key, self::DEFAULTS[$key] ?? $default);
    }
}
