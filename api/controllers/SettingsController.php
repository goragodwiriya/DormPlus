<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\SettingsService;

final class SettingsController extends Controller
{
    /**
     * @param SettingsService $settings
     */
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        Response::success($this->settings->all($this->propertyId()));
    }

    /**
     * @param Request $request
     */
    public function updateProperty(Request $request): never
    {
        Response::success($this->settings->updateProperty($this->propertyId(), $request->json()));
    }

    /**
     * @param Request $request
     */
    public function updateRental(Request $request): never
    {
        Response::success($this->settings->updateRental($this->propertyId(), $request->json()));
    }

    /**
     * @param Request $request
     */
    public function updateSystem(Request $request): never
    {
        Response::success($this->settings->updateSystem($this->propertyId(), $request->json()));
    }
}
