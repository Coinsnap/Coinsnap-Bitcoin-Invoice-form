/**
 * Bitcoin Invoice Form - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initInvoiceForms();
    });

    /**
     * Initialize invoice forms
     */
    function initInvoiceForms() {
        // Handle form submissions
        $('.bif-form').on('submit', handleFormSubmit);

        // Setup live discount calculation if applicable
        $('.bif-form').each(function() {
            setupLiveDiscount($(this));
        });
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();

        var form = $(this);
        var formId = form.data('form-id');
        var submitButton = form.find('.bif-button');
        var messagesContainer = form.find('.bif-form-messages');

        // Validate form
        if (!validateForm(form)) {
            return;
        }

        // Disable submit button and show loading state
        submitButton.prop('disabled', true).text('Processing...');
        messagesContainer.hide();

        // Prepare form data
        var formData = new FormData(this);

        // Submit form
        $.ajax({
            url: BIF.restUrl + 'payment/create',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-WP-Nonce': BIF.nonce
            },
            success: function(response) {
                if (response.success) {
                    showPaymentModal(formId, response.data);
                } else {
                    showFormError(form, response.message || 'An error occurred. Please try again.');
                }
            },
            error: function(xhr) {
                var message = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showFormError(form, message);
            },
            complete: function() {
                // Re-enable submit button
                submitButton.prop('disabled', false).text('Pay with Bitcoin');
            }
        });
    }

    /**
     * Validate form
     */
    function validateForm(form) {
        var isValid = true;
        var requiredFields = form.find('[required]');

        requiredFields.each(function() {
            var field = $(this);
            var value = field.val().trim();

            if (!value) {
                field.addClass('error');
                isValid = false;
            } else {
                field.removeClass('error');
            }
        });

        // Validate email field
        var emailField = form.find('input[type="email"]');
        if (emailField.length && emailField.val()) {
            var email = emailField.val();
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailField.addClass('error');
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * Show form error
     */
    function showFormError(form, message) {
        var messagesContainer = form.find('.bif-form-messages');
        var errorMessage = messagesContainer.find('.bif-message-error');

        errorMessage.text(message).show();
        messagesContainer.show();

        // Scroll to error message
        $('html, body').animate({
            scrollTop: messagesContainer.offset().top - 100
        }, 500);
    }

    /**
     * Create modal (matching wp-bitcoin-newsletter implementation)
     */
    function createModal() {
        var backdrop = document.createElement('div');
        backdrop.className = 'bif-modal-backdrop';
        var modal = document.createElement('div');
        modal.className = 'bif-modal';
        var iframe = document.createElement('iframe');
        iframe.className = 'bif-payment-iframe';
        iframe.style.cssText = 'width:100%;height:100%;border:none;background:#fff;';
        modal.appendChild(iframe);
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) backdrop.style.display = 'none';
        });
        return { backdrop: backdrop, frame: iframe };
    }

    /**
     * Show payment modal
     */
    function showPaymentModal(formId, paymentData) {
        console.log('Invoice created:', paymentData.invoice_id);
        console.log('Payment URL:', paymentData.payment_url);

        // Try inline modal for Coinsnap-compatible checkout link; otherwise redirect
        try {
            var modal = createModal();
            modal.frame.src = paymentData.payment_url;

            // Set redirect data on the backdrop
            if (paymentData.success_page) {
                modal.backdrop.dataset.successPage = paymentData.success_page;
            }
            if (paymentData.thank_you_message) {
                modal.backdrop.dataset.thankYouMessage = paymentData.thank_you_message;
            }

            modal.backdrop.style.display = 'flex';
            console.log('Modal created, starting status polling...');
            startPaymentPolling(paymentData.invoice_id, modal);
        } catch (e) {
            console.error('Modal creation failed, redirecting:', e);
            window.location.href = paymentData.payment_url;
        }
    }

    /**
     * Start payment status polling (matching wp-bitcoin-newsletter implementation)
     */
    function startPaymentPolling(invoiceId, modal) {
        var tries = 0;
        var maxTries = 60; // ~60s
        var verifyTries = 0;
        var maxVerifyTries = 3; // Try manual verification 3 times

        function step() {
            // Check if modal is still open and polling should continue
            if (!modal.backdrop.pollingActive) {
                console.log('Polling stopped - modal closed');
                return;
            }

            tries++;
            var statusUrl = BIF.restUrl + 'status/' + encodeURIComponent(invoiceId);

            console.log('Polling status for invoice:', invoiceId, 'attempt:', tries);

            $.ajax({
                url: statusUrl,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': BIF.nonce
                }
            })
            .done(function(response) {
                console.log('Status response:', response);

                if (response && response.success && response.data && response.data.paid) {
                    console.log('Payment confirmed');
                    showPaymentSuccess(modal);
                    return;
                }

                // If we've been polling for a while and still not paid, try manual verification
                if (tries > 30 && verifyTries < maxVerifyTries) {
                    verifyTries++;
                    console.log('Trying manual payment verification, attempt:', verifyTries);
                    return verifyPayment();
                }

                if (tries < maxTries) {
                    console.log('Payment not yet confirmed, retrying in 1s...');
                    setTimeout(step, 1000);
                } else {
                    console.log('Max polling attempts reached, payment may still be processing');
                    // Don't show error - just stop polling silently
                }
            })
            .fail(function(xhr) {
                console.error('Status check failed:', xhr);
                if (tries < maxTries) {
                    console.log('Retrying status check in 1.5s...');
                    setTimeout(step, 1500);
                } else {
                    console.error('Max polling attempts reached with errors');
                    // Don't show error - just stop polling silently
                }
            });
        }

        function verifyPayment() {
            // Check if modal is still open and polling should continue
            if (!modal.backdrop.pollingActive) {
                console.log('Verification stopped - modal closed');
                return;
            }

            var verifyUrl = BIF.restUrl + 'verify-payment/' + encodeURIComponent(invoiceId);

            console.log('Attempting manual payment verification...');

            $.ajax({
                url: verifyUrl,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': BIF.nonce
                }
            })
            .done(function(response) {
                console.log('Verification response:', response);

                if (response && response.success && response.data && response.data.paid) {
                    console.log('Payment verified manually');
                    showPaymentSuccess(modal);
                } else {
                    // Continue polling after verification attempt
                    if (tries < maxTries && modal.backdrop.pollingActive) {
                        setTimeout(step, 1000);
                    }
                }
            })
            .fail(function(xhr) {
                console.error('Manual verification failed:', xhr);
                // Continue polling after verification attempt
                if (tries < maxTries && modal.backdrop.pollingActive) {
                    setTimeout(step, 1000);
                }
            });
        }

        // Store polling state for cleanup and start polling
        modal.backdrop.pollingActive = true;
        step();
    }

    /**
     * Show payment success
     */
    function showPaymentSuccess(modal) {
        console.log('Payment successful, closing modal');
        modal.backdrop.style.display = 'none';

        // Redirect after 2 seconds
        setTimeout(function() {
            var successPage = modal.backdrop.dataset.successPage;
            if (successPage) {
                window.location.href = successPage;
            } else {
                var thankYouMessage = modal.backdrop.dataset.thankYouMessage || 'Thank you! Your payment has been processed successfully.';
                alert(thankYouMessage);
            }
        }, 2000);
    }


    /**
     * Close modal
     */
    function closeModal() {
        // Close any existing modals
        var existingModals = document.querySelectorAll('.bif-modal-backdrop');
        existingModals.forEach(function(backdrop) {
            // Stop polling by setting flag
            backdrop.pollingActive = false;
            backdrop.style.display = 'none';
        });
    }

    /**
     * Utility function to format currency
     */
    function formatCurrency(amount, currency) {
        var formatter = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        });
        return formatter.format(amount);
    }

    /**
     * Utility function to show loading state
     */
    function showLoading(element) {
        element.addClass('bif-loading');
    }

    /**
     * Utility function to hide loading state
     */
    function hideLoading(element) {
        element.removeClass('bif-loading');
    }

    /**
     * Setup live discount calculation
     */
    function setupLiveDiscount(form) {
        var amountInput = form.find('#bif_amount');
        if (!amountInput.length) return;
        var hasDiscount = amountInput.data('discount-enabled') === 1 || amountInput.data('discount-enabled') === '1';
        if (!hasDiscount) return;

        var finalField = form.find('.bif-amount-final');
        var currencySelect = form.find('#bif_currency');
        var formCurrency = form.data('currency') || 'USD';
        var discType = amountInput.data('discount-type') || 'fixed';
        var discValue = parseFloat(amountInput.data('discount-value')) || 0;

        function parseLocaleAmount(val) {
            if (val == null) return 0;
            var s = String(val).trim();
            if (s === '') return 0;
            var hasComma = s.indexOf(',') !== -1;
            var hasDot = s.indexOf('.') !== -1;
            if (hasComma && hasDot) {
                var lastComma = s.lastIndexOf(',');
                var lastDot = s.lastIndexOf('.');
                if (lastComma > lastDot) {
                    s = s.replace(/\./g, '');
                    s = s.replace(',', '.');
                } else {
                    s = s.replace(/,/g, '');
                }
            } else if (hasComma && !hasDot) {
                s = s.replace(/,/g, '.');
            } else {
                s = s.replace(/,/g, '');
            }
            var n = parseFloat(s);
            return isNaN(n) ? 0 : n;
        }

        function computeFinal(base, type, value) {
            var amt = base;
            if (value > 0) {
                if (type === 'percent') {
                    amt = amt - (amt * (value / 100));
                } else {
                    amt = amt - value;
                }
            }
            if (amt < 0) amt = 0;
            return amt;
        }

        function decimalsFor(currency) {
            return currency === 'SATS' ? 0 : 2;
        }

        function updateFinal() {
            if (!finalField.length) return;
            var currency = currencySelect.length ? currencySelect.val() : formCurrency;
            var base = parseLocaleAmount(amountInput.val());
            var finalAmt = computeFinal(base, discType, discValue);
            var dec = decimalsFor(currency);
            // Format value with fixed decimals (no currency symbol to keep it simple)
            finalField.val(finalAmt.toFixed(dec));
        }

        amountInput.on('input change', updateFinal);
        currencySelect.on('change', updateFinal);
        // Initial calc
        updateFinal();
    }

    // Expose functions globally for external use
    window.BIF = window.BIF || {};
    window.BIF.showPaymentModal = showPaymentModal;
    window.BIF.closeModal = closeModal;
    window.BIF.formatCurrency = formatCurrency;

})(jQuery);
