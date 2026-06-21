<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CargoController as AdminCargoController;
use App\Http\Controllers\Admin\AuthLogController as AdminAuthLogController;
use App\Http\Controllers\Admin\CatalogImageWorkbenchController as AdminCatalogImageWorkbenchController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\StoryController as AdminStoryController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DataConnectionController as AdminDataConnectionController;
use App\Http\Controllers\Admin\DataPullController as AdminDataPullController;
use App\Http\Controllers\Admin\ErpCariController as AdminErpCariController;
use App\Http\Controllers\Admin\ErpFaturaController as AdminErpFaturaController;
use App\Http\Controllers\Admin\ErpPosController as AdminErpPosController;
use App\Http\Controllers\Admin\ErpSayimController as AdminErpSayimController;
use App\Http\Controllers\Admin\FakeAuth2Controller as AdminFakeAuth2Controller;
use App\Http\Controllers\Admin\HomepageBlockController as AdminHomepageBlockController;
use App\Http\Controllers\Admin\MarketingSettingController as AdminMarketingSettingController;
use App\Http\Controllers\Admin\MaintenanceModeController as AdminMaintenanceModeController;
use App\Http\Controllers\Admin\MailAccessController as AdminMailAccessController;
use App\Http\Controllers\Admin\NavigationItemController as AdminNavigationItemController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OpsMonitorController as AdminOpsMonitorController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductDataPullController as AdminProductDataPullController;
use App\Http\Controllers\Admin\ProductImageSuggestionController as AdminProductImageSuggestionController;
use App\Http\Controllers\Admin\ProductionReadinessController as AdminProductionReadinessController;
use App\Http\Controllers\Admin\SeoAutomationController as AdminSeoAutomationController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Feed\GoogleMerchantFeedController;
use App\Http\Controllers\Paytr\CheckoutPageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $storefrontUrl = rtrim((string) config('commerce.domains.storefront', '/'), '/');

    if (app()->environment(['local', 'testing'])) {
        $host = $request->getHost();

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return redirect()->away($storefrontUrl ?: '/');
        }
    }

    return redirect()->away($storefrontUrl ?: '/');
})->name('storefront.redirect');

Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');

Route::get('/p/{order:checkout_ref}', [CheckoutPageController::class, 'show'])
    ->name('checkout.session');

Route::get('/feed/google-merchant.xml', GoogleMerchantFeedController::class)
    ->name('feed.google-merchant');
Route::get('/feed/facebook-catalog.xml', GoogleMerchantFeedController::class)
    ->name('feed.facebook-catalog');

