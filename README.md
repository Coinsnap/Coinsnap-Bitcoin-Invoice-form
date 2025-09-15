# Bitcoin Invoice Form Plugin

A WordPress plugin that enables merchants to generate and embed customizable Bitcoin Invoice Forms on their website. Customers can complete and pay invoices directly on the merchant's site with payment processing via CoinSnap or BTCPay Server.

## Features

### Core Functionality
- **Bitcoin Payment Integration**: Accept Bitcoin payments for invoice processing
- **Multiple Payment Gateways**: Support for CoinSnap and BTCPay Server
- **Custom Invoice Forms**: Create customizable invoice forms with configurable fields
- **Transaction Management**: Comprehensive transaction tracking and management
- **Email Notifications**: Automatic email notifications for successful payments
- **Test Mode**: Test payments without real transactions

### Payment Gateways
- **CoinSnap**: Lightning Network payments with instant confirmation
- **BTCPay Server**: Self-hosted Bitcoin payment processing

### Invoice Form Fields
- **Name**: Customer name (required)
- **Email**: Customer email address (required)
- **Company**: Company name (optional)
- **Invoice Number**: Invoice reference number (required)
- **Amount**: Payment amount (required)
- **Description/Notes**: Additional invoice details (optional)

## Installation

1. **Upload Plugin Files**
   - Upload the plugin folder to `/wp-content/plugins/`
   - Or install via WordPress admin → Plugins → Add New → Upload Plugin

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Bitcoin Invoice Form" and click "Activate"

3. **Verify Installation**
   - Check that "Bitcoin Invoice Forms" appears in your WordPress admin sidebar

## Configuration

### Initial Setup

1. **Access Settings**
   - Go to WordPress Admin → Bitcoin Invoice Forms → Settings

2. **General Settings**
   - **Default Payment Gateway**: Choose between CoinSnap or BTCPay Server
   - **Default Amount**: Set default invoice amount
   - **Default Currency**: Choose default currency (USD, EUR, CHF, JPY, SATS)

### Payment Gateway Setup

