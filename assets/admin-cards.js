(function ($) {
    'use strict';

    function showToast(message, type) {
        var $toast = $('<div class="clsc-toast"></div>');
        $toast.addClass(type === 'error' ? 'is-error' : 'is-success');
        $toast.text(message);
        $('body').append($toast);

        window.setTimeout(function () {
            $toast.addClass('is-visible');
        }, 10);

        window.setTimeout(function () {
            $toast.removeClass('is-visible');
            window.setTimeout(function () {
                $toast.remove();
            }, 250);
        }, 2200);
    }

    function escapeHtml(value) {
        return $('<div/>').text(value == null ? '' : String(value)).html();
    }

    function openMediaFrame($inputField, $previewDiv) {
        if (!window.wp || !wp.media) {
            window.alert('WordPress Media niet beschikbaar');
            return;
        }

        var frame = wp.media({
            title: 'Selecteer afbeelding',
            button: { text: 'Gebruik deze afbeelding' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            if (!attachment || !attachment.id) {
                return;
            }

            $inputField.val(attachment.id);
            if (attachment.url) {
                $previewDiv.html('<img src="' + escapeHtml(attachment.url) + '" alt="" class="clsc-preview-img" />');
            }
        });

        frame.open();
    }

    function initEditCardButtons() {
        $(document).on('click', '.clsc-edit-card-btn', function (event) {
            event.preventDefault();

            var $button = $(this);
            var $row = $button.closest('tr');
            var cardId = parseInt($button.attr('data-id'), 10);

            if ($row.next().hasClass('clsc-fallback-row')) {
                $row.next().remove();
                return;
            }

            $('.clsc-fallback-row').remove();

            var imageId = $button.attr('data-image-id') || '';
            var title = $button.attr('data-title') || '';
            var desc = $button.attr('data-desc') || '';
            var order = $button.attr('data-order') || '';
            var visible = $button.attr('data-visible') !== '0';

            var formHtml = ''
                + '<tr class="clsc-fallback-row">'
                + '<td colspan="7">'
                + '<div class="clsc-edit-form">'
                + '<div class="clsc-edit-form-image">'
                + '<div class="clsc-fb-preview">'
                + (imageId ? '<img src="" alt="" class="clsc-preview-img" />' : '<span class="clsc-empty">Geen afb.</span>')
                + '</div>'
                + '<p><button type="button" class="button button-secondary clsc-fb-image">Kies afbeelding</button></p>'
                + '<input type="hidden" class="clsc-fb-image-id" value="' + escapeHtml(imageId) + '" />'
                + '</div>'
                + '<div class="clsc-edit-form-content">'
                + '<p><label><strong>Titel</strong><br />'
                + '<input type="text" class="clsc-fb-title" value="' + escapeHtml(title) + '" /></label></p>'
                + '<p><label><strong>Beschrijving</strong><br />'
                + '<textarea class="clsc-fb-desc">' + escapeHtml(desc) + '</textarea></label></p>'
                + '<div class="clsc-edit-form-options">'
                + '<p><label><strong>Volgorde</strong><br />'
                + '<input type="number" min="0" step="1" class="clsc-fb-order" value="' + escapeHtml(order) + '" /></label></p>'
                + '<p><label class="clsc-fb-visible-label"><input type="checkbox" class="clsc-fb-visible" value="1"' + (visible ? ' checked' : '') + ' /> <strong>Zichtbaar op website</strong></label></p>'
                + '</div>'
                + '<p class="clsc-form-actions">'
                + '<button type="button" class="button button-primary clsc-fb-save" data-id="' + cardId + '">Opslaan</button>'
                + '<button type="button" class="button clsc-fb-cancel">Annuleer</button>'
                + '</p>'
                + '</div>'
                + '</div>'
                + '</td>'
                + '</tr>';

            $row.after(formHtml);

            var $formRow = $row.next();
            var $previewDiv = $formRow.find('.clsc-fb-preview');
            var $imageIdInput = $formRow.find('.clsc-fb-image-id');

            if (imageId) {
                var thumbSrc = $row.find('.clsc-thumb').attr('src');
                if (thumbSrc) {
                    $previewDiv.html('<img src="' + escapeHtml(thumbSrc) + '" alt="" class="clsc-preview-img" />');
                }
            }

            $formRow.on('click', '.clsc-fb-image', function (e) {
                e.preventDefault();
                openMediaFrame($imageIdInput, $previewDiv);
            });

            $formRow.on('click', '.clsc-fb-cancel', function (e) {
                e.preventDefault();
                $formRow.remove();
            });

            $formRow.on('click', '.clsc-fb-save', function (e) {
                e.preventDefault();

                var $saveButton = $(this);
                var postId = parseInt($saveButton.attr('data-id'), 10);
                var newTitle = String($formRow.find('.clsc-fb-title').val() || '');
                var newDesc = String($formRow.find('.clsc-fb-desc').val() || '');
                var newOrder = String($formRow.find('.clsc-fb-order').val() || '0');
                var newImageId = String($imageIdInput.val() || '');
                var newVisible = $formRow.find('.clsc-fb-visible').is(':checked') ? '1' : '0';

                $saveButton.prop('disabled', true).text('Bezig...');

                $.ajax({
                    url: CLSCAdmin.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'clsc_update_card',
                        nonce: CLSCAdmin.nonce,
                        post_id: postId,
                        card_title: newTitle,
                        card_desc: newDesc,
                        card_order: newOrder,
                        visible: newVisible,
                        image_id: newImageId
                    }
                }).done(function (response) {
                    if (!response || !response.success) {
                        var errorMessage = response && response.data && response.data.message ? response.data.message : 'Onbekend';
                        showToast('Opslaan mislukt: ' + errorMessage, 'error');
                        return;
                    }

                    var displayTitle = newTitle || $row.find('.clsc-col-service-page').text().trim();
                    $row.find('.clsc-col-card-title').text(displayTitle);
                    $row.find('.clsc-col-card-desc').text(newDesc);
                    $row.find('.clsc-col-order').text(newOrder || '0');

                    var visibleLabel = newVisible === '1' ? CLSCAdmin.labels.yes : CLSCAdmin.labels.no;
                    var $visibleCell = $row.find('.clsc-col-visible');
                    $visibleCell.html('<span class="clsc-visibility ' + (newVisible === '1' ? 'is-visible' : 'is-hidden') + '">' + escapeHtml(visibleLabel) + '</span>');

                    if (newImageId && $previewDiv.find('img').length) {
                        var newSrc = $previewDiv.find('img').attr('src');
                        if (newSrc) {
                            $row.find('.clsc-col-image').html('<img src="' + escapeHtml(newSrc) + '" alt="" class="clsc-thumb" />');
                        }
                    } else if (!newImageId) {
                        $row.find('.clsc-col-image').html('<span class="clsc-empty">-</span>');
                    }

                    $button.attr({
                        'data-image-id': newImageId,
                        'data-title': newTitle,
                        'data-desc': newDesc,
                        'data-order': newOrder,
                        'data-visible': newVisible
                    });

                    $formRow.remove();
                    showToast('Opgeslagen', 'success');
                }).fail(function () {
                    showToast('Opslaan mislukt', 'error');
                }).always(function () {
                    $saveButton.prop('disabled', false).text('Opslaan');
                });
            });
        });
    }

    $(initEditCardButtons);
}(jQuery));
