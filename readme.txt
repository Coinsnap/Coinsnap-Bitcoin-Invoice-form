=== Bitcoin Invoice Form ===

Contributors: coinsnap
Tags: Lightning, bitcoin, invoice form, BTCPay
Tested up to: 6.8
Stable tag: 1.0.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate and embed customizable Bitcoin Invoice Forms on your website (Coinsnap & BTCPay server Integration).

== Description ==

= Accept Bitcoin invoice payments on your own website—fast, simple, and professional =

Are you a business owner, entrepreneur, shop operator, or contractor who sends lots of invoices—and increasingly hears, “**Can I pay this in Bitcoin?**” 

With the **Coinsnap Bitcoin Invoice Form** you can add a “Pay with Bitcoin” link to any invoice and let customers settle in seconds, right on your site.

Here’s how it works: your customer opens the link, enters the **invoice amount**, the **invoice number** for matching, and an **optional name/message**, then clicks “**Pay invoice with Bitcoin**”. A payment screen with a **QR code** appears that can be paid via **Bitcoin Lightning** (or **on-chain**, if preferred). 

That’s it—no redirects, no confusion.

= Why merchants love it =

* **Frictionless invoice payments**: A clean, trust-building form that lives on your domain.
* **Lightning-fast checkout**: Accept **Lightning** (ideal for small/medium invoices) and optionally **on-chain** for higher amounts.
* **Fair, real-time pricing**: Automatic rate lock at the moment of payment—no volatility guesswork.
* **Works with your stack**: Use with a **Coinsnap** account—or connect to **your own BTCPay Server**.
* **Optional fiat settlement**: Prefer EUR on your bank account? Pair Coinsnap with Bringin/DFX.
* **Conversion booster**: Offer an optional **Bitcoin discount** (e.g., 5%) to nudge faster payments.

= Get started in minutes =

1. Install and activate the plugin.
2. Connect to Coinsnap (or your BTCPay Server).
3. Add the Invoice Form via shortcode to a page like /bitcoin.
4. Put a “**Pay with Bitcoin**” link on your invoices.

Give your clients the modern payment option they’re asking for—and get paid faster with Bitcoin and Lightning, directly on your website.


= More information: =

