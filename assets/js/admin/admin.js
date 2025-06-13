jQuery(document).ready(function($) {

    // --- Settings Page Logic ---
    // This code only runs on the settings page
    if ($('.arwai-toggle-list').length) {

        // Hide all toggle content by default and set the header to the 'closed' state.
        $('.arwai-toggle-list-content').hide();
        $('.arwai-toggle-list-header').removeClass('open');

        // Logic for the collapsible sections
        $('.arwai-toggle-list .arwai-toggle-list-header').on('click', function() {
            $(this).next('.arwai-toggle-list-content').slideToggle();
            $(this).toggleClass('open');
        });

        // Initialize the color picker
        $('.arwai-color-picker').wpColorPicker();

        // Start with the taxonomy toggle list closed by default.
        $('#field_anno_taxonomy .arwai-toggle-list .arwai-toggle-list-content').hide();
        $('#field_anno_taxonomy .arwai-toggle-list .arwai-toggle-list-header').removeClass('open');
    }


    // --- Metabox Image Uploader Logic ---
    // This code only runs on the post edit screen
    var $metaboxContainer = $('#arwai-multi-image-uploader-container');
    if ($metaboxContainer.length) {
        var $imageList = $metaboxContainer.find('.arwai-multi-image-list');
        var $hiddenField = $metaboxContainer.find('#arwai_multi_image_ids_field');
        var mediaFrame;

        $imageList.sortable({
            placeholder: "arwai-multi-image-placeholder",
            stop: function() {
                updateHiddenField();
            }
        }).disableSelection();

        $metaboxContainer.on('click', '.arwai-multi-image-add-button', function(e) {
            e.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: 'Select Images for Collection',
                button: { text: 'Use these images' },
                multiple: true
            });

            mediaFrame.on('select', function() {
                var selection = mediaFrame.state().get('selection');
                var currentIds = $hiddenField.val() ? JSON.parse($hiddenField.val()) : [];
                if (!Array.isArray(currentIds)) currentIds = [];

                selection.each(function(attachment) {
                    var id = attachment.id;
                    if ($.inArray(id, currentIds) === -1) {
                        currentIds.push(id);
                        var thumbUrl = attachment.attributes.sizes.thumbnail ? attachment.attributes.sizes.thumbnail.url : attachment.attributes.url;
                        $imageList.append(createImageLi(id, thumbUrl));
                    }
                });
                updateHiddenField(currentIds);
            });

            mediaFrame.open();
        });

        $metaboxContainer.on('click', '.arwai-multi-image-remove', function(e) {
            e.preventDefault();
            var $item = $(this).closest('li');
            var idToRemove = $item.data('id');
            
            var currentIds = $hiddenField.val() ? JSON.parse($hiddenField.val()) : [];
            if (!Array.isArray(currentIds)) currentIds = [];
            
            var newIds = $.grep(currentIds, function(value) {
                return value != idToRemove;
            });

            $item.remove();
            updateHiddenField(newIds);
        });

        function createImageLi(id, thumbUrl) {
            return '<li data-id="' + id + '"><img src="' + thumbUrl + '" style="max-width:100px; max-height:100px; display:block;"/><a href="#" class="arwai-multi-image-remove dashicons dashicons-trash" title="Remove image"></a></li>';
        }

        function updateHiddenField(ids) {
            if (!ids) {
                ids = [];
                $imageList.find('li').each(function() {
                    ids.push($(this).data('id'));
                });
            }
            $hiddenField.val(JSON.stringify(ids)).trigger('change');
        }
    }
});