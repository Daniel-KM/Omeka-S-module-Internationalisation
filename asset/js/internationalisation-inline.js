'use strict';

/**
 * Inline edition of the strings and the translations of a language.
 *
 * Each cell is a contenteditable region: the value is saved on blur, on Enter,
 * and restored on Escape. The pencils are a mouse affordance only: the cells
 * are reachable and editable with the keyboard by themselves.
 */
$(document).ready(function() {

    // The batch edition is a textarea toggled with the table. The rows may have
    // been edited inline since the page was loaded, so the text is rebuilt
    // from them each time it is opened.

    const batch = $('#translated-batch');
    const batchToggle = $('.translated-batch-toggle');
    if (batch.length && batchToggle.length) {
        const batchForm = batch.find('form');
        const textarea = batch.find('textarea');
        let openedText = textarea.val();

        const setBatch = function(open) {
            batchToggle.attr('aria-pressed', open ? 'true' : 'false');
            batch.prop('hidden', !open);
            $('#item-metadata').prop('hidden', open);
            $('.translated-add').prop('hidden', open);
        };

        batchToggle.on('click', function() {
            if (batchToggle.attr('aria-pressed') === 'true') {
                textarea.val(openedText);
                setBatch(false);
                return;
            }
            const lines = [];
            $('.translated-list tbody tr[data-string]').not('.translated-new').each(function() {
                const row = $(this);
                lines.push(row.attr('data-string') + ' = ' + row.find('.translated-translation').text());
            });
            openedText = lines.length ? lines.join('\n') + '\n' : '';
            textarea.val(openedText);
            // The core warns of unsaved changes from the data of the form on
            // load, so the rebuilt text is the new reference.
            batchForm.data('omekaFormOriginalData', batchForm.serialize());
            setBatch(true);
            textarea.trigger('focus');
        });

        batch.find('.translated-batch-cancel').on('click', function() {
            textarea.val(openedText);
            setBatch(false);
            batchToggle.trigger('focus');
        });

        // Opened by the server (old url or invalid text): keep the posted text.
        if (!batch.prop('hidden')) {
            $('.translated-add').prop('hidden', true);
        }
    }

    const table = $('.translated-inline');
    if (!table.length) {
        return;
    }

    const status = $('#translated-inline-status');
    const token = $('input[name="translate_string_csrf"]').val();

    /**
     * Announce a result to the screen readers without moving the focus.
     */
    const announce = function(message) {
        // Reset the region so an identical message is announced again.
        status.text('');
        window.setTimeout(function() { status.text(message); }, 50);
    };

    const post = function(url, data) {
        return $.post({url: url, data: Object.assign({translate_string_csrf: token}, data)});
    };

    const failMessage = function(jqXHR) {
        const response = jqXHR.responseJSON;
        return (response && response.message)
            ? response.message
            : Omeka.jsTranslate('An error occurred.');
    };

    const cleanText = function(field) {
        return field.text().replace(/\s+/g, ' ').trim();
    };

    /**
     * Name the button to delete a row with its string, that may change.
     */
    const refreshDeleteLabel = function(row) {
        const link = row.find('.translated-delete');
        if (link.length && link.data('label')) {
            link.attr('aria-label', link.data('label').replace('%s', row.attr('data-string')));
        }
    };

    /**
     * Save a new row once the string and the translation are filled.
     */
    const saveNewRow = function(row) {
        const string = cleanText(row.find('.translated-string'));
        const translation = cleanText(row.find('.translated-translation'));
        if (!string && !translation) {
            // An empty row is removed once the focus left it.
            window.setTimeout(function() {
                if (!row[0].contains(document.activeElement)) {
                    row.remove();
                }
            }, 0);
            return;
        }
        const url = table.data('add-url');
        if (!string || !translation || !url || row.data('saving')) {
            return;
        }
        row.data('saving', true).addClass('translated-saving');
        post(url, {string: string, translation: translation})
            .done(function(response) {
                row.removeClass('translated-new')
                    .attr('data-string', response.data.string);
                row.find('.translated-string').text(response.data.string);
                refreshDeleteLabel(row);
                // Without the right to update, the saved row becomes static.
                if (!table.data('update-url')) {
                    row.find('.translated-value')
                        .removeAttr('contenteditable role aria-multiline aria-label aria-describedby');
                    row.find('.translated-edit').closest('li').remove();
                }
                announce(Omeka.jsTranslate('Translation added.'));
            })
            .fail(function(jqXHR) {
                announce(failMessage(jqXHR));
                CommonDialog.jSendFail(jqXHR);
            })
            .always(function() {
                row.removeData('saving').removeClass('translated-saving');
            });
    };

    // Add a row, filled in place, saved when the two cells are filled.

    $('.translated-add').on('click', function() {
        const template = document.getElementById('translated-new-row');
        if (!template) {
            return;
        }
        const row = $(template.content.firstElementChild.cloneNode(true));
        table.find('tbody').prepend(row);
        row.find('.translated-string').trigger('focus');
    });

    // Save a string or a translation.

    table
        .on('focus', '.translated-value[contenteditable="true"]', function() {
            const field = $(this);
            field.data('original-text', field.text());
        })
        .on('keydown', '.translated-value[contenteditable="true"]', function(e) {
            // A single line: the new line validates instead of splitting.
            if (e.key === 'Enter') {
                e.preventDefault();
                const field = $(this);
                const row = field.closest('tr');
                if (row.hasClass('translated-new') && field.data('field') === 'string') {
                    row.find('.translated-translation').trigger('focus');
                    return;
                }
                this.blur();
            } else if (e.key === 'Escape') {
                const field = $(this);
                field.text(field.data('original-text'));
                this.blur();
            }
        })
        .on('blur', '.translated-value[contenteditable="true"]', function() {
            const field = $(this);
            const row = field.closest('tr');
            const oldText = field.data('original-text');
            const newText = cleanText(field);
            field.removeData('original-text');

            if (row.hasClass('translated-new')) {
                field.text(newText);
                saveNewRow(row);
                return;
            }

            if (newText === '' || newText === oldText) {
                field.text(oldText);
                return;
            }

            const url = table.data('update-url');
            if (!url) {
                field.text(oldText);
                return;
            }

            field.addClass('translated-saving');
            post(url, {
                string: row.attr('data-string'),
                field: field.data('field'),
                text: newText,
            })
                .done(function(response) {
                    field.text(response.data.string !== undefined && field.data('field') === 'string'
                        ? response.data.string
                        : newText);
                    // The string is the key of the row: keep it in step.
                    row.attr('data-string', response.data.string);
                    refreshDeleteLabel(row);
                    announce(Omeka.jsTranslate('Translation saved.'));
                })
                .fail(function(jqXHR) {
                    field.text(oldText);
                    announce(failMessage(jqXHR));
                    CommonDialog.jSendFail(jqXHR);
                })
                .always(function() {
                    field.removeClass('translated-saving');
                });
        });

    // Delete a string and its translation with the standard dialog of Common:
    // the trigger holds the message, the url and the spinner, and the failures
    // are displayed by Common.

    table.on('click', '.translated-delete', function(e) {
        e.preventDefault();
        const link = this;
        const row = $(link).closest('tr');
        // A row not saved yet is only removed from the page.
        if (row.hasClass('translated-new')) {
            row.remove();
            $('.translated-add').trigger('focus');
            return;
        }

        CommonDialog.dialogConfirm({trigger: link}).then(function(confirmed) {
            if (!confirmed) {
                return;
            }
            // The string may have been renamed since the page was loaded.
            link.dataset.payload = JSON.stringify({
                string: row.attr('data-string'),
                translate_string_csrf: token,
            });
            CommonDialog.jSend({target: link, preventDefault: function() {}})
                .then(function(data) {
                    if (!data || data.status !== 'success') {
                        return;
                    }
                    // Move the focus out of the row before removing it.
                    const next = row.next('tr').find('.translated-delete').first();
                    (next.length ? next : $('#page-actions').find('a, button').first()).trigger('focus');
                    row.remove();
                    announce(Omeka.jsTranslate('Translation deleted.'));
                });
        });
    });

    // The pencil is a shortcut for the mouse: it focuses the editable cell.

    table.on('click', '.translated-edit', function(e) {
        e.preventDefault();
        $(this).closest('td').find('.translated-value[contenteditable="true"]').trigger('focus');
    });

});
