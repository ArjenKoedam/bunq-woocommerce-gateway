
/**
 * Bunq Payment Gateway - Checkout JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle payment method selection visual feedback
        $('.bunq-payment-method-radio').on('change', function() {
            // Remove selected class from all options
            $('.bunq-payment-method-option').removeClass('selected');
            
            // Add selected class to the parent label of the checked radio
            if ($(this).is(':checked')) {
                $(this).closest('.bunq-payment-method-option').addClass('selected');
            }
        });

        // Handle clicking on the label
        $('.bunq-payment-method-option').on('click', function(e) {
            // Don't double-trigger if clicking the radio itself
            if (e.target.type !== 'radio') {
                $(this).find('.bunq-payment-method-radio').prop('checked', true).trigger('change');
            }
        });

        // Initialize - mark any pre-selected option
        $('.bunq-payment-method-radio:checked').closest('.bunq-payment-method-option').addClass('selected');

        // Validate that a payment method is selected before submitting
        $('form.checkout').on('checkout_place_order_bunq', function() {
            var selectedMethod = $('.bunq-payment-method-radio:checked').val();
            
            if (!selectedMethod) {
                // Scroll to the payment methods section
                $('html, body').animate({
                    scrollTop: $('.bunq-payment-methods').offset().top - 100
                }, 500);

                // Highlight the payment methods section
                $('.bunq-payment-methods').css('border', '2px solid red');
                setTimeout(function() {
                    $('.bunq-payment-methods').css('border', '');
                }, 2000);

                return false;
            }

            return true;
        });

        // Accessibility: Allow keyboard navigation
        $('.bunq-payment-method-radio').on('keypress', function(e) {
            if (e.which === 13 || e.which === 32) { // Enter or Space
                e.preventDefault();
                $(this).prop('checked', true).trigger('change');
            }
        });
    });

})(jQuery);
