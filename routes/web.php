<?php

use App\Http\Controllers\Admin\AdminArticleCategoryController;
use App\Http\Controllers\Admin\AdminArticleController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFoodController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Nutritionist\NutritionistConsultationController;
use App\Http\Controllers\Nutritionist\NutritionistDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\UserDashboardController;
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

    // Menu-menu Admin lainnya
    Route::get('/verifikasi', fn () => redirect()->route('admin.dashboard'))->name('verifications');
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
    Route::get('/catatan-makanan', fn () => redirect()->route('user.dashboard'))->name('meals');
    Route::get('/pindai-kemasan', fn () => redirect()->route('user.dashboard'))->name('scan');
    Route::get('/chat-gizi', fn () => redirect()->route('user.dashboard'))->name('chat_gizi');
    Route::get('/kalkulator', fn () => redirect()->route('user.dashboard'))->name('calculator');
    Route::get('/data-makanan', fn () => redirect()->route('user.dashboard'))->name('foods');
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
