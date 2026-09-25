<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Public;
use App\Http\Controllers\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publik
|--------------------------------------------------------------------------
*/
Route::get('/', Public\HomeController::class)->name('home');
Route::get('/health', HealthController::class)->middleware('throttle:30,1')->name('health');
Route::get('/cara-kerja', [Public\PageController::class, 'howItWorks'])->name('how-it-works');
Route::get('/syarat-ketentuan', [Public\LegalController::class, 'terms'])->name('legal.terms');
Route::get('/kebijakan-privasi', [Public\LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/lelang', [Public\AuctionController::class, 'index'])->name('auctions.index');
Route::get('/lelang/{auction:slug}', [Public\AuctionController::class, 'show'])->name('auctions.show');
Route::get('/lot/{lot}', [Public\LotController::class, 'show'])->name('lots.show');
Route::get('/lot/{lot}/state', [Public\LotController::class, 'state'])->middleware('throttle:120,1')->name('lots.state');

Route::get('/portal-penitip/{consignor}', Public\ConsignorPortalController::class)
    ->middleware(['signed', 'throttle:30,1'])->name('consignor.portal');

// Webhook payment gateway (Midtrans / Xendit / ...). Keaslian diverifikasi per driver.
Route::middleware('throttle:120,1')->group(function () {
    Route::post('/payments/webhook/{gateway}', [Public\PaymentWebhookController::class, 'payment'])->name('payments.webhook');
    Route::post('/payouts/webhook/{gateway}', [Public\PaymentWebhookController::class, 'payout'])->name('payouts.webhook');
    // Alias lama untuk URL notifikasi Midtrans yang sudah terdaftar di dashboard.
    Route::post('/payments/midtrans/notification', [Public\PaymentWebhookController::class, 'payment'])
        ->defaults('gateway', 'midtrans')->name('payments.midtrans.notification');
});

/*
|--------------------------------------------------------------------------
| Peserta (login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/lot/{lot}/bid', [User\BidController::class, 'store'])->middleware('throttle:bids')->name('lots.bid');
    Route::post('/lot/{lot}/beli-sekarang', [User\BidController::class, 'buyNow'])->middleware('throttle:bids')->name('lots.buy-now');
    Route::post('/lot/{lot}/watch', [User\WatchlistController::class, 'toggle'])->name('lots.watch');
    Route::post('/lelang/{auction:slug}/daftar', [User\AuctionRegistrationController::class, 'store'])
        ->middleware('throttle:uploads')->name('auctions.register');
    Route::post('/lelang/{auction:slug}/bayar-jaminan', [User\PaymentController::class, 'payDeposit'])
        ->middleware('throttle:10,1')->name('auctions.deposit.pay');

    Route::get('/pembayaran/{payment:reference}', [User\PaymentController::class, 'show'])->name('payments.show');
    Route::get('/pembayaran/{payment:reference}/simulator', [User\PaymentController::class, 'simulator'])->name('payments.simulator');
    Route::post('/pembayaran/{payment:reference}/simulator', [User\PaymentController::class, 'simulate'])->name('payments.simulate');

    Route::post('/persetujuan', [Public\LegalController::class, 'accept'])->name('legal.accept');

    Route::prefix('akun')->name('user.')->group(function () {
        Route::get('/profil/unduh-data', [User\ProfileController::class, 'export'])->middleware('throttle:5,1')->name('profile.export');
        Route::get('/', User\DashboardController::class)->name('dashboard');
        Route::get('/profil', [User\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [User\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profil/rekening', [User\ProfileController::class, 'updateBank'])->name('profile.bank');
        Route::post('/profil/kyc', [User\ProfileController::class, 'submitKyc'])->middleware('throttle:uploads')->name('profile.kyc');

        Route::get('/notifikasi', [User\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifikasi/baca-semua', [User\NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('/notifikasi/{id}', [User\NotificationController::class, 'open'])->name('notifications.open');

        Route::get('/invoice', [User\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoice/{invoice}', [User\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoice/{invoice}/pdf', [User\InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('/invoice/{invoice}/bayar', [User\PaymentController::class, 'payInvoice'])->middleware('throttle:10,1')->name('invoices.pay');
        Route::post('/invoice/{invoice}/bukti', [User\InvoiceController::class, 'uploadProof'])->middleware('throttle:uploads')->name('invoices.proof');
    });
});

/*
|--------------------------------------------------------------------------
| Admin (petugas) — 2FA wajib untuk admin & super admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super_admin,admin,staff', 'two-factor.required'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/keamanan/2fa', [Admin\SecurityController::class, 'twoFactor'])->name('security.two-factor');

    // Operasional gudang: staf, admin, super admin.
    Route::post('/penitip/{consignor}/reset-portal', [Admin\ConsignorController::class, 'resetPortal'])->name('consignors.reset-portal');
    Route::resource('penitip', Admin\ConsignorController::class)->names('consignors')->parameters(['penitip' => 'consignor']);
    Route::resource('barang', Admin\ItemController::class)->names('items')->parameters(['barang' => 'item']);
    Route::post('/barang/{item}/status', [Admin\ItemController::class, 'transition'])->name('items.transition');
    Route::delete('/barang/{item}/foto/{image}', [Admin\ItemController::class, 'destroyImage'])->name('items.images.destroy');

    // Lelang, peserta & keuangan: admin & super admin.
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/kategori', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('/kategori', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::put('/kategori/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/kategori/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::resource('lelang', Admin\AuctionController::class)->except('destroy')->names('auctions')->parameters(['lelang' => 'auction']);
        Route::post('/lelang/{auction}/lot', [Admin\AuctionController::class, 'addLots'])->name('auctions.lots.store');
        Route::delete('/lelang/{auction}/lot/{lot}', [Admin\AuctionController::class, 'removeLot'])->name('auctions.lots.destroy');
        Route::post('/lelang/{auction}/lot/{lot}/batal', [Admin\AuctionController::class, 'cancelLot'])->name('auctions.lots.cancel');
        Route::post('/lelang/{auction}/terbitkan', [Admin\AuctionController::class, 'publish'])->name('auctions.publish');
        Route::post('/lelang/{auction}/tarik', [Admin\AuctionController::class, 'unpublish'])->name('auctions.unpublish');

        // Konsol juru lelang (metode live).
        Route::get('/lelang/{auction}/konsol', [Admin\LiveAuctionController::class, 'show'])->name('auctions.live');
        Route::post('/lelang/{auction}/lot/{lot}/buka', [Admin\LiveAuctionController::class, 'open'])->name('auctions.live.open');
        Route::post('/lelang/{auction}/lot/{lot}/panggil', [Admin\LiveAuctionController::class, 'call'])->name('auctions.live.call');
        Route::post('/lelang/{auction}/lot/{lot}/palu', [Admin\LiveAuctionController::class, 'hammer'])->name('auctions.live.hammer');

        Route::post('/pendaftaran/{registration}', [Admin\RegistrationController::class, 'decide'])->name('registrations.decide');
        Route::post('/pendaftaran/{registration}/jaminan', [Admin\RegistrationController::class, 'settleDeposit'])->name('registrations.deposit');
        Route::get('/pendaftaran/{registration}/bukti', [Admin\RegistrationController::class, 'proof'])->name('registrations.proof');

        Route::get('/peserta', [Admin\BidderController::class, 'index'])->name('bidders.index');
        Route::get('/peserta/{user}', [Admin\BidderController::class, 'show'])->name('bidders.show');
        Route::post('/peserta/{user}/kyc', [Admin\BidderController::class, 'kyc'])->name('bidders.kyc');
        Route::post('/peserta/{user}/blokir', [Admin\BidderController::class, 'block'])->name('bidders.block');
        Route::get('/peserta/{user}/ktp', [Admin\BidderController::class, 'ktp'])->name('bidders.ktp');

        Route::get('/invoice', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoice/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoice/{invoice}/lunas', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::post('/invoice/{invoice}/batal', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('/invoice/{invoice}/serahkan', [Admin\InvoiceController::class, 'deliver'])->name('invoices.deliver');
        Route::get('/invoice/{invoice}/bukti', [Admin\InvoiceController::class, 'proof'])->name('invoices.proof');
        Route::get('/invoice/{invoice}/pdf', [Admin\InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('/invoice/{invoice}/bast', [Admin\InvoiceController::class, 'handover'])->name('invoices.handover');

        Route::get('/settlement', [Admin\SettlementController::class, 'index'])->name('settlements.index');
        Route::post('/settlement/{settlement}/bayar', [Admin\SettlementController::class, 'markPaid'])->name('settlements.paid');
        Route::post('/settlement/{settlement}/transfer', [Admin\SettlementController::class, 'payout'])->name('settlements.payout');
        Route::get('/settlement/{settlement}/bukti', [Admin\SettlementController::class, 'proof'])->name('settlements.proof');

        Route::get('/laporan', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/penjualan.xlsx', [Admin\ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/laporan/settlement.xlsx', [Admin\ReportController::class, 'settlements'])->name('reports.settlements');

        Route::get('/transaksi', [Admin\TransactionController::class, 'index'])->name('transactions.index');

        Route::get('/log-audit', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/pengguna', [Admin\UserController::class, 'store'])->name('users.store');
        Route::put('/pengguna/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    });
});
