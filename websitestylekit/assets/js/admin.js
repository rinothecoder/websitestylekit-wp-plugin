(function($) {
	'use strict';

	var exportOutput = '';

	function selectedExportFormat() {
		return $('input[name="wsk_format"]:checked').val() || 'framework';
	}

	function selectedExportFilter() {
		var format = selectedExportFormat();

		if (format === 'framework' || format === 'css') {
			return 'all';
		}

		return $('input[name="wsk_filter_advanced"]:checked').val() || 'all';
	}

	function isCssExport(format) {
		return format === 'css' || format === 'framework';
	}

	function updateExportDownloadButton() {
		var format = selectedExportFormat();
		$('#wsk-download-btn').text(isCssExport(format) ? 'Download CSS' : 'Download JSON');
	}

	function updateExportScopeVisibility() {
		var format = selectedExportFormat();
		$('#wsk-export-scope').toggleClass('active', !isCssExport(format));
	}

	function updateExportUi() {
		updateExportDownloadButton();
		updateExportScopeVisibility();
	}

	function clearExportResult() {
		exportOutput = '';
		$('#wsk-export-result').hide();
		$('#wsk-copy-btn, #wsk-download-btn').hide();
		$('#wsk-export-error').hide();
	}

	// ========================
	// Tab switching
	// ========================

	$('.wsk-header__link').on('click', function(e) {
		e.preventDefault();
		var tab = $(this).data('tab');

		$('.wsk-header__link').removeClass('wsk-header__link--active');
		$(this).addClass('wsk-header__link--active');

		$('.wsk-tab-panel').removeClass('active');
		$('.wsk-tab-panel[data-tab="' + tab + '"]').addClass('active');

		// Update URL hash without page reload.
		history.replaceState(null, '', '#' + tab);
	});

	// Restore tab from URL hash on page load.
	var hash = window.location.hash.replace('#', '');
	if (hash && $('.wsk-header__link[data-tab="' + hash + '"]').length) {
		$('.wsk-header__link[data-tab="' + hash + '"]').trigger('click');
	}

	$('input[name="wsk_format"]').on('change', function() {
		clearExportResult();
		updateExportUi();
	});

	$('input[name="wsk_filter_advanced"]').on('change', function() {
		clearExportResult();
	});

	$('#wsk-advanced-export').on('toggle', function() {
		if (!this.open && !isCssExport(selectedExportFormat())) {
			$('input[name="wsk_format"][value="framework"]').prop('checked', true);
			clearExportResult();
		}

		updateExportUi();
	});

	// ========================
	// Import
	// ========================

	var previewedImportInput = '';
	var previewedImportMode = '';
	var previewedImportBuilder = '';

	function resetImportPreview() {
		previewedImportInput = '';
		previewedImportMode = '';
		previewedImportBuilder = '';
		$('#wsk-import-preview').hide().empty();
		$('#wsk-import-btn').hide();
		$('#wsk-import-success, #wsk-import-error').hide();
	}

	function getImportPayload() {
		return {
			input: $('#wsk-import-input').val().trim(),
			mode: $('input[name="wsk_import_mode"]:checked').val(),
			builder: $('input[name="wsk_import_target"]:checked').val() || 'elementor',
			remFix: $('#wsk-bricks-rem-fix').is(':checked') ? 1 : 0,
			paletteSync: $('#wsk-bricks-palette').is(':checked') ? 1 : 0,
			fontDefaults: $('#wsk-bricks-fonts').is(':checked') ? 1 : 0
		};
	}

	function validateImportInput(input) {
		if (!input) {
			return 'Please paste some data first.';
		}

		if (!input.match(/--[a-zA-Z0-9_-]+\s*:/) && !input.match(/\.[a-zA-Z_-][a-zA-Z0-9_-]*\s*[:,{]/)) {
			return 'Please paste CSS variables or classes from Website Stylekit.';
		}

		return '';
	}

	function formatImportSummary(data, mode) {
		var counts = data.counts || {};
		var names = data.names || {};
		var warnings = data.warnings || [];
		var isDone = mode === 'done';
		var rows = '';
		var totalAdded = 0;
		var totalUpdated = 0;
		var maxChips = 14;

		function escapeHtml(value) {
			return $('<div>').text(value).html();
		}

		var checkIcon = '<svg class="wsk-summary__check" viewBox="0 0 20 20" aria-hidden="true">' +
			'<circle cx="10" cy="10" r="9"></circle>' +
			'<path d="M6 10.4l2.6 2.6L14 7.6"></path>' +
			'</svg>';

		function addRow(typeKey, label) {
			var typeCounts = counts[typeKey] || {};
			var typeNames = names[typeKey] || {};
			var added = typeCounts.imported || 0;
			var updated = typeCounts.updated || 0;

			if (!added && !updated) {
				return;
			}

			totalAdded += added;
			totalUpdated += updated;

			var badges = '';
			if (added) {
				badges += '<span class="wsk-badge wsk-badge--added">' + added + ' new</span>';
			}
			if (updated) {
				badges += '<span class="wsk-badge wsk-badge--updated">' + updated + ' updated</span>';
			}

			var allNames = (typeNames.imported || []).concat(typeNames.updated || []);
			var chips = '';

			allNames.slice(0, maxChips).forEach(function(name) {
				chips += '<span class="wsk-chip">' + escapeHtml(name) + '</span>';
			});

			if (allNames.length > maxChips) {
				chips += '<span class="wsk-chip wsk-chip--more">+' + (allNames.length - maxChips) + ' more</span>';
			}

			rows += '<div class="wsk-summary__row">' + checkIcon +
				'<div class="wsk-summary__body">' +
				'<div class="wsk-summary__line"><span class="wsk-summary__label">' + label + '</span>' + badges + '</div>' +
				(chips ? '<div class="wsk-summary__names">' + chips + '</div>' : '') +
				'</div></div>';
		}

		addRow('colors', 'Colors');
		addRow('fonts', 'Font families');
		addRow('sizes', 'Size variables');
		addRow('classes', 'Classes');

		if (!rows) {
			rows = '<div class="wsk-summary__row wsk-summary__row--empty">No changes needed.</div>';
		}

		var html = '<div class="wsk-summary' + (isDone ? ' wsk-summary--done' : '') + '">';

		html += '<div class="wsk-summary__header"><strong>' + (isDone ? 'Import complete' : 'Import preview') + '</strong>';

		var totalParts = [];
		if (totalAdded) {
			totalParts.push(totalAdded + ' new');
		}
		if (totalUpdated) {
			totalParts.push(totalUpdated + ' updated');
		}
		if (totalParts.length) {
			html += '<span class="wsk-summary__total">' + totalParts.join(' &middot; ') + '</span>';
		}

		html += '</div>';
		html += '<div class="wsk-summary__rows">' + rows + '</div>';

		if (warnings.length) {
			html += '<div class="wsk-import-warnings"><strong>Warnings</strong><ul>';
			warnings.forEach(function(warning) {
				html += '<li>' + escapeHtml(warning) + '</li>';
			});
			html += '</ul></div>';
		}

		if (isDone && data.changes && data.changes.length) {
			html += '<div class="wsk-import-changes"><strong>What changed</strong><ul>';
			data.changes.forEach(function(change) {
				html += '<li>' + escapeHtml(change) + '</li>';
			});
			html += '</ul></div>';
		}

		if (isDone && data.next_steps && data.next_steps.length) {
			html += '<div class="wsk-import-notes"><strong>Next step</strong><ul>';
			data.next_steps.forEach(function(note) {
				html += '<li>' + escapeHtml(note) + '</li>';
			});
			html += '</ul></div>';
		}

		if (isDone) {
			var builderLabel = data.builder === 'bricks' ? 'Bricks builder' : 'Elementor editor';
			html += '<div class="wsk-summary__footer">Refresh the ' + builderLabel + ' to see the changes.</div>';
		}

		html += '</div>';

		return html;
	}

	function requestImport(dryRun, button) {
		var payload = getImportPayload();
		var error = validateImportInput(payload.input);

		if (error) {
			$('#wsk-import-error').text(error).show();
			return;
		}

		button.prop('disabled', true).text(dryRun ? 'Previewing...' : 'Importing...');
		$('#wsk-import-error, #wsk-import-success').hide();

		$.post(wskData.ajaxUrl, {
			action: 'wsk_import_variables',
			nonce: wskData.nonce,
			json_input: payload.input,
			mode: payload.mode,
			builder: payload.builder,
			rem_fix: payload.builder === 'bricks' ? payload.remFix : 0,
			palette_sync: payload.builder === 'bricks' ? payload.paletteSync : 0,
			font_defaults: payload.builder === 'bricks' ? payload.fontDefaults : 0,
			dry_run: dryRun ? 1 : 0,
		}, function(response) {
			button.prop('disabled', false).text(dryRun ? 'Preview Import' : 'Apply Import');

			if (response.success) {
				if (dryRun) {
					previewedImportInput = payload.input;
					previewedImportMode = payload.mode;
					previewedImportBuilder = payload.builder + ':' + payload.remFix + ':' + payload.paletteSync + ':' + payload.fontDefaults;
					$('#wsk-import-preview').html(formatImportSummary(response.data, 'preview')).show();
					$('#wsk-import-btn').show();
				} else {
					$('#wsk-import-preview').hide();
					$('#wsk-import-btn').hide();
					$('#wsk-import-success').html(formatImportSummary(response.data, 'done')).show();
				}
			} else {
				$('#wsk-import-error').text(response.data || 'Something went wrong.').show();
			}
		}).fail(function() {
			button.prop('disabled', false).text(dryRun ? 'Preview Import' : 'Apply Import');
			$('#wsk-import-error').text('Request failed. Check your connection.').show();
		});
	}

	$('#wsk-import-input').on('input', resetImportPreview);
	$('input[name="wsk_import_mode"]').on('change', resetImportPreview);

	function updateReplaceHint() {
		var isBricks = $('input[name="wsk_import_target"]:checked').val() === 'bricks';
		var hint = isBricks
			? 'Clears everything previously imported by Website Stylekit first, then adds the new CSS. Styles you made in Bricks yourself are kept.'
			: 'Clears the imported style types first, then adds the new CSS.';
		$('input[name="wsk_import_mode"][value="replace"]').closest('label').find('.wsk-hint').text(hint);
	}

	$('#wsk-bricks-rem-fix, #wsk-bricks-palette, #wsk-bricks-fonts').on('change', resetImportPreview);

	$('input[name="wsk_import_target"]').on('change', function() {
		resetImportPreview();
		$('#wsk-bricks-options').toggle($(this).val() === 'bricks');
		updateReplaceHint();
	});

	updateReplaceHint();

	$('#wsk-preview-import-btn').on('click', function() {
		requestImport(true, $(this));
	});

	$('#wsk-import-btn').on('click', function() {
		var btn = $(this);
		var payload = getImportPayload();

		if (!previewedImportInput || payload.input !== previewedImportInput || payload.mode !== previewedImportMode || (payload.builder + ':' + payload.remFix + ':' + payload.paletteSync + ':' + payload.fontDefaults) !== previewedImportBuilder) {
			$('#wsk-import-error').text('Please preview the latest CSS before applying it.').show();
			return;
		}

		requestImport(false, btn);
	});

	// ========================
	// Backups
	// ========================

	function reloadBackupsTab() {
		window.location.hash = 'backups';
		window.location.reload();
	}

	function backupRequest(action, backupId, button, loadingText, successText, extraData) {
		var originalText = button.text();

		button.prop('disabled', true).text(loadingText);
		$('#wsk-backup-error, #wsk-backup-success').hide();

		$.post(wskData.ajaxUrl, $.extend({
			action: action,
			nonce: wskData.nonce,
			backup_id: backupId || ''
		}, extraData || {}), function(response) {
			button.prop('disabled', false).text(originalText);

			if (response.success) {
				$('#wsk-backup-success').text(successText).show();
				setTimeout(reloadBackupsTab, 700);
			} else {
				$('#wsk-backup-error').text(response.data || 'Something went wrong.').show();
			}
		}).fail(function() {
			button.prop('disabled', false).text(originalText);
			$('#wsk-backup-error').text('Request failed. Check your connection.').show();
		});
	}

	function downloadTextFile(filename, content, type) {
		var blob = new Blob([content], { type: type });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');

		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	$('.wsk-create-backup-btn').on('click', function() {
		backupRequest('wsk_create_backup', '', $(this), 'Creating...', 'Backup created.', { target: $(this).data('target') || 'elementor' });
	});

	$('.wsk-restore-backup-btn').on('click', function() {
		var isBricks = String($(this).data('backup-id')).indexOf('wskb-') === 0;
		var builderName = isBricks ? 'Bricks' : 'Elementor';

		if (!window.confirm('Restore this backup? Current ' + builderName + ' variables and classes will be replaced.')) {
			return;
		}

		backupRequest('wsk_restore_backup', $(this).data('backup-id'), $(this), 'Restoring...', 'Backup restored. Refresh the ' + builderName + ' editor to see the changes.');
	});

	$('.wsk-delete-backup-btn').on('click', function() {
		if (!window.confirm('Delete this backup?')) {
			return;
		}

		backupRequest('wsk_delete_backup', $(this).data('backup-id'), $(this), 'Deleting...', 'Backup deleted.');
	});

	$('#wsk-upload-backup-input').on('change', function() {
		var input = this;
		var file = input.files && input.files[0];

		if (!file) return;

		var reader = new FileReader();

		$('#wsk-backup-error, #wsk-backup-success').hide();

		reader.onload = function(event) {
			$.post(wskData.ajaxUrl, {
				action: 'wsk_upload_backup',
				nonce: wskData.nonce,
				backup_json: event.target.result || ''
			}, function(response) {
				input.value = '';

				if (response.success) {
					$('#wsk-backup-success').text('Backup uploaded.').show();
					setTimeout(reloadBackupsTab, 700);
				} else {
					$('#wsk-backup-error').text(response.data || 'Something went wrong.').show();
				}
			}).fail(function() {
				input.value = '';
				$('#wsk-backup-error').text('Upload failed. Check your connection.').show();
			});
		};

		reader.onerror = function() {
			input.value = '';
			$('#wsk-backup-error').text('Could not read that backup file.').show();
		};

		reader.readAsText(file);
	});

	$('.wsk-view-backup-btn').on('click', function() {
		var btn = $(this);
		var backupId = btn.data('backup-id');
		var item = btn.closest('.wsk-backup-item');
		var viewer = item.find('.wsk-backup-viewer');
		var output = item.find('.wsk-backup-viewer-output');

		if (viewer.is(':visible')) {
			viewer.hide();
			btn.text('View');
			return;
		}

		btn.prop('disabled', true).text('Loading...');
		$('#wsk-backup-error, #wsk-backup-success').hide();

		$.post(wskData.ajaxUrl, {
			action: 'wsk_view_backup',
			nonce: wskData.nonce,
			backup_id: backupId
		}, function(response) {
			btn.prop('disabled', false);

			if (!response.success) {
				btn.text('View');
				$('#wsk-backup-error').text(response.data || 'Something went wrong.').show();
				return;
			}

			var data = response.data || {};

			output.val(data.css || 'This backup does not contain previewable CSS.');
			viewer.show();
			btn.text('Close view');
		}).fail(function() {
			btn.prop('disabled', false).text('View');
			$('#wsk-backup-error').text('Request failed. Check your connection.').show();
		});
	});

	$('.wsk-download-backup-btn').on('click', function() {
		var btn = $(this);
		var originalText = btn.text();
		var backupId = btn.data('backup-id');

		btn.prop('disabled', true).text('Preparing...');
		$('#wsk-backup-error, #wsk-backup-success').hide();

		$.post(wskData.ajaxUrl, {
			action: 'wsk_download_backup',
			nonce: wskData.nonce,
			backup_id: backupId
		}, function(response) {
			btn.prop('disabled', false).text(originalText);

			if (!response.success) {
				$('#wsk-backup-error').text(response.data || 'Something went wrong.').show();
				return;
			}

			downloadTextFile('website-stylekit-backup-' + backupId + '.json', JSON.stringify(response.data, null, 2), 'application/json');
		}).fail(function() {
			btn.prop('disabled', false).text(originalText);
			$('#wsk-backup-error').text('Request failed. Check your connection.').show();
		});
	});

	$('.wsk-download-backup-css-btn').on('click', function() {
		var btn = $(this);
		var originalText = btn.text();
		var backupId = btn.data('backup-id');

		btn.prop('disabled', true).text('Preparing...');
		$('#wsk-backup-error, #wsk-backup-success').hide();

		$.post(wskData.ajaxUrl, {
			action: 'wsk_view_backup',
			nonce: wskData.nonce,
			backup_id: backupId
		}, function(response) {
			btn.prop('disabled', false).text(originalText);

			if (!response.success) {
				$('#wsk-backup-error').text(response.data || 'Something went wrong.').show();
				return;
			}

			var css = (response.data && response.data.css) ? response.data.css : '';

			if (!css) {
				$('#wsk-backup-error').text('This backup does not contain previewable CSS.').show();
				return;
			}

			downloadTextFile('website-stylekit-backup-' + backupId + '.css', css, 'text/css');
		}).fail(function() {
			btn.prop('disabled', false).text(originalText);
			$('#wsk-backup-error').text('Request failed. Check your connection.').show();
		});
	});

	// ========================
	// Reorder
	// ========================

	$('#wsk-reorder-btn').on('click', function() {
		var btn = $(this);

		btn.text('Reordering...').prop('disabled', true);
		$('#wsk-reorder-error, #wsk-reorder-success').hide();

		$.post(wskData.ajaxUrl, {
			action: 'wsk_reorder_variables',
			nonce: wskData.nonce,
		}, function(response) {
			btn.text('Reorder Variables').prop('disabled', false);

			if (response.success) {
				var total = response.data.total || 0;
				$('#wsk-reorder-success').text('Done! Reordered ' + total + ' variables. Refresh the Elementor editor to see the changes.').show();
			} else {
				$('#wsk-reorder-error').text(response.data || 'Something went wrong.').show();
			}
		}).fail(function() {
			btn.text('Reorder Variables').prop('disabled', false);
			$('#wsk-reorder-error').text('Request failed. Check your connection.').show();
		});
	});

	// ========================
	// Export
	// ========================

	$('#wsk-export-btn').on('click', function() {
		var btn = $(this);
		var format = selectedExportFormat();
		var filter = selectedExportFilter();

		btn.text('Exporting...').prop('disabled', true);
		$('#wsk-export-error').hide();
		$('#wsk-export-result').hide();
		$('#wsk-copy-btn, #wsk-download-btn').hide();

		$.post(wskData.ajaxUrl, {
			action: 'wsk_export_variables',
			nonce: wskData.nonce,
			format: format,
			filter: filter,
		}, function(response) {
			btn.text('Export').prop('disabled', false);

			if (response.success) {
				exportOutput = isCssExport(format) ? response.data : JSON.stringify(response.data, null, 2);
				$('#wsk-export-output').val(exportOutput);
				$('#wsk-export-result').show();
				$('#wsk-copy-btn, #wsk-download-btn').show();
				updateExportUi();
			} else {
				$('#wsk-export-error').text(response.data || 'Something went wrong.').show();
			}
		}).fail(function() {
			btn.text('Export').prop('disabled', false);
			$('#wsk-export-error').text('Request failed. Check your connection.').show();
		});
	});

	// Copy to clipboard.
	$('#wsk-copy-btn').on('click', function() {
		var btn = $(this);

		if (navigator.clipboard && exportOutput) {
			navigator.clipboard.writeText(exportOutput).then(function() {
				btn.text('Copied!');
				setTimeout(function() {
					btn.text('Copy to Clipboard');
				}, 2000);
			});
		} else {
			$('#wsk-export-output').select();
			document.execCommand('copy');
			btn.text('Copied!');
			setTimeout(function() {
				btn.text('Copy to Clipboard');
			}, 2000);
		}
	});

	// Download as CSS or JSON file.
	$('#wsk-download-btn').on('click', function() {
		if (!exportOutput) return;

		var format = selectedExportFormat();
		var filterName = selectedExportFilter();
		var extension = isCssExport(format) ? 'css' : 'json';
		var suffix = format === 'framework' ? 'framework' : 'variables';
		downloadTextFile('elementor-' + filterName + '-' + suffix + '.' + extension, exportOutput, isCssExport(format) ? 'text/css' : 'application/json');
	});

})(jQuery);
