<?php

use App\Http\Controllers\Admin\AdminAiSyncController;
use App\Http\Controllers\Admin\AdminArticleCategoryController;
use App\Http\Controllers\Admin\AdminArticleController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFoodController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminVerificationController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Nutritionist\NutritionistConsultationController;
use App\Http\Controllers\Nutritionist\NutritionistDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\AiPageController;
use App\Http\Controllers\User\MealLogController;
use App\Http\Controllers\User\UserDashboardController;
use App\Http\Controllers\User\UserFoodController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing page
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Pintu Gerbang Terpusat: Otomatis redirect ke dashboard sesuai role user
Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware(['auth'])
    ->name('dashboard');

// ==========================================
// 1. AREA ADMIN
// ==========================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/pengguna', [AdminUserController::class, 'index'])->name('users');
    Route::post('/pengguna', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/pengguna/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/pengguna/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    // Manajemen Artikel
    Route::get('/artikel', [AdminArticleController::class, 'index'])->name('articles');
    Route::get('/artikel/create', [AdminArticleController::class, 'create'])->name('articles.create');
    Route::post('/artikel', [AdminArticleController::class, 'store'])->name('articles.store');
    Route::get('/artikel/{artikel}/edit', [AdminArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/artikel/{artikel}', [AdminArticleController::class, 'update'])->name('articles.update');
    Route::delete('/artikel/{artikel}', [AdminArticleController::class, 'destroy'])->name('articles.destroy');
    Route::post('/artikel/upload-image', [AdminArticleController::class, 'uploadEditorImage'])->name('articles.upload-image');

    // Manajemen Kategori Artikel
    Route::get('/kategori-artikel', [AdminArticleCategoryController::class, 'index'])->name('article-categories.index');
    Route::post('/kategori-artikel', [AdminArticleCategoryController::class, 'store'])->name('article-categories.store');
    Route::put('/kategori-artikel/{kategoriArtikel}', [AdminArticleCategoryController::class, 'update'])->name('article-categories.update');
    Route::delete('/kategori-artikel/{kategoriArtikel}', [AdminArticleCategoryController::class, 'destroy'])->name('article-categories.destroy');

    // Manajemen Data Makanan
    Route::get('/data-makanan', [AdminFoodController::class, 'index'])->name('foods');
    Route::post('/data-makanan', [AdminFoodController::class, 'store'])->name('foods.store');
    Route::post('/data-makanan/{makanan}', [AdminFoodController::class, 'update'])->name('foods.update');
    Route::delete('/data-makanan/{makanan}', [AdminFoodController::class, 'destroy'])->name('foods.destroy');

    // Sinkronisasi Chat Gizi: publish verified foods the AI chat is missing
    Route::post('/sinkronisasi-ai', [AdminAiSyncController::class, 'run'])->name('ai-sync.run');

    // Verifikasi Pengajuan Makanan
    Route::get('/verifikasi', [AdminVerificationController::class, 'index'])->name('verifications');
    Route::post('/verifikasi/{makanan}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
    Route::post('/verifikasi/{makanan}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');
    Route::post('/verifikasi/{makanan}/update-approve', [AdminVerificationController::class, 'updateAndApprove'])->name('verifications.update-approve');

    // Profil Admin
    Route::get('/profil', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');
});

// ==========================================
// 2. AREA AHLI GIZI (NUTRITIONIST)
// ==========================================
Route::middleware(['auth', 'role:ahli_gizi|nutritionist'])->prefix('ahli-gizi')->name('nutritionist.')->group(function () {
    Route::get('/dashboard', [NutritionistDashboardController::class, 'index'])->name('dashboard');
    Route::get('/konsultasi', [NutritionistConsultationController::class, 'index'])->name('consultations');
});

// ==========================================
// 3. AREA PENGGUNA (CUSTOMER / FAMILY)
// ==========================================
Route::middleware(['auth'])->name('user.')->group(function () {
    Route::get('/beranda', [UserDashboardController::class, 'index'])->name('dashboard');

    // Menu-menu Pengguna
    // Catatan Makanan (Figma 03): the signed-in user's own meal log; /makanan is declared before /{pelacakan}.
    Route::get('/catatan-makanan', [MealLogController::class, 'index'])->middleware('permission:meal_log:read')->name('meals');
    Route::get('/catatan-makanan/makanan', [MealLogController::class, 'foods'])->middleware('permission:meal_log:create')->name('meals.foods');
    Route::post('/catatan-makanan', [MealLogController::class, 'store'])->middleware(['permission:meal_log:create', 'throttle:60,1'])->name('meals.store');
    Route::put('/catatan-makanan/{pelacakan}', [MealLogController::class, 'update'])->whereNumber('pelacakan')->middleware('permission:meal_log:update')->name('meals.update');
    Route::delete('/catatan-makanan/{pelacakan}', [MealLogController::class, 'destroy'])->whereNumber('pelacakan')->middleware('permission:meal_log:delete')->name('meals.destroy');
    // AI screens: same permissions as the /ai/v1 API behind them.
    Route::get('/pindai-kemasan', [AiPageController::class, 'scan'])->middleware('permission:health_tool:use_scanner')->name('scan');
    Route::get('/chat-gizi', [AiPageController::class, 'chat'])->middleware('permission:health_tool:use_ai_chat')->name('chat_gizi');
    Route::get('/kalkulator', fn () => redirect()->route('user.dashboard'))->name('calculator');
    // Data Makanan (Figma 07). /ajukan is declared before /{makanan} so it is not read as an id.
    Route::get('/data-makanan', [UserFoodController::class, 'index'])->middleware('permission:food:read')->name('foods');
    Route::get('/data-makanan/ajukan', [UserFoodController::class, 'create'])->middleware('permission:food:create')->name('foods.create');
    Route::post('/data-makanan/ajukan', [UserFoodController::class, 'store'])->middleware(['permission:food:create', 'throttle:20,60'])->name('foods.store');
    Route::get('/data-makanan/{makanan}', [UserFoodController::class, 'show'])->whereNumber('makanan')->middleware('permission:food:read')->name('foods.show');
    Route::get('/pengajuan-saya', [UserFoodController::class, 'mine'])->middleware('permission:food:create')->name('foods.mine');
    Route::get('/artikel', fn () => redirect()->route('user.dashboard'))->name('articles');
    Route::get('/konsultasi', fn () => redirect()->route('user.dashboard'))->name('consultation');
    Route::get('/keluarga', fn () => redirect()->route('user.dashboard'))->name('family');
});

// Profil akun
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/ai.php';
