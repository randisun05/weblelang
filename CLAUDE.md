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
- Money is integer rupiah everywhere. Status enums in `app/Enums` (label/color used by `StatusBadge.vue`).
- Business rules in `config/auction.php`.

## Conventions
- State-changing actions are POST/PUT/DELETE, authorized server-side (`role:` middleware), logged via `AuditLogger`.
- Sensitive fields use `encrypted` casts; KYC/payment proofs on the private `local` disk, served via controllers.
- Run `vendor/bin/pint` and `php artisan test` before committing; `npm run build` after frontend changes.
