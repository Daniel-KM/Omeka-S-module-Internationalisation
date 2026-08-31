'use strict';

/**
 * Inline edition of the strings and the translations of a language.
 *
 * Each cell is a contenteditable region: the value is saved on blur, on Enter,
 * and restored on Escape. The pencils are a mouse affordance only: the cells
 * are reachable and editable with the keyboard by themselves.
 */
$(document).ready(function() {

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
            const newText = field.text().replace(/\s+/g, ' ').trim();
            field.removeData('original-text');

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
                string: row.data('string'),
                field: field.data('field'),
                text: newText,
            })
                .done(function(response) {
                    field.text(response.data.string !== undefined && field.data('field') === 'string'
                        ? response.data.string
                        : newText);
                    // The string is the key of the row: keep it in step.
                    row.data('string', response.data.string);
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

    // Delete a string and its translation.

    table.on('click', '.translated-delete', function(e) {
        e.preventDefault();
        const url = table.data('delete-url');
        if (!url) {
            return;
        }
        const link = $(this);
        const row = link.closest('tr');
        const string = row.data('string');

        CommonDialog.dialogConfirm({
            message: Omeka.jsTranslate('Delete the string and its translation?'),
        }).then(function(confirmed) {
            if (!confirmed) {
                return;
            }
            row.addClass('translated-saving');
            post(url, {string: string})
                .done(function() {
                    // Move the focus out of the row before removing it.
                    const next = row.next('tr').find('.translated-delete').first();
                    (next.length ? next : $('#page-actions').find('a').first()).trigger('focus');
                    row.remove();
                    announce(Omeka.jsTranslate('Translation deleted.'));
                })
                .fail(function(jqXHR) {
                    row.removeClass('translated-saving');
                    announce(failMessage(jqXHR));
                    CommonDialog.jSendFail(jqXHR);
                });
        });
    });

    // The pencil is a shortcut for the mouse: it focuses the editable cell.

    table.on('click', '.translated-edit', function(e) {
        e.preventDefault();
        $(this).closest('td').find('.translated-value[contenteditable="true"]').trigger('focus');
    });

});
