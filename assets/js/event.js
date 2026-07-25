// Event Page - Grid Layout with Image Modal and Filter
document.addEventListener('DOMContentLoaded', function() {
    // Image Modal Functionality
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalClose = document.querySelector('.modal-close');
    const modalBackdrop = document.querySelector('.modal-backdrop');
    const eventImages = document.querySelectorAll('.event-image img');

    // Open modal when clicking on event images
    eventImages.forEach(img => {
        img.addEventListener('click', function() {
            modalImage.src = this.src;
            modalImage.alt = this.alt;
            imageModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal functions
    function closeModal() {
        imageModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    modalClose.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', closeModal);

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && imageModal.classList.contains('active')) {
            closeModal();
        }
    });

    // Event Filter Functionality
    const filterChips = document.querySelectorAll('[data-filter-event]');
    const eventCards = document.querySelectorAll('.event-card');

    filterChips.forEach(chip => {
        chip.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-filter-event');

            // Update active state
            filterChips.forEach(c => {
                c.classList.remove('is-active');
                c.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('is-active');
            this.setAttribute('aria-pressed', 'true');

            // Filter event cards
            eventCards.forEach(card => {
                const cardCategory = card.getAttribute('data-event-category');
                
                if (filterValue === 'all' || cardCategory === filterValue) {
                    card.style.display = 'flex';
                    // Add fade-in animation
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.style.opacity = '1';
                    }, 50);
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Touch swipe support for image modal (mobile)
    let touchStartX = 0;
    let touchEndX = 0;

    imageModal.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    imageModal.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);

    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;

        if (Math.abs(diff) > swipeThreshold) {
            // Swipe detected - close modal
            closeModal();
        }
    }
});