# Coinsnap Bitcoin Invoice Form

* Contributors: coinsnap
* Tags: Lightning, bitcoin, invoice form, BTCPay
* Requires at least: 6.2
* Tested up to: 7.0
* Requires PHP: 7.4
* Stable tag: 1.1.0
* License: GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate and embed customizable Bitcoin Invoice Forms on your website (Coinsnap & BTCPay server Integration).

## Description

### Accept Bitcoin invoice payments on your own website—fast, simple, and professional

Are you a business owner, entrepreneur, shop operator, or contractor who sends lots of invoices—and increasingly hears, "**Can I pay this in Bitcoin?**"

With the **Coinsnap Bitcoin Invoice Form** you can add a "Pay with Bitcoin" link to any invoice and let customers settle in seconds, right on your site.

Here's how it works: your customer opens the link, enters the **invoice amount**, the **invoice number** for matching, and an **optional name/message**, then clicks "**Pay invoice with Bitcoin**". A payment screen with a **QR code** appears that can be paid via **Bitcoin Lightning** (or **on-chain**, if preferred).

That's it—no redirects, no confusion.

### Why merchants love it

* **Frictionless invoice payments**: A clean, trust-building form that lives on your domain.
* **Lightning-fast checkout**: Accept **Lightning** (ideal for small/medium invoices) and optionally **on-chain** for higher amounts.
* **Fair, real-time pricing**: Automatic rate lock at the moment of payment—no volatility guesswork.
* **Works with your stack**: Use with a **Coinsnap** account—or connect to **your own BTCPay Server**.
* **Optional fiat settlement**: Prefer EUR on your bank account? Pair Coinsnap with Bringin/DFX.
* **Conversion booster**: Offer an optional **Bitcoin discount** (e.g., 5%) to nudge faster payments.

### Get started in minutes

1. Install and activate the plugin.
2. Connect to Coinsnap (or your BTCPay Server).
3. Add the Invoice Form via shortcode to a page like /bitcoin.
4. Put a "**Pay with Bitcoin**" link on your invoices.

Give your clients the modern payment option they're asking for—and get paid faster with Bitcoin and Lightning, directly on your website.

### More information

* Demo Store: [https://invoice.coinsnap.org/](https://invoice.coinsnap.org/)
* Product page: [https://coinsnap.io/coinsnap-bitcoin-invoice-form-plugin/](https://coinsnap.io/coinsnap-bitcoin-invoice-form-plugin/)
* Installation Guide: [https://coinsnap.io/coinsnap-bitcoin-invoice-form-installation-guide/](https://coinsnap.io/coinsnap-bitcoin-invoice-form-installation-guide/)
* GitHub: [https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-Form](https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-Form)

### Documentation

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/en/faq/)
* [Terms and Conditions](https://coinsnap.io/en/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/en/privacy/)

## Changelog

#### 1.1.0 :: 2026-03-01
* Update: added BTCPay server connection wizard on Settings page.
* Update: deleted setting Disable Webhook Verification.

#### 1.0.3.2 :: 2026-02-11
* Updated Coinsnap and BTCPay server errors handler.

#### 1.0.3.1 :: 2026-02-10
* Fixed: form_id var in main class file.

#### 1.0.3 :: 2026-02-10
* Update: added Coinsnap and BTCPay server AJAX connection check in admin.
* Update: added webhook AJAX check and registration functionality.

#### 1.0.2 :: 2026-02-09
* Update: added Coinsnap and BTCPay server connection check in admin.
* Update: added webhook check and registration functionality.
* Fixed: payment provider override functionality for a specific form.

#### 1.0.1 :: 2026-01-23
* Update: added all the currencies supported by Coinsnap.
* Updated plugin URI.
* Updated default field labels for invoice form.

#### 1.0.0 :: 2026-01-14
* Plugin is published in WordPress plugin directory.
