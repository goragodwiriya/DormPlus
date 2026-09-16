<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\UserRepository;

final class AuthService
{
    /**
     * @param UserRepository $users
     */
    public function __construct(
        private readonly UserRepository $users
    ) {
    }

    /**
     * @param string $username
     * @param string $password
     * @return mixed
     */
    public function login(string $username, string $password): ?array
    {
        $user = $this->users->findByUsername($username);

        if (
            $user === null
            || (int) $user['is_active'] !== 1
            || !password_verify($password, $user['password_hash'])
        ) {
            return null;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['property_id'] = (int) $user['property_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $this->users->updateLastLogin((int) $user['id']);

        return $this->users->findPublicById((int) $user['id']);
    }

    /**
     * @return mixed
     */
    public function currentUser(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        return $this->users->findPublicById((int) $userId);
    }

    /**
     * @param int $userId
     * @param array $input current_password, new_password, confirm_password
     */
    public function changePassword(int $userId, array $input): void
    {
        $validator = new Validator($input);
        $validator->required('current_password', 'รหัสผ่านปัจจุบัน');
        $validator->required('new_password', 'รหัสผ่านใหม่');
        $validator->required('confirm_password', 'ยืนยันรหัสผ่านใหม่');

        if ($validator->fails()) {
            $validator->validate();
        }

        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new NotFoundException('ไม่พบผู้ใช้');
        }

        if (!password_verify((string) $validator->value('current_password'), $user['password_hash'])) {
            $validator->addError('current_password', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }

        $newPassword = (string) $validator->value('new_password');

        if (strlen($newPassword) < 8) {
            $validator->addError('new_password', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
        }

        if ($newPassword !== (string) $validator->value('confirm_password')) {
            $validator->addError('confirm_password', 'รหัสผ่านใหม่ไม่ตรงกัน');
        }

        $validator->validate();

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));
    }

    /**
     * @param int $userId
     * @param array $input first_name, last_name
     */
    public function updateProfile(int $userId, array $input): array
    {
        $validator = new Validator($input);
        $validator->required('first_name', 'ชื่อ')->max('first_name', 80);
        $validator->required('last_name', 'นามสกุล')->max('last_name', 80);
        $validator->validate();

        $this->users->updateProfile($userId, $validator->values());

        return $this->users->findPublicById($userId) ?? [];
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();
    }
}