(function ($) {
	'use strict';

	function fixedQtyValue($input) {
		var fixed = parseInt($input.attr('data-fixed-qty') || $input.attr('min') || '10', 10);
		return isNaN(fixed) || fixed < 1 ? 10 : fixed;
	}

	function lockInput($input) {
		if (!$input || !$input.length) {
			return;
		}
		var qty = fixedQtyValue($input);
		$input.val(String(qty)).attr('data-fixed-qty', String(qty));
	}

	function dedupeQuantityFields($form) {
		if (!$form || !$form.length || !$('body').hasClass('cpm-hb-fixed-qty-product')) {
			return;
		}
		var $quantities = $form.find('.quantity');
		if ($quantities.length <= 1) {
			return;
		}
		$quantities.slice(1).remove();
	}

	function lockAll($scope) {
		($scope || $(document)).find('form.cart input.qty.cpm-hb-fixed-qty, form.cart input.qty[readonly][name="quantity"]').each(function () {
			lockInput($(this));
		});
	}

	$(document.body).on('found_variation', 'form.variations_form', function (_event, variation) {
		var $form = $(this);
		dedupeQuantityFields($form);
		var $qty = $form.find('input.qty[name="quantity"]').first();
		if (!$qty.length) {
			return;
		}
		var fixed = fixedQtyValue($qty);
		if (variation && typeof variation.min_qty !== 'undefined' && parseInt(variation.min_qty, 10) === fixed) {
			lockInput($qty);
			$qty.closest('.quantity').show();
		}
	});

	$(document.body).on('reset_data', 'form.variations_form', function () {
		dedupeQuantityFields($(this));
	});

	$(document.body).on('input change keyup', 'form.cart input.qty.cpm-hb-fixed-qty, form.cart input.qty[readonly][name="quantity"]', function () {
		lockInput($(this));
	});

	$(function () {
		$('form.variations_form, form.cart').each(function () {
			dedupeQuantityFields($(this));
		});
		lockAll();
	});
})(jQuery);
