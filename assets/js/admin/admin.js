jQuery(document).ready(function($) {
    var $container = $('#arwai-multi-image-uploader-container');
    if (!$container.length) {
        return;
    }

    var $imageList = $container.find('.arwai-multi-image-list');
    var $hiddenField = $container.find('#arwai_multi_image_ids_field');
    var mediaFrame;

    $imageList.sortable({
        placeholder: "arwai-multi-image-placeholder",
        stop: function() {
            updateHiddenField();
        }
    }).disableSelection();

    $container.on('click', '.arwai-multi-image-add-button', function(e) {
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

    $container.on('click', '.arwai-multi-image-remove', function(e) {
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
});