#### CoinSnap Configuration
1. **Get API Key**
   - Sign up at [CoinSnap.io](https://coinsnap.io)
   - Generate an API key from your dashboard

2. **Configure in WordPress**
   - Go to Bitcoin Invoice Forms → Settings
   - Enter your CoinSnap API key
   - Set webhook URL (automatically configured)

#### BTCPay Server Configuration
1. **Set Up BTCPay Server**
   - Install BTCPay Server or use a hosted service
   - Create a store and generate API credentials

2. **Configure in WordPress**
   - Go to Bitcoin Invoice Forms → Settings
   - Enter BTCPay Server URL
   - Add API key and store ID
   - Configure webhook URL

## Creating Invoice Forms

### Basic Form Creation

1. **Create New Form**
   - Go to Bitcoin Invoice Forms → Invoice Forms
   - Click "Add New"

2. **Configure Form Fields**
   - **Name Field**: Customer name (required by default)
   - **Email Field**: Customer email (required by default)
   - **Company Field**: Company name (optional)
   - **Invoice Number Field**: Invoice reference (required by default)
   - **Amount Field**: Payment amount (required by default)
   - **Description Field**: Additional notes (optional)
   - **Button Text**: Set submit button text (default: "Pay with Bitcoin")

3. **Field Configuration Options**
   - **Enabled**: Toggle field visibility
   - **Required**: Make field mandatory
   - **Label**: Customize field label
   - **Order**: Set field display order

### Payment Settings

1. **Payment Configuration**
   - **Payment Gateway**: Override default gateway for this form
   - **Default Amount**: Set default invoice amount
   - **Currency**: Choose display currency
   - **Description**: Default payment description

### Email Settings

1. **Email Configuration**
   - **Admin Email**: Email address for notifications
   - **Email Subject**: Subject line for payment notifications
   - **Email Template**: Customize notification content with placeholders

2. **Available Placeholders**
   - `{invoice_number}`: Invoice number
   - `{customer_name}`: Customer name
   - `{customer_email}`: Customer email
   - `{amount}`: Payment amount
   - `{currency}`: Currency
   - `{payment_status}`: Payment status
   - `{transaction_id}`: Transaction ID
   - `{payment_provider}`: Payment provider
   - `{description}`: Invoice description

### Redirect Settings

1. **Redirect Configuration**
   - **Success Page**: Redirect after successful payment
   - **Error Page**: Redirect on payment failure
   - **Thank You Message**: Custom success message

## Managing Transactions

### Transaction Dashboard

1. **Access Transactions**
   - Go to Bitcoin Invoice Forms → Transactions
   - View all invoice transactions

2. **Transaction Information**
   - Transaction ID
   - Form name
   - Invoice number
   - Customer details
   - Payment status
   - Amount and currency
   - Payment date

3. **Filtering Options**
   - Filter by form
   - Filter by payment status (Paid/Unpaid/Failed/Refunded)
   - Filter by date range

4. **Transaction Actions**
   - View payment details
   - Access payment provider transaction page

## Shortcodes

### Basic Form Shortcode

```php
[bif_invoice_form id="123"]
```

**Parameters:**
- `id` (required): Form ID from Invoice Forms

### Advanced Shortcode Options

```php
[bif_invoice_form id="123" class="custom-class" style="border: 2px solid #000;"]
```

**Parameters:**
- `id` (required): Form ID
- `class`: Additional CSS classes
- `style`: Inline CSS styles

### Form Display Examples

1. **Simple Form**
   ```php
   [bif_invoice_form id="1"]
   ```

2. **Styled Form**
   ```php
   [bif_invoice_form id="1" class="invoice-form" style="max-width: 500px; margin: 0 auto;"]
   ```

## Advanced Customization

### Custom CSS Styling

1. **Form Styling**
   ```css
   .bif-form {
     background: #f9f9f9;
     padding: 30px;
     border-radius: 8px;
     box-shadow: 0 4px 6px rgba(0,0,0,0.1);
   }
   
   .bif-button {
     background: linear-gradient(45deg, #f7931a, #ff6b35);
     border: none;
     padding: 15px 30px;
     font-size: 18px;
     font-weight: bold;
   }
   ```

2. **Field Styling**
   ```css
   .bif-input {
     border: 2px solid #e1e5e9;
     border-radius: 6px;
     padding: 12px 16px;
     font-size: 16px;
   }
   
   .bif-input:focus {
     border-color: #f7931a;
     box-shadow: 0 0 0 3px rgba(247, 147, 26, 0.1);
   }
   ```

### Custom Hooks and Filters

1. **Form Display Filter**
   ```php
   add_filter('bif_form_html', function($html, $form_id) {
       // Modify form HTML before display
       return $html;
   }, 10, 2);
   ```

2. **Payment Success Action**
   ```php
   add_action('bif_payment_success', function($transaction_id, $payment_data) {
       // Custom action after successful payment
       error_log('Payment successful for transaction: ' . $transaction_id);
   }, 10, 2);
   ```

3. **Email Template Filter**
   ```php
   add_filter('bif_email_template', function($template, $transaction_id) {
       // Modify email template
       return $template;
   }, 10, 2);
   ```

## Troubleshooting

### Common Issues

#### Payment Not Processing

**Symptoms:**
- Payment created but not confirmed
- Transaction not marked as paid

**Solutions:**
1. **Check Webhook Configuration**
   ```
   - Verify webhook URL is correct
   - Check webhook is enabled in payment gateway
   - Test webhook delivery
   ```

2. **Verify API Credentials**
   ```
   - Check API key is valid and active
   - Ensure proper permissions
   - Test API connection
   ```

3. **Check Logs**
   ```
   - Go to Bitcoin Invoice Forms → Logs
   - Look for error messages
   - Check payment gateway logs
   ```

#### Form Display Issues

**Symptoms:**
- Form not displaying
- Styling problems
- JavaScript errors

**Solutions:**
1. **Check Shortcode**
   ```
   - Verify form ID is correct
   - Check shortcode syntax
   - Test with default form
   ```

2. **Clear Cache**
   ```
   - Clear WordPress cache
   - Clear CDN cache
   - Clear browser cache
   ```

3. **Check Theme Compatibility**
   ```
   - Test with default theme
   - Check for CSS conflicts
   - Review JavaScript errors
   ```

### Debug Mode

1. **Enable Debug Mode**
   ```
   - Go to Bitcoin Invoice Forms → Settings
   - Advanced Settings → Log Level
   - Set to "Debug"
   ```

2. **Monitor Logs**
   ```
   - Go to Bitcoin Invoice Forms → Logs
   - Watch for detailed debug information
   - Check for error patterns
   ```

## System Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **SSL Certificate**: Required for payment processing
- **cURL**: Required for API communications

## Security Features

- **Nonce Verification**: All form submissions are protected with WordPress nonces
- **Input Sanitization**: All user inputs are sanitized and validated
- **Webhook Verification**: Payment webhooks are verified for authenticity
- **SQL Injection Protection**: All database queries use prepared statements
- **XSS Protection**: All outputs are escaped

## Performance Optimization

1. **Database Optimization**
   ```sql
   -- Clean up old logs
   DELETE FROM wp_options WHERE option_name LIKE 'bif_log_%' 
   AND option_value < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

2. **Caching**
   ```php
   // Add caching for form data
   add_filter('bif_form_cache', '__return_true');
   ```

## Support

### Getting Help

1. **Documentation**
   - Review this README thoroughly
   - Check WordPress admin help sections
   - Consult payment gateway documentation

2. **Logs and Debugging**
   - Enable debug mode
   - Check plugin logs
   - Review server error logs

### Plugin Updates

1. **Automatic Updates**
   - Plugin updates automatically when available
   - Test updates on staging site first
   - Backup before major updates

2. **Manual Updates**
   - Download latest version
   - Deactivate current plugin
   - Upload new version
   - Reactivate plugin

---

## Changelog

### Version 0.1.0
- Initial release
- CoinSnap and BTCPay Server integration
- Custom invoice form builder
- Transaction management
- Email notifications
- Webhook handling
- Admin interface

---

**Plugin Version**: 0.1.0  
**Last Updated**: 2025  
**WordPress Compatibility**: 5.8+  
**PHP Compatibility**: 7.4+
