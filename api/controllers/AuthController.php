<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\AuthService;

final class AuthController
{
    /**
     * @param AuthService $auth
     */
    public function __construct(
        private readonly AuthService $auth
    ) {
    }

    /**
     * @param Request $request
     */
    public function login(Request $request): never
    {
        $input = $request->json();
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $fields = [];

        if ($username === '') {
            $fields['username'] = 'กรุณากรอกชื่อผู้ใช้';
        }

        if ($password === '') {
            $fields['password'] = 'กรุณากรอกรหัสผ่าน';
        }

        if ($fields !== []) {
            Response::error(
                'VALIDATION_ERROR',
                'กรุณาตรวจสอบข้อมูล',
                422,
                $fields
            );
        }

        $user = $this->auth->login($username, $password);

        if ($user === null) {
            Response::error(
                'INVALID_CREDENTIALS',
                'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
                401
            );
        }

        Response::success([
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    /**
     * @param Request $request
     */
    public function currentUser(Request $request): never
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            Response::error(
                'UNAUTHORIZED',
                'กรุณาเข้าสู่ระบบ',
                401
            );
        }

        Response::success([
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token'] ?? null
        ]);
    }

    /**
     * @param Request $request
     */
    public function changePassword(Request $request): never
    {
        $this->auth->changePassword((int) $_SESSION['user_id'], $request->json());

        Response::success(['message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว']);
    }

    /**
     * @param Request $request
     */
    public function updateProfile(Request $request): never
    {
        Response::success([
            'user' => $this->auth->updateProfile((int) $_SESSION['user_id'], $request->json())
        ]);
    }

    /**
     * @param Request $request
     */
    public function logout(Request $request): never
    {
        $this->auth->logout();

        Response::success([
            'message' => 'ออกจากระบบเรียบร้อยแล้ว'
        ]);
    }
}