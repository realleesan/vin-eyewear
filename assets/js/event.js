/**
 * Vin Eyewear - Event Page JavaScript
 * Handles event listing page filtering and event detail page interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    /* ============================================================
       EVENT LISTING PAGE - Filter and Navigation
       ============================================================ */
    
    // Check if we're on the events listing page
    const filterChips = document.querySelectorAll('[data-filter-event]');
    const eventCards = document.querySelectorAll('.event-card');

    if (filterChips.length > 0 && eventCards.length > 0) {
        // Event Listing Page Functionality
        handleEventListing();
    }

    function handleEventListing() {
        let activeCategory = 'all';

        // Ẩn/hiện card theo danh mục đang chọn.
        // Slug trên card (data-event-category) và trên chip (data-filter-event)
        // đều do app/views/event/index.php sinh ra từ cùng một hàm slug hoá.
        function applyFilter() {
            eventCards.forEach(card => {
                const cardCategory = card.getAttribute('data-event-category');
                const visible = activeCategory === 'all' || cardCategory === activeCategory;

                if (visible) {
                    card.style.display = ''; // tra ve display cua .event-card trong CSS
                    setTimeout(() => {
                        card.style.opacity = '1';
                    }, 50);
                } else {
                    card.style.display = 'none';
                    card.style.opacity = '0';
                }
            });
        }

        function setActiveChip(selected) {
            filterChips.forEach(chip => {
                const isActive = chip === selected;
                chip.classList.toggle('is-active', isActive);
                chip.setAttribute('aria-pressed', String(isActive));
            });
        }

        filterChips.forEach(chip => {
            chip.addEventListener('click', function() {
                activeCategory = this.getAttribute('data-filter-event');
                setActiveChip(this);
                applyFilter();
            });
        });

        // Nút "Đặt lại" của filter-sidebar.js chỉ reset giao diện chip rồi bắn
        // sự kiện 'filterReset'; phải tự lọc lại card, nếu không card vẫn bị ẩn.
        document.addEventListener('filterReset', function() {
            activeCategory = 'all';
            applyFilter();
        });

        // Click on event card image or title should navigate to detail
        eventCards.forEach(card => {
            const link = card.querySelector('.event-link');
            const href = link ? link.getAttribute('href') : null;

            if (href) {
                // Make entire card clickable
                card.style.cursor = 'pointer';
                
                card.addEventListener('click', function(e) {
                    // Prevent double navigation if link is clicked directly
                    if (e.target !== link && e.target !== link?.parentElement) {
                        window.location.href = href;
                    }
                });

                // Make image and title directly clickable
                const image = card.querySelector('.event-image');
                const title = card.querySelector('.event-title');

                if (image) {
                    image.style.cursor = 'pointer';
                    image.addEventListener('click', function(e) {
                        e.stopPropagation();
                        window.location.href = href;
                    });
                }

                if (title) {
                    title.style.cursor = 'pointer';
                    title.addEventListener('click', function(e) {
                        e.stopPropagation();
                        window.location.href = href;
                    });
                }
            }
        });
    }

    /* ============================================================
       EVENT DETAIL PAGE - Related Events Navigation
       ============================================================ */
    
    // Make related event cards clickable
    const relatedEventCards = document.querySelectorAll('.related-event-card');
    if (relatedEventCards.length > 0) {
        handleRelatedEvents();
    }

    function handleRelatedEvents() {
        relatedEventCards.forEach(card => {
            const link = card.querySelector('a');
            const href = link ? link.getAttribute('href') : null;

            if (href) {
                card.style.cursor = 'pointer';
                
                // Make image clickable
                const image = card.querySelector('.related-event-image img');
                const title = card.querySelector('.related-event-title');

                if (image) {
                    image.style.cursor = 'pointer';
                    image.addEventListener('click', function(e) {
                        e.stopPropagation();
                        window.location.href = href;
                    });
                }

                if (title) {
                    title.style.cursor = 'pointer';
                    title.addEventListener('click', function(e) {
                        e.stopPropagation();
                        window.location.href = href;
                    });
                }
            }
        });
    }

    /* ============================================================
       IMAGE MODAL (Legacy - for old listing layouts)
       ============================================================ */
    
    const imageModal = document.getElementById('imageModal');
    if (imageModal) {
        handleImageModal();
    }

    function handleImageModal() {
        const modalImage = document.getElementById('modalImage');
        const modalClose = document.querySelector('.modal-close');
        const modalBackdrop = document.querySelector('.modal-backdrop');
        const eventImages = document.querySelectorAll('.event-image img');

        // Open modal when clicking on event images (if not on detail page)
        eventImages.forEach(img => {
            img.addEventListener('click', function() {
                // Don't open modal on detail page - use navigation instead
                if (document.querySelector('.event-detail-section')) {
                    return;
                }
                
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

        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }

        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', closeModal);
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && imageModal.classList.contains('active')) {
                closeModal();
            }
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
                closeModal();
            }
        }
    }
});