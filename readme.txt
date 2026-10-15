=== Bitcoin Invoice Form ===

Contributors: coinsnap
Tags: bitcoin, lightning, invoice payments, bitcoin invoice, btcpay server
Requires at least: 6.2
Tested up to: 6.9.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers pay existing invoices with Bitcoin and Lightning directly on your WordPress site using Coinsnap or your own BTCPay Server.

== Description ==

= Accept Bitcoin invoice payments directly on WordPress =

Coinsnap Bitcoin Invoice Form adds a professional Bitcoin payment form to your WordPress website without changing how you create, send or manage invoices.

Your customer opens a dedicated payment page, enters the **invoice number** and **invoice amount**, and pays by scanning a QR code. Payments can be made via **Lightning or Bitcoin on-chain**.

The plugin is designed for freelancers, consultants, contractors, agencies, hotels, medical practices and other businesses that issue invoices and want to offer Bitcoin as an additional payment option.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

= Key features =

* Accept **Bitcoin and Lightning payments for existing invoices**
* Support for **Lightning and Bitcoin on-chain**
* Keep your existing invoicing and accounting workflow
* Create multiple invoice payment forms
* Place forms anywhere with a shortcode
* Configurable customer and invoice fields
* Invoice number and amount for clear reconciliation
* Per-form currency and payment settings
* Support for **Coinsnap** and **BTCPay Server**
* BTCPay Server setup wizard
* Optional **Bitcoin discount** as a fixed amount or percentage
* Optional customer-facing discount notice
* Transactions overview in WordPress
* Payment status and provider transaction details
* Email notification settings
* Success/error redirects and thank-you message
* Automatic webhook handling
* **Non-custodial / self-custody** payment model
* Optional fiat settlement through supported Coinsnap partners

= How it works =

1. Install and activate the plugin.
2. Connect Coinsnap or your own BTCPay Server.
3. Create an invoice payment form.
4. Configure its fields, currency and optional Bitcoin discount.
5. Add the generated shortcode to a WordPress page.
6. Add a “Pay with Bitcoin” link to your invoices.
7. The customer enters the invoice number and amount and pays via Lightning or on-chain Bitcoin.
8. The payment is recorded with the invoice reference for reconciliation.

= Invoice form fields =

Forms can include configurable fields such as:

* Customer name
* Email address
* Company
* Invoice number
* Invoice amount
* Description or message
* Custom payment button text

Fields can be enabled, disabled, required, renamed and reordered.

= Shortcode =

The current shortcode is:

[coinsnap_invoice_form id="123"]

Replace 123 with the ID of your invoice form.

The legacy shortcode [bif_invoice_form id="123"] remains supported for backward compatibility.

= Coinsnap or BTCPay Server =

The plugin supports two payment-provider modes:

* **Coinsnap** — accept Bitcoin and Lightning without operating your own payment infrastructure.
* **BTCPay Server** — connect your own self-hosted BTCPay Server.

= Bitcoin discount =

Each invoice form can optionally apply either:

* a **fixed discount**, or
* a **percentage discount**

The discount is applied before the payment invoice is created and can be shown to the customer with a custom notice.

= Settlement =

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be converted and settled to a bank account.

Coinsnap follows a non-custodial approach and does not hold merchant Bitcoin.

= More information =

* Product page: https://coinsnap.io/wp-plugins/bitcoin-invoice/
* WordPress Plugin Directory: https://wordpress.org/plugins/coinsnap-bitcoin-invoice-form/
* Live demo: https://invoice.coinsnap.org/
* GitHub: https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-form
* Coinsnap Wallet: https://coinsnap.io/wallet/
* Coinsnap API: https://docs.coinsnap.io/
* Coinsnap account: https://app.coinsnap.io/register

== Installation ==

1. In WordPress, go to **Plugins → Add New**.
2. Search for **Bitcoin Invoice Form**.
3. Click **Install Now** and activate the plugin.
4. Go to **Bitcoin Invoice Forms → Settings**.
5. Select Coinsnap or BTCPay Server and connect the payment provider.
6. Go to **Bitcoin Invoice Forms → Invoice Forms** and create a form.
7. Configure the form fields, currency and optional Bitcoin discount.
8. Copy the generated shortcode to a WordPress page.
9. Make a test payment.
10. Add the payment page URL to your invoices.

== Frequently Asked Questions ==

= Does this plugin create or issue invoices? =

No. It lets customers pay invoices that already exist. You continue using your normal accounting or invoicing workflow.

= Can customers pay with both Lightning and Bitcoin on-chain? =

Yes. The plugin supports both Lightning and Bitcoin on-chain.

= Can I use my own BTCPay Server? =

Yes. BTCPay Server is supported as an alternative to Coinsnap.

= Can I create multiple invoice forms? =

Yes. You can create multiple forms for different languages, brands or business use cases.

= Can I offer a Bitcoin discount? =

Yes. Each form can use either a fixed or percentage-based discount.

= How is the payment matched to the invoice? =

The customer enters the invoice number or reference in the form. This information is stored with the payment for reconciliation.

= Do I need my own Lightning node? =

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

= Does Coinsnap hold my Bitcoin? =

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

= Can I receive settlement in euros? =

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be converted and settled to a bank account.

== Screenshots ==

1. Install Bitcoin Invoice Form from the WordPress Plugin Directory
2. Coinsnap payment-provider settings
3. BTCPay Server payment-provider settings
4. BTCPay Server setup wizard
5. Invoice Forms list
6. Invoice Form configuration
7. Bitcoin discount configuration
8. Transactions overview
9. Bitcoin Invoice Form on the website
10. Bitcoin and Lightning payment QR code

== Changelog ==

= 1.1.0 :: 2026-03-01 =
* Added BTCPay Server connection wizard on the Settings page.
* Removed the Disable Webhook Verification setting.

= 1.0.3.2 :: 2026-02-11 =
* Updated Coinsnap and BTCPay Server error handling.

= 1.0.3.1 :: 2026-02-10 =
* Fixed the form ID variable in the main plugin class.

= 1.0.3 :: 2026-02-10 =
* Added Coinsnap and BTCPay Server AJAX connection checks.
* Added webhook AJAX check and registration functionality.

= 1.0.2 :: 2026-02-09 =
* Added Coinsnap and BTCPay Server connection checks in WordPress admin.
* Added webhook check and registration functionality.
* Fixed payment-provider override for individual forms.

= 1.0.1 :: 2026-01-23 =
* Added all currencies supported by Coinsnap.
* Updated plugin URI.
* Updated default field labels.

= 1.0.0 :: 2026-01-14 =
* First public WordPress.org release.
