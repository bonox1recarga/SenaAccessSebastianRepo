<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\TwoFactorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Healthcheck público para Railway (no requiere auth)
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Rutas de autenticación con Sanctum
Route::post('/register', [AuthController::class, 'register']);
// Throttle anclado al correo (ver AppServiceProvider): máx 5 fallos/min por cuenta.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
// Throttle por correo: máx 3 solicitudes/min, evita spam de correos.
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
// Throttle por código: permite probar el código sin abrir fuerza bruta.
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:reset-password');
// Invitado simple (público): crea o reutiliza el usuario invitado y registra el ingreso.
Route::post('/register-guest', [AuthController::class, 'registerGuest'])->middleware('throttle:guest');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Verificación de correo: enlace firmado temporal (GET, firmado con 'signed').
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');
// Reenvío del enlace de verificación (requiere sesión).
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
    ->middleware('auth:sanctum')
    ->middleware('throttle:verification');

// Verificación en dos pasos (2FA): validar-codigo, estado y decidir son PÚBLICOS.
Route::post('/2fa/validar-codigo', [TwoFactorController::class, 'validarCodigo'])->middleware('throttle:login');
Route::get('/2fa/estado/{challengeId}', [TwoFactorController::class, 'estado']);
// Botones "¿Eres tú?" del correo: público pero con enlace firmado.
Route::get('/2fa/decidir', [TwoFactorController::class, 'decidir']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role');
    });

    // Rutas de usuario general (Aprendiz)
    Route::get('/my-ingresos', [UserController::class, 'getMyIngresos']);
    Route::get('/my-equipment', [EquipmentController::class, 'getMyEquipment']);
    Route::post('/my-equipment', [EquipmentController::class, 'storeMine']);
    Route::put('/my-profile', [UserController::class, 'updateMyProfile']);
    Route::post('/my-profile/verificar-password', [UserController::class, 'verificarPassword']);

    // Verificación en dos pasos (requieren sesión).
    Route::get('/2fa/estado-config', [TwoFactorController::class, 'estadoConfig']);
    Route::post('/2fa/activar', [TwoFactorController::class, 'activar']);
    Route::post('/2fa/desactivar', [TwoFactorController::class, 'desactivar']);
    Route::get('/2fa/pendientes', [TwoFactorController::class, 'pendientes']);
    Route::post('/2fa/aprobar', [TwoFactorController::class, 'aprobar']);

    // Rutas compartidas entre Admin e Instructor
    Route::middleware('admin_or_instructor')->prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/ingresos', [UserController::class, 'getIngresos']);
        Route::get('/roles', [UserController::class, 'getRoles']);
        
        // Rutas admin para crear usuarios 
        Route::middleware('admin')->group(function () {
            Route::post('/users', [UserController::class, 'createUser']);
            Route::put('/users/{id}', [UserController::class, 'updateUser']);
            Route::delete('/users/{id}', [UserController::class, 'deleteUser']);
        });

        // Rutas admin para gestionar equipos
        Route::middleware('admin')->group(function () {
            Route::get('/equipment', [EquipmentController::class, 'index']);
            Route::post('/equipment', [EquipmentController::class, 'store']);
            Route::delete('/equipment/{id}', [EquipmentController::class, 'deleteEquipment']);
        });
    });

    // Rutas de novedades compartidas entre Admin e Instructor
    Route::middleware('admin_or_instructor')->group(function () {
        Route::get('/my-novedades', [NovedadController::class, 'getMyNovedades']); //OBTIENE LAS NOVEDADES DEL USUARIO
        Route::apiResource('novedades', NovedadController::class);
    });

    // Rutas específicas del Instructor
    Route::middleware('auth:sanctum')->group(function () {
        // Solo el instructor puede ver sus propios comprobantes
    });

    // ─── Chatbot IA ─────────────────────────────────────────────────────────
    Route::post('/chatbot', [ChatbotController::class, 'chat']);
});

