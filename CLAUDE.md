# Project Context

WebLelang: online auction platform for consigned goods (barang titipan). Indonesian UI.
Full plan/research: `docs/RENCANA.md`. Stack mirrors `randisun05/asprov1` (web-aspro).

## Stack
- Laravel 12 (PHP 8.3, platform pinned in composer.json), Inertia 2 + Vue 3 (`<script setup>`), Vite, Tailwind v4
- Fortify auth (Inertia views in `FortifyServiceProvider`), 2FA mandatory for super_admin/admin (`EnsureTwoFactorEnabled`)
- Ziggy `route()` in Vue; SweetAlert2 for flash toasts & confirmations

## Structure
- `app/Services/Auction/` — reusable engine: `BidService` (locking, proxy bids, anti-sniping, hash chain),
  `BidIncrement`, `LotCloser` (open/close lots, invoices). Scheduler: `auctions:tick` in `routes/console.php`.
- `app/Services/{InvoiceService,SettlementService,MidtransService,ImageService,AuditLogger}.php`
- Controllers split `Admin/`, `Public/`, `User/`; pages in `resources/js/Pages/{Admin,Public,User,Auth}`.
- `app/Support/Present.php` builds Inertia props — never send `reserve_price` to bidders.
- Auction methods (`AuctionMethod` on `auctions.method`): open / sealed / live. `Lot::isConcealed()` = sealed lot not yet
  closed → never expose amount, count, or leader anywhere (incl. admin); sealed bids don't touch `current_price`/`leader_id`
  until `LotCloser::close()` resolves the winner. Live lots are opened/closed only by the auctioneer console
  (`LiveAuctionController`); the scheduler skips them. `Lot` always eager-loads `auction`.
- Money is integer rupiah everywhere. Status enums in `app/Enums` (label/color used by `StatusBadge.vue`).
- Business rules in `config/auction.php`.
- Payments (`app/Payments`): `PaymentManager` resolves drivers (midtrans/xendit/simulator — simulator refused in
  production) implementing `Contracts\PaymentGateway` + `Contracts\PayoutGateway`. `PaymentService` (checkout →
  webhook → fulfil Invoice/AuctionRegistration) and `PayoutService` (Settlement payout, deposit refunds; one active
  payout per payable) are generic over morph `payable`. Webhooks: `/payments/webhook/{gateway}`, `/payouts/webhook/{gateway}`.
  Tests fake HTTP with `Http::fake`; never call real gateways in tests.
- Real-time: `App\Events\LotUpdated` (Reverb) is only a "changed" signal (lot_id + reason); clients refetch
  `lots.state`, so never put prices in broadcast payloads. Frontend helper `resources/js/lib/realtime.js`.
- SEO: controllers call `App\Support\Seo::set()/lot()/auction()`; rendered in `app.blade.php`. Sitemap/robots in
  `Public\SeoController`. Public item photos go through `ImageService` with `watermark: true`.
- Online consignment intake: `ConsignmentIntakeService` (photos on private disk until accepted).
- Security: Turnstile via `VerifyTurnstile` (route-name list), `FraudDetector` + `ScanLotForFraud` job.
- Notifications (`app/Notifications`) extend `AuctionNotification` (mail + database [+ broadcast], queued after commit) —
  production needs `php artisan queue:work`. PDFs via `DocumentService` (dompdf, views in `resources/views/pdf`),
  Excel via `app/Exports` (maatwebsite/excel 4). Consignor portal = temporary signed URL + revocable `portal_nonce`.

## Conventions
- State-changing actions are POST/PUT/DELETE, authorized server-side (`role:` middleware), logged via `AuditLogger`.
- Sensitive fields use `encrypted` casts; KYC/payment proofs on the private `local` disk, served via controllers.
- Run `vendor/bin/pint` and `php artisan test` before committing; `npm run build` after frontend changes.
