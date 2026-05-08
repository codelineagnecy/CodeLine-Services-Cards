(function($) {
    'use strict';

    function showToast(message, type) {
        var toast = $('<div class="clsc-toast"></div>');
        toast.addClass(type === 'error' ? 'is-error' : 'is-success');
        toast.text(message);
        $('body').append(toast);

        window.setTimeout(function() {
            toast.addClass('is-visible');
        }, 10);

        window.setTimeout(function() {
            toast.removeClass('is-visible');
            window.setTimeout(function() {
                toast.remove();
            }, 250);
        }, 2200);
    }

    // Open WordPress media picker
    function openMediaFrame($inputField, $previewDiv) {
        if (!wp.media) {
            alert('WordPress Media niet beschikbaar');
            return;
        }

        var frame = wp.media({
            title: 'Selecteer afbeelding',
            button: { text: 'Gebruik deze afbeelding' },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            if (attachment && attachment.id) {
                $inputField.val(attachment.id);
                if (attachment.url) {
                    $previewDiv.html('<img src="' + attachment.url + '" class="clsc-preview-img" />');
                }
            }
        });

        frame.open();
    }

    // Initialize edit button handler
    function initEditCardButtons() {
        $(document).on('click', '.clsc-edit-card-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var cardId = $btn.data('id');
            var $row = $btn.closest('tr');

            // Close form if already open
            if ($row.next().hasClass('clsc-fallback-row')) {
                $row.next().remove();
                return;
            }

            // Close any other open forms
            $('.clsc-fallback-row').remove();

            // Get current data from row
            var imageId = $btn.data('image-id') || '';
            var title = $row.find('td').eq(1).text().trim();
            var desc = $row.find('td').eq(2).text().trim();

            // Build form HTML
            var formHtml = '<tr class="clsc-fallback-row">'
                + '<td colspan="5">'
                + '<div class="clsc-edit-form">'
                + '<div class="clsc-edit-form-image">'
                + '<div class="clsc-fb-preview">'
                + (imageId ? '<img src="" class="clsc-preview-img" />' : '<span class="clsc-empty">Geen afb.</span>')
                + '</div>'
                + '<p><button type="button" class="button button-secondary clsc-fb-image">📷 Kies afbeelding</button></p>'
                + '<input type="hidden" class="clsc-fb-image-id" value="' + imageId + '" />'
                + '</div>'
                + '<div class="clsc-edit-form-content">'
                + '<p><strong>Titel</strong><br />'
                + '<input type="text" class="clsc-fb-title" value="' + $('<div/>').text(title).html() + '" />'
                + '</p>'
                + '<p><strong>Beschrijving</strong><br />'
                + '<textarea class="clsc-fb-desc">' + $('<div/>').text(desc).html() + '</textarea>'
                + '</p>'
                + '<p>'
                + '<button class="button button-primary clsc-fb-save" data-id="' + cardId + '">💾 Opslaan</button>'
                + '<button class="button clsc-fb-cancel">✕ Annuleer</button>'
                + '</p>'
                + '</div>'
                + '</div>'
                + '</td>'
                + '</tr>';

            $row.after(formHtml);
            var $formRow = $row.next();
            var $previewDiv = $formRow.find('.clsc-fb-preview');
            var $imageIdInput = $formRow.find('.clsc-fb-image-id');

            // Load initial image preview
            if (imageId) {
                var $thumb = $row.find('.clsc-thumb');
                if ($thumb.length) {
                    var thumbSrc = $thumb.attr('src');
                    if (thumbSrc) {
                        $previewDiv.html('<img src="' + thumbSrc + '" class="clsc-preview-img" />');
                    }
                }
            }

            // Image picker button
            $formRow.on('click', '.clsc-fb-image', function(e) {
                e.preventDefault();
                openMediaFrame($imageIdInput, $previewDiv);
            });

            // Cancel button
            $formRow.on('click', '.clsc-fb-cancel', function(e) {
                e.preventDefault();
                $formRow.remove();
            });

            // Save button
            $formRow.on('click', '.clsc-fb-save', function(e) {
                e.preventDefault();

                var $saveBtn = $(this);
                var postId = parseInt($saveBtn.data('id'), 10);
                var newTitle = $formRow.find('.clsc-fb-title').val();
                var newDesc = $formRow.find('.clsc-fb-desc').val();
                var newImageId = $imageIdInput.val() || '';

                $saveBtn.prop('disabled', true).text('Bezig...');

                // Make AJAX request
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
                        image_id: newImageId
                    },
                    success: function(response) {
                        if (response && response.success) {
                            // Update table row
                            $row.find('td').eq(1).text(newTitle);
                            $row.find('td').eq(2).text(newDesc);

                            // Update image
                            if (newImageId && $previewDiv.find('img').length) {
                                var newSrc = $previewDiv.find('img').attr('src');
                                if (newSrc) {
                                    $row.find('td').eq(0).html('<img src="' + newSrc + '" class="clsc-thumb" />');
                                }
                            } else if (!newImageId) {
                                $row.find('td').eq(0).html('<span class="clsc-empty">-</span>');
                            }

                            $formRow.remove();
                            showToast('Opgeslagen', 'success');
                        } else {
                            var errorMsg = response && response.data && response.data.message ? response.data.message : 'Onbekend';
                            showToast('Opslaan mislukt: ' + errorMsg, 'error');
                        }
                    },
                    error: function() {
                        showToast('Opslaan mislukt', 'error');
                    },
                    complete: function() {
                        $saveBtn.prop('disabled', false).text('💾 Opslaan');
                    }
                });
            });
        });
    }

    // Initialize when DOM ready
    $(document).ready(function() {
        initEditCardButtons();
    });

})(jQuery);
