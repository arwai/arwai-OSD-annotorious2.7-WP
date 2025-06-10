jQuery(document).ready(function($) {

    // Check if the main configuration object exists
    if (typeof ArwaiOSD_ViewerConfig === 'undefined' || !ArwaiOSD_ViewerConfig.images || ArwaiOSD_ViewerConfig.images.length === 0) {
        return;
    }

    // Main Viewer Configuration
    const viewerId = ArwaiOSD_ViewerConfig.id;
    const images = ArwaiOSD_ViewerConfig.images;
    
    // Global variables (like AJAX URL)
    const ajaxUrl = ArwaiOSD_Vars.ajax_url;

    // Options from the plugin settings page
    const osdOptions = ArwaiOSD_Options.osd_options || {};
    const annoOptions = ArwaiOSD_Options.anno_options || {};

    const osdContainer = document.getElementById(viewerId);
    if (!osdContainer) {
        console.error("OpenSeadragon container element not found:", viewerId);
        return;
    }
    
    // Initialize OpenSeadragon with a combination of hardcoded and dynamic options
    const osdViewer = OpenSeadragon({
        // Hardcoded options as per request
        id: viewerId,
        tileSources: images.map(img => ({
            type: img.type,
            url: img.url
        })),

        // Dynamic options from settings page
        ...osdOptions 
    });

    // Prepare Annotorious configuration
    const annoConfig = {
        fragmentUnit: 'percent',
        readOnly: annoOptions.readOnly,
        allowEmpty: annoOptions.allowEmpty,
        drawOnSingleClick: annoOptions.drawOnSingleClick,
        // Configure widgets. Both Tag and Comment are always on.
        widgets: [
            'COMMENT',
            { 
                widget: 'TAG', 
                vocabulary: annoOptions.tagVocabulary || [] 
            }
        ]
    };

    // Initialize Annotorious
    const anno = OpenSeadragon.Annotorious(osdViewer, annoConfig);

    // --- Two-Way Tag Sync ---
    function syncTagsToWordPress(annotation) {
        const linkedTaxonomy = annoOptions.linkTaxonomy;
        const tagVocabulary = annoOptions.tagVocabulary || [];

        // Proceed only if a taxonomy is linked
        if (!linkedTaxonomy || linkedTaxonomy === 'none') {
            return;
        }

        // Find all tag bodies in the annotation
        const annotationTags = annotation.body
            .filter(b => b.purpose === 'tagging')
            .map(b => b.value);
        
        // Check each tag
        annotationTags.forEach(tag => {
            // If the tag is not in the original vocabulary, it's new
            if (tag && !tagVocabulary.includes(tag)) {
                
                // Add to local vocabulary to prevent duplicate requests on this page load
                tagVocabulary.push(tag);

                // Make AJAX call to add the term to the WordPress taxonomy
                $.post(ajaxUrl, {
                    action: 'arwai_add_taxonomy_term',
                    taxonomy: linkedTaxonomy,
                    term: tag,
                    nonce: annoOptions.addTermNonce
                }).fail(function(xhr) {
                    console.error("Error adding new tag to WordPress:", xhr.responseText);
                });
            }
        });
    }


    // Function to load annotations for the currently visible image
    function loadAnnotationsForImage(attachmentId) {
        anno.clearAnnotations();
        if (!attachmentId) return;

        $.ajax({
            url: ajaxUrl,
            data: {
                action: 'arwai_anno_get',
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

    // Load annotations when the viewer opens the first image
    osdViewer.addHandler('open', function() {
        const currentPage = osdViewer.currentPage();
        if (images[currentPage]) {
            loadAnnotationsForImage(images[currentPage].post_id);
        }
    });

    // Load annotations when the page changes
    osdViewer.addHandler('page', function(event) {
        const newPage = event.page;
        if (images[newPage]) {
            loadAnnotationsForImage(images[newPage].post_id);
        }
    });

    // --- Annotation event handlers ---
    anno.on('createAnnotation', function(annotation) {
        // First, save the annotation to our database
        $.post(ajaxUrl, { action: 'arwai_anno_add', annotation: JSON.stringify(annotation) });
        // Then, sync any new tags back to WordPress
        syncTagsToWordPress(annotation);
    });

    anno.on('updateAnnotation', function(annotation) {
        // First, save the updated annotation to our database
        $.post(ajaxUrl, { action: 'arwai_anno_update', annotation: JSON.stringify(annotation), annotationid: annotation.id });
        // Then, sync any new tags back to WordPress
        syncTagsToWordPress(annotation);
    });

    anno.on('deleteAnnotation', function(annotation) {
        $.post(ajaxUrl, { action: 'arwai_anno_delete', annotation: JSON.stringify(annotation), annotationid: annotation.id });
    });

});