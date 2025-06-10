/**
 * A formatter to display a label on top of the annotation shape on the canvas.
 * It looks for a body with the purpose 'arwai-AnnotationID' and displays its value.
 */
const arwaiAnnotationIDFormatter = function(annotation) {
    // The annotation data is in the `body` array.
    const labelBody = annotation.body.find(b => b.purpose === 'arwai-AnnotationID');

    if (labelBody) {
        // Create an SVG 'foreignObject' to wrap our HTML label.
        // This is necessary to render HTML inside the SVG annotation layer.
        const foreignObject = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');

        // Set the content of the foreignObject.
        foreignObject.innerHTML =
            `<label class="arwai-annotation-label" xmlns="http://www.w3.org/1999/xhtml">${labelBody.value}</label>`;
        
        // Return the element in the format Annotorious expects for canvas formatters.
        return {
           element: foreignObject
        };
    }
}


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
    const currentUser = annoOptions.currentUser || null;

    const osdContainer = document.getElementById(viewerId);
    if (!osdContainer) {
        console.error("OpenSeadragon container element not found:", viewerId);
        return;
    }
    
    // Combine hardcoded options with dynamic options from the settings page
    const finalOsdConfig = {
        id: viewerId,
        tileSources: images.map(img => ({
            type: img.type,
            url: img.url
        })),
        ...osdOptions 
    };
    
    const osdViewer = OpenSeadragon(finalOsdConfig);

    // Prepare Annotorious configuration
    const annoConfig = {
        fragmentUnit: 'percent',
        readOnly: (currentUser === null),
        allowEmpty: annoOptions.allowEmpty,
        drawOnSingleClick: annoOptions.drawOnSingleClick,
        formatters: [ arwaiAnnotationIDFormatter ],
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

    // Set the user information if a WP user is logged in
    if (currentUser) {
        anno.setAuthInfo({
            id: currentUser.id,
            displayName: currentUser.displayName
        });
    }

    // --- Two-Way Tag Sync ---
    function syncTagsToWordPress(annotation) {
        const linkedTaxonomy = annoOptions.linkTaxonomy;
        const tagVocabulary = annoOptions.tagVocabulary || [];

        if (!linkedTaxonomy || linkedTaxonomy === 'none') {
            return;
        }

        const annotationTags = annotation.body
            .filter(b => b.purpose === 'tagging')
            .map(b => b.value);
        
        annotationTags.forEach(tag => {
            if (tag && !tagVocabulary.includes(tag)) {
                tagVocabulary.push(tag);
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
        // Send the annotation to the backend to be saved.
        $.post(ajaxUrl, { 
            action: 'arwai_anno_add', 
            annotation: JSON.stringify(annotation) 
        }).done(function(response) {
            // After the server saves it and adds the DB ID, it sends back the complete annotation.
            if (response.success && response.data.annotation) {
                const completeAnnotation = response.data.annotation;

                // To update the annotation on screen, we remove the temporary one...
                anno.removeAnnotation(annotation);

                // ...and add the final, complete version from the server.
                // This will re-trigger the formatter and display the new label.
                anno.addAnnotation(completeAnnotation);
            }
        });

        // Sync tags immediately
        syncTagsToWordPress(annotation);
    });

    anno.on('updateAnnotation', function(annotation, previous) {
        $.post(ajaxUrl, { action: 'arwai_anno_update', annotation: JSON.stringify(annotation), annotationid: annotation.id });
        syncTagsToWordPress(annotation);
    });

    anno.on('deleteAnnotation', function(annotation) {
        $.post(ajaxUrl, { action: 'arwai_anno_delete', annotation: JSON.stringify(annotation), annotationid: annotation.id });
    });

});