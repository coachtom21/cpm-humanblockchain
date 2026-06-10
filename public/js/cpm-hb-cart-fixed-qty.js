(function ($) {
	'use strict';

	function lockCartQuantitySelectors() {
		$('.wc-block-cart-items__row, .woocommerce-cart-form__cart-item').each(function () {
			var $row = $(this);
			var $input = $row.find('.wc-block-components-quantity-selector input, input.qty[name^="cart["]').first();
			if (!$input.length) {
				return;
			}
			var min = parseInt($input.attr('min') || $input.attr('aria-valuemin') || '0', 10);
			var max = parseInt($input.attr('max') || $input.attr('aria-valuemax') || '0', 10);
			if (min !== 10 || (max !== 10 && max !== 0)) {
				return;
			}
			$input.val('10').attr('readonly', true).prop('readonly', true);
			$row.find('.wc-block-components-quantity-selector').addClass('cpm-hb-cart-qty-locked');
			$row.find('.wc-block-components-quantity-selector button').prop('disabled', true);
		});
	}

	$(function () {
		lockCartQuantitySelectors();
	});

	$(document.body).on('wc-blocks_render_blocks_frontend updated_wc_div', function () {
		lockCartQuantitySelectors();
	});

	// Block cart updates via React — re-apply after DOM changes.
	if (typeof MutationObserver !== 'undefined') {
		var observer = new MutationObserver(function () {
			lockCartQuantitySelectors();
		});
		$(function () {
			var target = document.querySelector('.wc-block-cart, .woocommerce-cart-form');
			if (target) {
				observer.observe(target, { childList: true, subtree: true });
			}
		});
	}
})(jQuery);
