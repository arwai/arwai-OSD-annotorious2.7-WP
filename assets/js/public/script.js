jQuery(document).ready(function($) {

    if (typeof ArwaiOSD_ViewerConfig !== 'undefined' && ArwaiOSD_ViewerConfig.images && ArwaiOSD_ViewerConfig.images.length > 0) {

        const viewerId = ArwaiOSD_ViewerConfig.id;
        const images = ArwaiOSD_ViewerConfig.images;
        const ajaxUrl = ArwaiOSD_Vars.ajax_url;
        const prefixUrl = ArwaiOSD_Vars.prefixUrl; // Use the localized prefixUrl

        const osdContainer = document.getElementById(viewerId);
        if (!osdContainer) {
            console.error("OpenSeadragon container element not found:", viewerId);
            return;
        }
        
        const osdViewer = OpenSeadragon({
            id: viewerId,
            prefixUrl: prefixUrl, // Correctly set the prefixUrl for local button images
            sequenceMode: true,
            showSequenceControl: true,
            showReferenceStrip: true,
            showRotationControl: true,
            navPrevNextWrap: true,
            tileSources: images.map(img => ({
                type: img.type,
                url: img.url
            }))
        });

        const anno = OpenSeadragon.Annotorious(osdViewer);

        function loadAnnotationsForImage(attachmentId) {
            anno.clearAnnotations();
            if (!attachmentId) return;

            $.ajax({
                url: ajaxUrl,
                data: {
                    action: 'arwai_anno_get', // Correct AJAX action name
                    attachment_id: attachmentId
                },
                dataType: 'json',
                success: function(annotations) {
                    if (Array.isArray(annotations)) {
                       anno.setAnnotations(annotations);
                    }
                },
                error: function(xhr) {
                    console.error("Error loading annotations:", xhr.responseText);
                }
            });
        }

        osdViewer.addHandler('open', function() {
            const currentPage = osdViewer.currentPage();
            if (images[currentPage]) {
                loadAnnotationsForImage(images[currentPage].post_id);
            }
        });

        osdViewer.addHandler('page', function(event) {
            const newPage = event.page;
            if (images[newPage]) {
                loadAnnotationsForImage(images[newPage].post_id);
            }
        });

        // Annotation event handlers
        anno.on('createAnnotation', function(annotation) {
            $.post(ajaxUrl, { action: 'arwai_anno_add', annotation: JSON.stringify(annotation) });
        });

        anno.on('updateAnnotation', function(annotation) {
            $.post(ajaxUrl, { action: 'arwai_anno_update', annotation: JSON.stringify(annotation), annotationid: annotation.id });
        });

        anno.on('deleteAnnotation', function(annotation) {
            $.post(ajaxUrl, { action: 'arwai_anno_delete', annotation: JSON.stringify(annotation), annotationid: annotation.id });
        });

    }
});