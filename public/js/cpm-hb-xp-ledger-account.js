/**
 * XP Ledger tables: hide legacy "Remote" (remote_sync_status) column when markup still
 * outputs it (header text match). Safe no-op if the column is already removed.
 */
(function () {
	'use strict';
	function hideRemoteColumn(table) {
		var headerRow = table.querySelector('thead tr');
		if (!headerRow) {
			return;
		}
		var headers = headerRow.querySelectorAll('th');
		var col = -1;
		for (var i = 0; i < headers.length; i++) {
			var t = (headers[i].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
			if (t === 'remote') {
				col = i;
				break;
			}
		}
		if (col < 0) {
			return;
		}
		var rows = table.querySelectorAll('tr');
		for (var r = 0; r < rows.length; r++) {
			var cell = rows[r].children[col];
			if (cell) {
				cell.style.setProperty('display', 'none', 'important');
			}
		}
	}
	function collectTables() {
		var out = [];
		var seen = {};
		function add(nodeList) {
			for (var k = 0; k < nodeList.length; k++) {
				var t = nodeList[k];
				if (t && !seen[t]) {
					seen[t] = true;
					out.push(t);
				}
			}
		}
		if (document.body.classList.contains('woocommerce-account') && document.body.classList.contains('endpoint-xp-ledger')) {
			var wrap = document.querySelector('.woocommerce-MyAccount-content');
			if (wrap) {
				add(wrap.querySelectorAll('table'));
			}
		}
		add(document.querySelectorAll('.hb-xp-ledger-table'));
		return out;
	}
	var tables = collectTables();
	for (var j = 0; j < tables.length; j++) {
		hideRemoteColumn(tables[j]);
	}
})();
