<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadIngestController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\Api\LeadStatusesController;
use App\Http\Controllers\Api\MenusController;
use App\Http\Controllers\Api\MetaLeadWebhookController;
use App\Http\Controllers\Api\PermissionsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\UsersController;
use App\Models\MstMenu;
use App\Models\MstPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public lead capture (landing pages)
Route::post('/leads/ingest', [LeadIngestController::class, 'ingest']);

// Meta Lead Ads webhook
Route::get('/webhooks/meta/leadgen', [MetaLeadWebhookController::class, 'verify']);
Route::post('/webhooks/meta/leadgen', [MetaLeadWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('role:id,name');

        $allowedMenuIds = MstPermission::query()
            ->where('role_id', (int) $user->role_id)
            ->where('enabled', true)
            ->pluck('menu_id')
            ->all();

        $menus = MstMenu::query()
            ->select(['id', 'key', 'label', 'path', 'sort'])
            ->whereIn('id', $allowedMenuIds)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return response()->json([
            'user' => $user,
            'menus' => $menus,
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('admin')->group(function () {
        // Users
        Route::get('/users', [UsersController::class, 'index']);
        Route::post('/users', [UsersController::class, 'store']);
        Route::put('/users/{user}', [UsersController::class, 'update']);
        Route::delete('/users/{user}', [UsersController::class, 'destroy']);

        // Roles
        Route::get('/roles', [RolesController::class, 'index']);

        // Menus
        Route::get('/menus', [MenusController::class, 'index']);
        Route::post('/menus', [MenusController::class, 'store']);
        Route::put('/menus/{menu}', [MenusController::class, 'update']);
        Route::delete('/menus/{menu}', [MenusController::class, 'destroy']);

        // Permissions
        Route::get('/roles/{role}/permissions', [PermissionsController::class, 'show']);
        Route::put('/roles/{role}/permissions', [PermissionsController::class, 'update']);

        // Lead Statuses
        Route::get('/lead-statuses', [LeadStatusesController::class, 'index']);
        Route::post('/lead-statuses', [LeadStatusesController::class, 'store']);
        Route::put('/lead-statuses/{leadStatus}', [LeadStatusesController::class, 'update']);
        Route::delete('/lead-statuses/{leadStatus}', [LeadStatusesController::class, 'destroy']);

        // Leads
        Route::get('/leads', [LeadsController::class, 'index']);
        Route::get('/leads/{lead}', [LeadsController::class, 'show']);
        Route::post('/leads', [LeadsController::class, 'store']);
        Route::put('/leads/{lead}', [LeadsController::class, 'update']);
    });
});
