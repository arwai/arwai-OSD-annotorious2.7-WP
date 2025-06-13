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
    const tagBodies = annotation.body.filter(function (body) {
        return body.purpose === 'tagging';
    });
    if (tagBodies.length > 0) {
        return 'tagged';
    }
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

    if (typeof ArwaiOSD_ViewerConfig === 'undefined' || !ArwaiOSD_ViewerConfig.images || ArwaiOSD_ViewerConfig.images.length === 0) {
        return;
    }

    const viewerId = ArwaiOSD_ViewerConfig.id;
    const images = ArwaiOSD_ViewerConfig.images;
    const ajaxUrl = ArwaiOSD_Vars.ajax_url;
    const osdOptions = ArwaiOSD_Options.osd_options || {};
    const annoOptions = ArwaiOSD_Options.anno_options || {};
    const currentUser = annoOptions.currentUser || null;


    const osdContainer = document.getElementById(viewerId);
    if (!osdContainer) {
        console.error("OpenSeadragon container element not found:", viewerId);
        return;
    }
    
    const finalOsdConfig = {
        id: viewerId,
        // Pass thumbnail URL to OSD's internal tileSources object
        tileSources: images.map(img => ({
            type: img.type,
            url: img.url,
            thumbnail: img.thumbnailUrl
        })),
        ...osdOptions 
    };
    
    const osdViewer = OpenSeadragon(finalOsdConfig);

    // --- Custom Reference Strip Functions ---
    function updateActiveThumbnail(page) {
        const strip = document.getElementById('arwai-custom-reference-strip');
        if (!strip) return;
        strip.querySelectorAll('img').forEach(thumb => thumb.classList.remove('active'));
        const activeThumb = strip.querySelector(`img[data-page-index="${page}"]`);
        if (activeThumb) {
            activeThumb.classList.add('active');
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    function createCustomReferenceStrip(viewer) {
        const container = document.getElementById('arwai-custom-reference-strip');
        if (!container || viewer.tileSources.length <= 1) {
            if (container) container.style.display = 'none';
            return;
        }
        container.innerHTML = '';
        viewer.tileSources.forEach((tileSource, index) => {
            if (!tileSource.thumbnail) return;
            const thumb = document.createElement('img');
            thumb.src = tileSource.thumbnail;
            thumb.className = 'arwai-thumbnail';
            thumb.dataset.pageIndex = index;
            thumb.addEventListener('click', () => viewer.goToPage(index));
            container.appendChild(thumb);
        });
        updateActiveThumbnail(viewer.currentPage());
    }



    // --- END: Custom Reference Strip Functions ---

    
    // --- Get translations from settings provided by PHP ---
    const currentLocale = annoOptions.locale || 'English';
    // Fallback to English if no locale is set
    const allMessages = annoOptions.translations || {};

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
        // Use the dictionary from settings, with a fallback
        messages: allMessages[currentLocale] || allMessages['en'] || {},
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


     osdViewer.addHandler('open', function() {
        const currentPage = osdViewer.currentPage();
        if (images[currentPage]) {
            loadAnnotationsForImage(images[currentPage].post_id);
        }
        createCustomReferenceStrip(osdViewer);

        const scrollableDiv = document.getElementById('arwai-custom-reference-strip');

        // --- Draggable Reference Strip Logic ---
        if (scrollableDiv) {
            let isDragging = false, startX, scrollLeft;
            scrollableDiv.style.cursor = 'grab';
            scrollableDiv.addEventListener('mousedown', (e) => { isDragging = true; scrollableDiv.style.cursor = 'grabbing'; startX = e.pageX - scrollableDiv.offsetLeft; scrollLeft = scrollableDiv.scrollLeft; });
            scrollableDiv.addEventListener('mouseleave', () => { isDragging = false; scrollableDiv.style.cursor = 'grab'; });
            scrollableDiv.addEventListener('mouseup', () => { isDragging = false; scrollableDiv.style.cursor = 'grab'; });
            scrollableDiv.addEventListener('mousemove', (e) => { if (!isDragging) return; e.preventDefault(); const x = e.pageX - scrollableDiv.offsetLeft; const walk = (x - startX); scrollableDiv.scrollLeft = scrollLeft - walk; });
        }
        
        // --- Arrow Scroll for Reference Strip ---
        const scrollAmount = 400; // Adjust as needed
        const leftArrow = document.getElementById('arwai-strip-scroll-left');
        const rightArrow = document.getElementById('arwai-strip-scroll-right');

        if (leftArrow && rightArrow && scrollableDiv) {
            leftArrow.addEventListener('click', () => {
                scrollableDiv.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });

            rightArrow.addEventListener('click', () => {
                scrollableDiv.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });
        }
    });

    osdViewer.addHandler('page', function(event) {
        const newPage = event.page;
        if (images[newPage]) {
            loadAnnotationsForImage(images[newPage].post_id);
        }
        updateActiveThumbnail(newPage); // Update the active thumbnail highlight
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