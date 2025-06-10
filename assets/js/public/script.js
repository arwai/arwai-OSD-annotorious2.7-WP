/**
 * A formatter to display a label on top of the annotation shape on the canvas.
 * It looks for a body with the purpose 'arwai-AnnotationID' and displays its value.
 */
const arwaiAnnotationIDFormatter = function(annotation) {
    const labelBody = annotation.body.find(b => b.purpose === 'arwai-AnnotationID');

    if (labelBody) {
        const foreignObject = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');
        foreignObject.innerHTML =
            `<label class="arwai-annotation-label" xmlns="http://www.w3.org/1999/xhtml">${labelBody.value}</label>`;
        
        return {
           element: foreignObject
        };
    }
}

/**
 * A formatter to change the stroke color of an annotation if it has tags.
 */
const arwaiTagColorFormatter = function(annotation) {
    // Find tagging bodies
    const tagBodies = annotation.body.filter(function (body) {
        return body.purpose === 'tagging';
    });

    // If there is at least one tag, return a blue stroke style.
    if (tagBodies.length > 0) {
        return 'tagged'; // This will use the default style for tagged annotations.
    }

    // Otherwise, return null to use the default style.
    return null;
}

/**
 * A formatter to change the stroke color of an annotation if it has tag of "Important".
 */ 
const MyImportantFormatter = function(annotation) {
  const isImportant = annotation.bodies.find(b => {
    return b.purpose === 'tagging' && b.value.toLowerCase() === 'important'
  });
  
  if (isImportant) {
    return 'important';
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

    // --- Translation Dictionary ---
    const translations = {
        'en-alt': {
          'Add a comment...': 'Add a comment...',
          'Add tag...': 'Add name or tag...',
          'Cancel': 'Cancel',
          'Done': 'Done'
        },
        'pt': {
          'Add a comment...': 'Adicionar um comentário...',
          'Add tag...': 'Adicionar nome ouetiqueta...',
          'Cancel': 'Cancelar',
          'Done': 'Feito'
        }
    };
    
    const currentLocale = annoOptions.locale || 'en-alt';

    // Prepare Annotorious configuration
    const annoConfig = {
        fragmentUnit: 'percent',
        readOnly: (currentUser === null),
        allowEmpty: annoOptions.allowEmpty,
        drawOnSingleClick: annoOptions.drawOnSingleClick,
        formatters: [ 
            arwaiAnnotationIDFormatter, 
            arwaiTagColorFormatter,
            MyImportantFormatter
        ],
        locale: currentLocale,
        messages: translations[currentLocale] || translations['en-alt'],
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
        $.post(ajaxUrl, { 
            action: 'arwai_anno_add', 
            annotation: JSON.stringify(annotation) 
        }).done(function(response) {
            if (response.success && response.data.annotation) {
                const completeAnnotation = response.data.annotation;
                anno.removeAnnotation(annotation);
                anno.addAnnotation(completeAnnotation);
            }
        });

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