Route::get('/oauth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('oauth.redirect');

Route::get('/oauth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('oauth.callback');

$adminPrefix = trim((string) config('admin_security.admin_prefix', 'admin'), '/');

Route::get('/internal/mail-auth/check', AdminMailAccessController::class)
    ->name('internal.mail-auth.check');

Route::prefix($adminPrefix)->name('admin.')->middleware('admin.security')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
    });

    Route::get('/oauth2/authorize', [AdminFakeAuth2Controller::class, 'show'])->name('fake-auth2.show');
    Route::post('/oauth2/token', [AdminFakeAuth2Controller::class, 'store'])->name('fake-auth2.store');
    Route::get('/auth2', [AdminFakeAuth2Controller::class, 'trap'])->name('decoy.auth2');
    Route::get('/sso/login', [AdminFakeAuth2Controller::class, 'trap'])->name('decoy.sso');

    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('auth-logs', AdminAuthLogController::class)->name('auth-logs.index');
        Route::get('ops-monitor', AdminOpsMonitorController::class)->name('ops-monitor.index');
        Route::get('production-readiness', AdminProductionReadinessController::class)->name('production-readiness.index');
        Route::resource('products', AdminProductController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('products/bulk', [AdminProductController::class, 'bulkAction'])->name('products.bulk');
        Route::get('catalog-images', [AdminCatalogImageWorkbenchController::class, 'index'])->name('catalog-images.index');
        Route::post('catalog-images/batch', [AdminCatalogImageWorkbenchController::class, 'batch'])->name('catalog-images.batch');
        Route::get('products/{product}/image-suggestions', [AdminProductImageSuggestionController::class, 'suggest'])
            ->name('products.image-suggestions.suggest');
        Route::post('products/{product}/image-suggestions/apply', [AdminProductImageSuggestionController::class, 'apply'])
            ->name('products.image-suggestions.apply');
        Route::resource('categories', AdminCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('orders-monitor/latest', [AdminOrderController::class, 'latest'])->name('orders.latest');
        Route::get('orders/export', [AdminOrderController::class, 'export'])->name('orders.export');
        Route::resource('orders', AdminOrderController::class)->only(['index', 'show']);
        Route::post('orders/{order}/approve', [AdminOrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status.update');
        Route::post('orders/{order}/cargo', [AdminOrderController::class, 'assignCargo'])->name('orders.cargo.assign');
        Route::post('orders/{order}/payment-method', [AdminOrderController::class, 'updatePaymentMethod'])->name('orders.payment-method.update');
        Route::post('orders/{order}/payment-status', [AdminOrderController::class, 'updatePaymentStatus'])->name('orders.payment-status.update');
        Route::resource('payments', AdminPaymentController::class)->only(['index']);
        Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
        Route::resource('users', AdminUserController::class)->only(['index']);
        Route::post('users/{user}/vip', [AdminUserController::class, 'updateVip'])->name('users.vip.update');
        Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications', [AdminNotificationController::class, 'store'])->name('notifications.store');
        Route::get('support', [AdminSupportController::class, 'index'])->name('support.index');
        Route::post('support/{conversation}/messages', [AdminSupportController::class, 'message'])->name('support.messages.store');
        Route::patch('support/{conversation}', [AdminSupportController::class, 'update'])->name('support.update');
        Route::get('support/{conversation}/stream', [AdminSupportController::class, 'stream'])->name('support.stream');
        Route::resource('pages', AdminPageController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('homepage-blocks', AdminHomepageBlockController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['homepage-blocks' => 'homepageBlock']);
        Route::resource('navigation', AdminNavigationItemController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['navigation' => 'navigationItem']);
        Route::resource('campaigns', AdminCampaignController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
        Route::resource('stories', AdminStoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('coupons', [AdminCampaignController::class, 'storeCoupon'])->name('coupons.store');
        Route::get('marketing', [AdminMarketingSettingController::class, 'edit'])->name('marketing.edit');
        Route::put('marketing', [AdminMarketingSettingController::class, 'update'])->name('marketing.update');
        Route::get('maintenance', [AdminMaintenanceModeController::class, 'edit'])->name('maintenance.edit');
        Route::put('maintenance', [AdminMaintenanceModeController::class, 'update'])->name('maintenance.update');
        Route::get('seo-automation', [AdminSeoAutomationController::class, 'index'])->name('seo-automation.index');
        Route::post('seo-automation/xml', [AdminSeoAutomationController::class, 'generateXml'])->name('seo-automation.xml');
        Route::post('seo-automation/ai', [AdminSeoAutomationController::class, 'startAi'])->name('seo-automation.ai');
        Route::post('seo-automation/base-seo', [AdminSeoAutomationController::class, 'generateBaseSeo'])->name('seo-automation.base-seo');
        Route::post('seo-automation/cache', [AdminSeoAutomationController::class, 'clearCache'])->name('seo-automation.cache');

        // ── Kargo Ayarları ─────────────────────────────────────────
        Route::get('cargo', [AdminCargoController::class, 'index'])->name('cargo.index');
        Route::put('cargo', [AdminCargoController::class, 'update'])->name('cargo.update');

        // ── Veri Çekme (Dış DB tablolarını tara) ──────────────────
        Route::prefix('data-pull')->name('data-pull.')->group(function () {
            Route::get('/', [AdminDataConnectionController::class, 'index'])->name('index');
            Route::get('/connections/create', [AdminDataConnectionController::class, 'create'])->name('create');
            Route::post('/connections', [AdminDataConnectionController::class, 'store'])->name('store');
            Route::get('/connections/{connection}/edit', [AdminDataConnectionController::class, 'edit'])->name('edit');
            Route::put('/connections/{connection}', [AdminDataConnectionController::class, 'update'])->name('update');
            Route::delete('/connections/{connection}', [AdminDataConnectionController::class, 'destroy'])->name('destroy');
            Route::post('/connections/{connection}/test', [AdminDataConnectionController::class, 'test'])->name('test');

            Route::get('/connections/{connection}/browse', [AdminDataPullController::class, 'browse'])->name('browse');
            Route::post('/connections/{connection}/preview', [AdminDataPullController::class, 'preview'])->name('preview');
            Route::get('/connections/{connection}/export', [AdminDataPullController::class, 'export'])->name('export');
            Route::get('/products', [AdminProductDataPullController::class, 'index'])->name('products');
            Route::post('/products/inspect', [AdminProductDataPullController::class, 'inspect'])->name('products.inspect');
            Route::post('/products/import', [AdminProductDataPullController::class, 'import'])->name('products.import');
            Route::post('/products/settings', [AdminProductDataPullController::class, 'settings'])->name('products.settings');
        });

        // ── ERP Modülleri ────────────────────────────────────────────
        Route::get('erp/fatura', [AdminErpFaturaController::class, 'index'])->name('erp.fatura');
        Route::get('erp/fatura/{id}', [AdminErpFaturaController::class, 'show'])->name('erp.fatura.show');
        Route::post('erp/fatura/{id}/sync', [AdminErpFaturaController::class, 'sync'])->name('erp.fatura.sync');
        Route::get('erp/pos', [AdminErpPosController::class, 'index'])->name('erp.pos');
        Route::get('erp/cari', [AdminErpCariController::class, 'index'])->name('erp.cari');
        Route::get('erp/cari/{id}', [AdminErpCariController::class, 'show'])->name('erp.cari.show');
        Route::get('erp/sayim', [AdminErpSayimController::class, 'index'])->name('erp.sayim');
        Route::get('erp/sayim/{id}', [AdminErpSayimController::class, 'show'])->name('erp.sayim.show');
    });

    Route::get('/{adminDecoyPath}', [AdminFakeAuth2Controller::class, 'trap'])
        ->where('adminDecoyPath', '.*')
        ->name('decoy.catch');
});

Route::middleware('admin.security')->group(function (): void {
    Route::get('/administrator', [AdminFakeAuth2Controller::class, 'trap'])->name('admin.decoy.administrator');
    Route::get('/admin.php', [AdminFakeAuth2Controller::class, 'trap'])->name('admin.decoy.php');
    Route::get('/wp-admin', [AdminFakeAuth2Controller::class, 'trap'])->name('admin.decoy.wp-admin');
    Route::get('/cpanel', [AdminFakeAuth2Controller::class, 'trap'])->name('admin.decoy.cpanel');
});