* Demo Store: [https://invoice.coinsnap.org/](https://invoice.coinsnap.org/)
* Product page: [https://coinsnap.io/coinsnap-bitcoin-invoice-form-plugin/](https://coinsnap.io/coinsnap-bitcoin-invoice-form-plugin/)
* Installation Guide: [https://coinsnap.io/coinsnap-bitcoin-invoice-form-installation-guide/](https://coinsnap.io/coinsnap-bitcoin-invoice-form-installation-guide/)
* GitHub: [https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-Form](https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-Form)


= Documentation: =

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/en/faq/)
* [Terms and Conditions](https://coinsnap.io/en/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/en/privacy/)


== Installation ==

= Initial Setup =

1. **Access Settings**
   * Go to WordPress Admin → Bitcoin Invoice Forms → Settings

2. **General Settings**
   * **Default Payment Gateway**: Choose between CoinSnap or BTCPay Server
   * **Default Amount**: Set default invoice amount
   * [Removed] Default Currency: Currency is now configured per form in each Invoice Form’s Payment settings.

= Payment Gateway Setup =

= CoinSnap Configuration =

1. **Get API Key**
   * Sign up at [CoinSnap.io](https://coinsnap.io)
   * Generate an API key from your dashboard

2. **Configure in WordPress**
   * Go to Bitcoin Invoice Forms → Settings
   * Enter your CoinSnap API key
   * Set webhook URL (automatically configured)

= BTCPay Server Configuration =

1. **Set Up BTCPay Server**
   * Install BTCPay Server or use a hosted service
   * Create a store and generate API credentials

2. **Configure in WordPress**
   * Go to Bitcoin Invoice Forms → Settings
   * Enter BTCPay Server URL
   * Add API key and store ID
   * Configure webhook URL

= Creating Invoice Forms =

= Basic Form Creation =

1. **Create New Form**
   * Go to Bitcoin Invoice Forms → Invoice Forms
   * Click "Add New"

2. **Configure Form Fields**
   * **Name Field**: Customer name (required by default)
   * **Email Field**: Customer email (required by default)
   * **Company Field**: Company name (optional)
   * **Invoice Number Field**: Invoice reference (required by default)
   * **Amount Field**: Payment amount (required by default)
   * **Description Field**: Additional notes (optional)
   * **Button Text**: Set submit button text (default: "Pay with Bitcoin")

3. **Field Configuration Options**
   * **Enabled**: Toggle field visibility
   * **Required**: Make field mandatory
   * **Label**: Customize field label
   * **Order**: Set field display order

= Discounts =

1. Enable Discounts
   * Go to Bitcoin Invoice Forms → Invoice Forms → Add New (or edit an existing form)
   * In the Fields metabox, open the “Discount” section
   * Check “Enable Discount”

2. Choose Discount Type
   * Fixed amount: subtracts an absolute value (e.g., 5.00) from the total
   * Percentage: subtracts a percentage of the amount (e.g., 10%)

3. Set Discount Amount
   * Enter a positive value for the selected type
   * The plugin guarantees the total never goes below zero

4. Customer-facing Notice (Optional)
   * Add your own message to be shown on the form when a discount is active
   * If left empty, a friendly default message is auto-generated based on your type and amount
   * The notice is sanitized and shown above the submit button

5. How It’s Applied
   * The discount is applied server-side before creating the invoice with your payment provider (CoinSnap/BTCPay)
   * Currency handling follows the form/default settings; fixed discounts are in the selected currency

=  Payment Settings =

1. **Payment Configuration**
   * **Payment Gateway**: Override default gateway for this form
   * **Default Amount**: Set default invoice amount
   * **Currency**: Choose display currency
   * **Description**: Default payment description

=  Email Settings =

1. **Email Configuration**
   * **Admin Email**: Email address for notifications
   * **Email Subject**: Subject line for payment notifications
   * **Email Template**: Customize notification content with placeholders

2. **Available Placeholders**
   * `{invoice_number}`: Invoice number
   * `{customer_name}`: Customer name
   * `{customer_email}`: Customer email
   * `{amount}`: Payment amount
   * `{currency}`: Currency
   * `{payment_status}`: Payment status
   * `{transaction_id}`: Transaction ID
   * `{payment_provider}`: Payment provider
   * `{description}`: Invoice description

= Redirect Settings =

1. **Redirect Configuration**
   * **Success Page**: Redirect after successful payment
   * **Error Page**: Redirect on payment failure
   * **Thank You Message**: Custom success message

= Managing Transactions =

= Transaction Dashboard =

1. **Access Transactions**
   * Go to Bitcoin Invoice Forms → Transactions
   * View all invoice transactions

2. **Transaction Information**
   * Transaction ID
   * Form name
   * Invoice number
   * Customer details
   * Payment status
   * Amount and currency
   * Payment date

3. **Filtering Options**
   * Filter by form
   * Filter by payment status (Paid/Unpaid/Failed/Refunded)
   * Filter by date range

4. **Transaction Actions**
   * View payment details
   * Access payment provider transaction page

== Upgrade Notice ==

Follow updates on plugin's GitHub page:

[https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-Form](https://github.com/Coinsnap/Coinsnap-Bitcoin-Invoice-Form)

=== Frequently Asked Questions ===

Plugin's page on Coinsnap website: [https://coinsnap.io/coinsnap-bitcoin-invoice-form-plugin/](https://coinsnap.io/coinsnap-bitcoin-invoice-form-plugin/)

=== Screenshots ===


=== Changelog ===

= 1.0.0 :: 2025-09-26 =
* First tests.
