/**
 * Bitcoin Invoice Form - Admin JavaScript
 */

(function($) {
    'use strict';

    // Wait until the DOM is fully loaded
    $(document).ready(function() {
        const $paymentProviderSelector = $('select[name="bif_settings[payment_provider]"]');
        const $coinsnapWrapper = $('#bif-coinsnap-settings-wrapper');
        const $btcpayWrapper = $('#bif-btcpay-settings-wrapper');

        // Function to toggle payment provider settings
        function togglePaymentProviderSettings() {
            if (!$paymentProviderSelector || !$paymentProviderSelector.length) {
                return;
            }
            
            const selectedProvider = $paymentProviderSelector.val();
            
            // Hide all payment provider settings with fade effect
            $coinsnapWrapper.fadeOut(300);
            $btcpayWrapper.fadeOut(300);
            
            // Show the selected provider's settings with fade effect
            if (selectedProvider === 'coinsnap') {
                $coinsnapWrapper.fadeIn(300);
            } else if (selectedProvider === 'btcpay') {
                $btcpayWrapper.fadeIn(300);
            }
        }

        // Initialize the display on page load
        // Use setTimeout to ensure DOM is fully ready
        setTimeout(function() {
            togglePaymentProviderSettings();
        }, 100);

        // Bind change events
        $paymentProviderSelector.on('change', togglePaymentProviderSettings);

        // Add smooth transitions
        $('.provider-settings').css({
            'transition': 'opacity 0.3s ease-in-out',
            'opacity': '1'
        });

        // Initialize other admin features
        initAdminFeatures();
    });

    /**
     * Initialize admin features
     */
    function initAdminFeatures() {
        // Handle form field toggles
        $('.bif-field-config input[type="checkbox"]').on('change', handleFieldToggle);
        
        // Handle transaction details modal
        $('.bif-view-details').on('click', handleViewDetails);
        
        // Handle modal close
        $('.bif-modal-close').on('click', closeModal);
        $(window).on('click', handleModalClick);
    }

    /**
     * Handle field toggle
     */
    function handleFieldToggle() {
        var checkbox = $(this);
        var fieldConfig = checkbox.closest('.bif-field-config');
        var fieldInputs = fieldConfig.find('input[type="text"], input[type="number"], select');
        
        if (checkbox.is(':checked')) {
            fieldInputs.prop('disabled', false);
        } else {
            fieldInputs.prop('disabled', true);
        }
    }

    /**
     * Handle view details click
     */
    function handleViewDetails(e) {
        e.preventDefault();
        
        var button = $(this);
        var transactionId = button.data('transaction-id');
        
        if (!transactionId) {
            console.error('Transaction ID not found');
            return;
        }
        
        // Show loading state
        var modal = $('#bif-transaction-modal');
        modal.find('#bif-transaction-details').html('<p>Loading transaction details...</p>');
        modal.show();
        
        // Load transaction details via AJAX
        $.ajax({
            url: bifRestUrl.restUrl + 'transactions/' + transactionId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': bifRestUrl.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayTransactionDetails(response.data);
                } else {
                    modal.find('#bif-transaction-details').html('<p>Error loading transaction details: ' + response.message + '</p>');
                }
            },
            error: function(xhr) {
                var message = 'Error loading transaction details';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                modal.find('#bif-transaction-details').html('<p>' + message + '</p>');
            }
        });
    }

    /**
     * Display transaction details
     */
    function displayTransactionDetails(data) {
        var detailsHtml = '<div class="bif-transaction-details">';
        
        detailsHtml += '<h3>Transaction Information</h3>';
        detailsHtml += '<table class="widefat">';
        detailsHtml += '<tr><td><strong>Transaction ID:</strong></td><td>' + escapeHtml(data.transaction_id) + '</td></tr>';
        detailsHtml += '<tr><td><strong>Invoice Number:</strong></td><td>' + escapeHtml(data.invoice_number || 'N/A') + '</td></tr>';
        detailsHtml += '<tr><td><strong>Amount:</strong></td><td>' + escapeHtml(data.amount) + ' ' + escapeHtml(data.currency) + '</td></tr>';
        detailsHtml += '<tr><td><strong>Status:</strong></td><td><span class="bif-status bif-status-' + escapeHtml(data.payment_status) + '">' + escapeHtml(data.payment_status) + '</span></td></tr>';
        detailsHtml += '<tr><td><strong>Payment Provider:</strong></td><td>' + escapeHtml(data.payment_provider) + '</td></tr>';
        detailsHtml += '<tr><td><strong>Created:</strong></td><td>' + escapeHtml(data.created_at) + '</td></tr>';
        detailsHtml += '<tr><td><strong>Updated:</strong></td><td>' + escapeHtml(data.updated_at) + '</td></tr>';
        detailsHtml += '</table>';
        
        detailsHtml += '<h3>Customer Information</h3>';
        detailsHtml += '<table class="widefat">';
        detailsHtml += '<tr><td><strong>Name:</strong></td><td>' + escapeHtml(data.customer_name) + '</td></tr>';
        detailsHtml += '<tr><td><strong>Email:</strong></td><td>' + escapeHtml(data.customer_email) + '</td></tr>';
        if (data.customer_company) {
            detailsHtml += '<tr><td><strong>Company:</strong></td><td>' + escapeHtml(data.customer_company) + '</td></tr>';
        }
        detailsHtml += '</table>';
        
        if (data.description) {
            detailsHtml += '<h3>Description</h3>';
            detailsHtml += '<p>' + escapeHtml(data.description) + '</p>';
        }
        
        if (data.payment_url) {
            detailsHtml += '<h3>Actions</h3>';
            detailsHtml += '<p><a href="' + escapeHtml(data.payment_url) + '" target="_blank" class="button">View Payment</a></p>';
        }
        
        detailsHtml += '</div>';
        
        $('#bif-transaction-details').html(detailsHtml);
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('.bif-modal').hide();
    }

    /**
     * Handle modal click
     */
    function handleModalClick(event) {
        if (event.target.classList.contains('bif-modal')) {
            closeModal();
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Handle bulk actions
     */
    function handleBulkAction(action) {
        var selectedItems = $('input[name="transaction[]"]:checked');
        
        if (selectedItems.length === 0) {
            alert('Please select at least one transaction.');
            return;
        }
        
        var ids = [];
        selectedItems.each(function() {
            ids.push($(this).val());
        });
        
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete the selected transactions?')) {
                return;
            }
        }
        
        // Perform bulk action
        $.ajax({
            url: bifRestUrl.restUrl + 'transactions/bulk-action',
            method: 'POST',
            data: {
                action: action,
                ids: ids
            },
            headers: {
                'X-WP-Nonce': bifRestUrl.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while performing the bulk action.');
            }
        });
    }

    /**
     * Handle form validation
     */
    function validateForm(form) {
        var isValid = true;
        var errors = [];
        
        // Check required fields
        form.find('[required]').each(function() {
            var field = $(this);
            if (!field.val().trim()) {
                field.addClass('error');
                errors.push(field.attr('name') + ' is required');
                isValid = false;
            } else {
                field.removeClass('error');
            }
        });
        
        // Validate email fields
        form.find('input[type="email"]').each(function() {
            var field = $(this);
            var email = field.val();
            if (email && !isValidEmail(email)) {
                field.addClass('error');
                errors.push('Invalid email address');
                isValid = false;
            }
        });
        
        // Validate URL fields
        form.find('input[type="url"]').each(function() {
            var field = $(this);
            var url = field.val();
            if (url && !isValidUrl(url)) {
                field.addClass('error');
                errors.push('Invalid URL');
                isValid = false;
            }
        });
        
        if (!isValid) {
            alert('Please fix the following errors:\n' + errors.join('\n'));
        }
        
        return isValid;
    }

    /**
     * Validate email address
     */
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate URL
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Handle settings form submission
     */
    $('form[action*="options.php"]').on('submit', function() {
        return validateForm($(this));
    });

    /**
     * Handle test connection buttons
     */
    $('.bif-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var provider = button.data('provider');
        var originalText = button.text();
        
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: bifRestUrl.restUrl + 'test-connection/' + provider,
            method: 'POST',
            headers: {
                'X-WP-Nonce': bifRestUrl.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Connection successful!');
                } else {
                    alert('Connection failed: ' + response.message);
                }
            },
            error: function() {
                alert('Connection test failed.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Expose functions globally for external use
    window.BIFAdmin = window.BIFAdmin || {};
    window.BIFAdmin.handleBulkAction = handleBulkAction;
    window.BIFAdmin.validateForm = validateForm;
    window.BIFAdmin.closeModal = closeModal;

})(jQuery);
