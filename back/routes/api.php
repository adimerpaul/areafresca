<?php

use App\Http\Controllers\CompraController;
use App\Http\Controllers\CertificadoDigitalController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CorreoPruebaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\SiatTokenController;
use App\Http\Controllers\SiatEventoSignificativoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login']);
Route::get('/configuracion', [ConfiguracionController::class, 'show']);
Route::get('/correo-prueba', [CorreoPruebaController::class, 'enviar']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/logout', [UserController::class, 'logout']);
    Route::put('/cambiar-password', [UserController::class, 'changePassword']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{id}/avatar', [UserController::class, 'uploadAvatar']);
    Route::get('/permissions', [UserController::class, 'permissions']);
    Route::get('/users/{id}/permissions', [UserController::class, 'userPermissions']);
    Route::put('/users/{id}/permissions', [UserController::class, 'updateUserPermissions']);

    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos-exportar/excel', [ProductoController::class, 'exportExcel']);
    Route::get('/productos-exportar/pdf', [ProductoController::class, 'exportPdf']);
    Route::get('/productos-catalogos', [ProductoController::class, 'catalogos']);
    Route::post('/categorias', [ProductoController::class, 'storeCategoria']);
    Route::put('/categorias/{categoria}', [ProductoController::class, 'updateCategoria']);
    Route::delete('/categorias/{categoria}', [ProductoController::class, 'destroyCategoria']);
    Route::patch('/productos/{producto}/codigo-barras', [ProductoController::class, 'updateBarcode']);
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::put('/productos/{producto}', [ProductoController::class, 'update']);
    Route::post('/productos/{producto}/foto', [ProductoController::class, 'uploadPhoto']);
    Route::post('/productos/{producto}/foto-url', [ProductoController::class, 'uploadPhotoFromUrl']);
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy']);

    Route::get('/ventas', [VentaController::class, 'index']);
    Route::get('/clientes/buscar', [ClienteController::class, 'search']);
    Route::post('/ventas', [VentaController::class, 'store']);
    Route::get('/ventas-resumen', [VentaController::class, 'summary']);
    Route::get('/dashboard', [VentaController::class, 'dashboard']);
    Route::get('/ventas-exportar/excel', [VentaController::class, 'exportExcel']);
    Route::get('/ventas-exportar/pdf', [VentaController::class, 'exportPdf']);
    Route::get('/siat/estado', [VentaController::class, 'siatStatus']);
    Route::get('/siat/motivos-anulacion', [VentaController::class, 'siatCancellationReasons']);
    Route::get('/ventas/{venta}/verificar-impuestos', [VentaController::class, 'verifyTaxes']);
    Route::get('/ventas/{venta}', [VentaController::class, 'show']);
    Route::put('/ventas/{venta}/anular', [VentaController::class, 'cancel']);

    Route::get('/compras', [CompraController::class, 'index']);
    Route::get('/compras-resumen', [CompraController::class, 'summary']);
    Route::post('/compras', [CompraController::class, 'store']);
    Route::get('/compras/{compra}', [CompraController::class, 'show']);
    Route::put('/compras/{compra}/anular', [CompraController::class, 'cancel']);
    Route::get('/proveedores', [CompraController::class, 'proveedores']);
    Route::post('/proveedores', [CompraController::class, 'storeProveedor']);
    Route::put('/proveedores/{proveedor}', [CompraController::class, 'updateProveedor']);
    Route::delete('/proveedores/{proveedor}', [CompraController::class, 'destroyProveedor']);
    Route::get('/vencimientos', [CompraController::class, 'vencimientos']);
    Route::put('/configuracion', [ConfiguracionController::class, 'update']);
    Route::post('/configuracion/logo', [ConfiguracionController::class, 'uploadLogo']);
    Route::get('/certificados-digitales', [CertificadoDigitalController::class, 'index']);
    Route::post('/certificados-digitales', [CertificadoDigitalController::class, 'store']);
    Route::put('/certificados-digitales/{certificado}/activar', [CertificadoDigitalController::class, 'activate']);
    Route::delete('/certificados-digitales/{certificado}', [CertificadoDigitalController::class, 'destroy']);
    Route::get('/siat-tokens', [SiatTokenController::class, 'index']);
    Route::get('/siat-credenciales', [SiatTokenController::class, 'credentials']);
    Route::post('/siat-cuis', [SiatTokenController::class, 'createCuis']);
    Route::post('/siat-cufd', [SiatTokenController::class, 'createCufd']);
    Route::get('/siat-eventos-significativos', [SiatEventoSignificativoController::class, 'index']);
    Route::post('/siat-eventos-significativos', [SiatEventoSignificativoController::class, 'store']);
    Route::post('/siat-tokens', [SiatTokenController::class, 'store']);
    Route::delete('/siat-tokens/{token}', [SiatTokenController::class, 'destroy']);
});
