jQuery(document).ready(function($) {
    if (typeof ArwaiGalleryData === 'undefined' || !ArwaiGalleryData.images || ArwaiGalleryData.images.length === 0) {
        return;
    }

    const images = ArwaiGalleryData.images;
    const galleryWidth = ArwaiGalleryData.width || '100%';
    const galleryHeight = ArwaiGalleryData.height || '600px';
    let currentIndex = 0;

    const galleryContainer = $('#arwai-gallery-container');
    const mainImage = $('.arwai-main-image');
    const thumbnailStrip = $('.arwai-thumbnail-strip');
    const prevButton = $('.arwai-prev');
    const nextButton = $('.arwai-next');

    // Set the dimensions of the gallery container
    galleryContainer.css({
        'width': galleryWidth,
        'height': galleryHeight
    });

    function updateGallery(index) {
        // Fade out, change src, fade in
        mainImage.css('opacity', 0);
        setTimeout(function() {
            mainImage.attr('src', images[index].full);
            mainImage.css('opacity', 1);
        }, 300);

        // Update active thumbnail
        thumbnailStrip.find('img').removeClass('arwai-active-thumb');
        thumbnailStrip.find('img[data-index="' + index + '"]').addClass('arwai-active-thumb');

        currentIndex = index;
    }

    // Populate thumbnails
    images.forEach(function(img, index) {
        const thumb = $('<img>')
            .attr('src', img.thumbnail)
            .attr('data-index', index)
            .on('click', function() {
                updateGallery(index);
            });
        thumbnailStrip.append(thumb);
    });

    // Event listeners for prev/next buttons
    prevButton.on('click', function() {
        const newIndex = (currentIndex - 1 + images.length) % images.length;
        updateGallery(newIndex);
    });

    nextButton.on('click', function() {
        const newIndex = (currentIndex + 1) % images.length;
        updateGallery(newIndex);
    });

    // Initialize the gallery with the first image
    updateGallery(0);
});