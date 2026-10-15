# Coinsnap Bitcoin Invoice Form for WordPress

**Bitcoin invoice payment plugin for WordPress — let customers pay existing invoices with Bitcoin and Lightning using Coinsnap or your own BTCPay Server.**

Coinsnap Bitcoin Invoice Form adds a professional Bitcoin payment form to a WordPress website without changing how invoices are created, sent or managed.

Customers open a dedicated payment page, enter their **invoice number** and **invoice amount**, and complete the payment by scanning a QR code. Payments can be made via **Lightning or Bitcoin on-chain**.

The plugin is especially useful for freelancers, consultants, contractors, agencies, hotels, medical practices and other businesses that issue invoices and want to add Bitcoin as an additional payment option.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments for existing invoices**
- Support for **Lightning and Bitcoin on-chain**
- Keep your existing invoicing and accounting workflow
- Create multiple invoice payment forms
- Place forms anywhere in WordPress with a shortcode
- Configurable customer and invoice fields
- Invoice number and amount for clear reconciliation
- Per-form currency and payment settings
- Support for **Coinsnap** and **BTCPay Server**
- BTCPay Server setup wizard
- Optional **Bitcoin discount** as a fixed amount or percentage
- Optional customer-facing discount notice
- Payment transaction overview in WordPress
- Payment status and provider transaction details
- Email notification settings
- Success/error redirects and thank-you message
- Automatic webhook handling
- **Non-custodial / self-custody** payment model
- Optional fiat settlement through supported Coinsnap partners

## Current public release

The current WordPress.org release is **1.1.0**.

WordPress.org currently lists:

- WordPress **6.2 or newer**
- PHP **7.4 or newer**
- Tested up to **WordPress 6.9.9**

> Note for developers: the plugin header in the current GitHub `main` branch still reports version 1.0.0 and older compatibility metadata. The public WordPress.org release is newer, so the repository code metadata should be synchronized separately.

## How it works

1. Install and activate the plugin.
2. Connect **Coinsnap** or your own **BTCPay Server**.
3. Create an invoice payment form.
4. Configure the fields, currency, optional Bitcoin discount and payment settings.
5. Add the generated shortcode to a WordPress page.
6. Add a “Pay with Bitcoin” link to your invoices.
7. The customer opens the page, enters the invoice number and amount, then pays via Lightning or on-chain Bitcoin.
8. The payment is recorded with the invoice reference for reconciliation.

## Invoice form fields

Forms can include configurable fields such as:

- Customer name
- Email address
- Company
- Invoice number
- Invoice amount
- Description or message
- Custom payment button text

Fields can be enabled, disabled, required, renamed and reordered.

## Shortcode

The current shortcode is:

`[coinsnap_invoice_form id="123"]`

Replace `123` with the ID of your invoice form.

The legacy shortcode `[bif_invoice_form id="123"]` remains supported for backward compatibility.

## Coinsnap or BTCPay Server

The plugin supports two payment-provider setups:

- **Coinsnap** — for users who want to accept Bitcoin and Lightning without operating their own payment infrastructure.
- **BTCPay Server** — for users who want to connect their own self-hosted BTCPay Server.

## Bitcoin discount

Each invoice form can optionally offer a Bitcoin discount.

You can configure either:

- a **fixed discount**, or
- a **percentage discount**

The discount is applied before the Bitcoin invoice is created and can be displayed to the customer with a custom notice.

## Settlement

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be converted and settled to a bank account.

Coinsnap follows a non-custodial approach and does not hold merchant Bitcoin.

## Installation

### Install from WordPress.org

1. In WordPress, go to **Plugins → Add New**.
2. Search for **Bitcoin Invoice Form**.
3. Click **Install Now** and activate the plugin.
4. Go to **Bitcoin Invoice Forms → Settings**.
5. Connect Coinsnap or BTCPay Server.
6. Create an invoice form.
7. Add its shortcode to a WordPress page.
8. Make a test payment before sharing the payment URL with customers.

### Install from GitHub

1. Download this repository as a ZIP archive.
2. Upload it through **Plugins → Add New → Upload Plugin**.
3. Install and activate the plugin.
4. Configure the payment provider and create an invoice form.

## Resources

- [Coinsnap Bitcoin Invoice for WordPress](https://coinsnap.io/wp-plugins/bitcoin-invoice/)
- [WordPress Plugin Directory](https://wordpress.org/plugins/coinsnap-bitcoin-invoice-form/)
- [Live demo](https://invoice.coinsnap.org/)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Coinsnap website](https://coinsnap.io/)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### Does this plugin create invoices?

No. The plugin is designed to let customers **pay invoices that already exist**. You continue creating invoices with your existing accounting or billing workflow.

### Can customers pay with both Lightning and Bitcoin on-chain?

Yes. The payment flow supports both Lightning and Bitcoin on-chain.

### Can I use my own BTCPay Server?

Yes. The plugin supports BTCPay Server as an alternative to Coinsnap.

### Can I create multiple invoice forms?

Yes. You can create multiple forms, for example for different languages, business units or brands.

### Can I offer a Bitcoin discount?

Yes. Each form can apply either a fixed or percentage-based Bitcoin discount.

### How is the payment matched to the invoice?

The customer enters the invoice number or reference in the form. This reference is stored with the payment information for reconciliation.

### Do I need my own Lightning node?

No. When using Coinsnap, you do not need to operate your own Lightning node or Bitcoin payment server.

### Does Coinsnap hold my Bitcoin?

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

### Can I receive settlement in euros?

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be converted and settled to a bank account.

### Is the plugin open source?

Yes. The plugin is open source under GPL-2.0.

## Changelog

### 1.1.0 — 2026-03-01
- Added BTCPay Server connection wizard on the Settings page.
- Removed the “Disable Webhook Verification” setting.

### 1.0.3.2 — 2026-02-11
- Updated Coinsnap and BTCPay Server error handling.

### 1.0.3.1 — 2026-02-10
- Fixed the form ID variable in the main plugin class.

### 1.0.3 — 2026-02-10
- Added Coinsnap and BTCPay Server AJAX connection checks.
- Added webhook AJAX check and registration functionality.

### 1.0.2 — 2026-02-09
- Added Coinsnap and BTCPay Server connection checks in WordPress admin.
- Added webhook check and registration functionality.
- Fixed payment-provider override for individual forms.

### 1.0.1 — 2026-01-23
- Added all currencies supported by Coinsnap.
- Updated the plugin URI.
- Updated default field labels.

### 1.0.0 — 2026-01-14
- First public WordPress.org release.
