<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\AuthController;
use DormPlus\Api\Controllers\ContractController;
use DormPlus\Api\Controllers\DashboardController;
use DormPlus\Api\Controllers\ExpenseController;
use DormPlus\Api\Controllers\MaintenanceController;
use DormPlus\Api\Controllers\MessageController;
use DormPlus\Api\Controllers\NotificationController;
use DormPlus\Api\Controllers\PaymentController;
use DormPlus\Api\Controllers\ReportController;
use DormPlus\Api\Controllers\RoomController;
use DormPlus\Api\Controllers\SettingsController;
use DormPlus\Api\Controllers\TenantController;
use DormPlus\Api\Core\HttpException;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Repositories\ContractRepository;
use DormPlus\Api\Repositories\DashboardRepository;
use DormPlus\Api\Repositories\ExpenseRepository;
use DormPlus\Api\Repositories\MaintenanceRepository;
use DormPlus\Api\Repositories\MessageRepository;
use DormPlus\Api\Repositories\NotificationRepository;
use DormPlus\Api\Repositories\PaymentRepository;
use DormPlus\Api\Repositories\ReportRepository;
use DormPlus\Api\Repositories\RoomRepository;
use DormPlus\Api\Repositories\SettingsRepository;
use DormPlus\Api\Repositories\TenantRepository;
use DormPlus\Api\Repositories\UserRepository;
use DormPlus\Api\Services\AuthService;
use DormPlus\Api\Services\ContractService;
use DormPlus\Api\Services\DashboardService;
use DormPlus\Api\Services\ExpenseService;
use DormPlus\Api\Services\MaintenanceService;
use DormPlus\Api\Services\MessageService;
use DormPlus\Api\Services\NotificationService;
use DormPlus\Api\Services\PaymentService;
use DormPlus\Api\Services\ReportService;
use DormPlus\Api\Services\RoomService;
use DormPlus\Api\Services\SettingsService;
use DormPlus\Api\Services\TenantService;
use DormPlus\Config\Database;

require dirname(__DIR__).'/bootstrap.php';

$config = require dirname(__DIR__).'/config/config.php';

session_name($config['session']['name']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => $config['session']['lifetime'],
        'path' => '/',
        'secure' => $config['session']['secure'],
        'httponly' => true,
        'samesite' => $config['session']['same_site']
    ]);

    session_start();
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;

if (
    $origin !== null
    && in_array($origin, $config['cors']['allowed_origins'], true)
) {
    header('Access-Control-Allow-Origin: '.$origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = Database::connection();

    // Repositories
    $userRepository = new UserRepository($pdo);
    $dashboardRepository = new DashboardRepository($pdo);
    $roomRepository = new RoomRepository($pdo);
    $tenantRepository = new TenantRepository($pdo);
    $contractRepository = new ContractRepository($pdo);
    $paymentRepository = new PaymentRepository($pdo);
    $expenseRepository = new ExpenseRepository($pdo);
    $maintenanceRepository = new MaintenanceRepository($pdo);
    $notificationRepository = new NotificationRepository($pdo);
    $messageRepository = new MessageRepository($pdo);
    $settingsRepository = new SettingsRepository($pdo);
    $reportRepository = new ReportRepository($pdo);

    // Services
    $authService = new AuthService($userRepository);
    $dashboardService = new DashboardService($dashboardRepository);
    $notificationService = new NotificationService($notificationRepository);
    $roomService = new RoomService(
        $roomRepository,
        $contractRepository,
        $paymentRepository,
        $maintenanceRepository
    );
    $tenantService = new TenantService($tenantRepository, $contractRepository, $paymentRepository);
    $contractService = new ContractService(
        $pdo,
        $contractRepository,
        $roomRepository,
        $tenantRepository,
        $notificationService
    );
    $paymentService = new PaymentService(
        $paymentRepository,
        $tenantRepository,
        $roomRepository,
        $contractRepository,
        $notificationService
    );
    $expenseService = new ExpenseService($expenseRepository);
    $maintenanceService = new MaintenanceService(
        $maintenanceRepository,
        $roomRepository,
        $tenantRepository,
        $notificationService
    );
    $messageService = new MessageService($pdo, $messageRepository);
    $settingsService = new SettingsService($settingsRepository);
    $reportService = new ReportService($reportRepository, $contractRepository);

    // Controllers
    $authController = new AuthController($authService);
    $dashboardController = new DashboardController($dashboardService);
    $roomController = new RoomController($roomService);
    $tenantController = new TenantController($tenantService);
    $contractController = new ContractController($contractService);
    $paymentController = new PaymentController($paymentService);
    $expenseController = new ExpenseController($expenseService);
    $maintenanceController = new MaintenanceController($maintenanceService);
    $notificationController = new NotificationController($notificationService);
    $messageController = new MessageController($messageService);
    $settingsController = new SettingsController($settingsService);
    $reportController = new ReportController($reportService);

    $router = new Router();

    require __DIR__.'/routes/auth.php';
    require __DIR__.'/routes/dashboard.php';
    require __DIR__.'/routes/rooms.php';
    require __DIR__.'/routes/tenants.php';
    require __DIR__.'/routes/contracts.php';
    require __DIR__.'/routes/payments.php';
    require __DIR__.'/routes/maintenance.php';
    require __DIR__.'/routes/notifications.php';
    require __DIR__.'/routes/messages.php';
    require __DIR__.'/routes/settings.php';
    require __DIR__.'/routes/reports.php';

    $router->get('/api/health', static function (): never {
        Response::success([
            'status' => 'พร้อมใช้งาน',
            'timestamp' => date(DATE_ATOM)
        ]);
    });

    $router->dispatch(new Request());
} catch (HttpException $exception) {
    Response::error(
        $exception->errorCode(),
        $exception->getMessage(),
        $exception->status(),
        $exception->fields()
    );
} catch (Throwable $exception) {
    error_log(sprintf(
        "[%s] %s\n%s",
        date(DATE_ATOM),
        $exception->getMessage(),
        $exception->getTraceAsString()
    ));

    Response::error(
        'INTERNAL_SERVER_ERROR',
        'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
        500
    );
}
