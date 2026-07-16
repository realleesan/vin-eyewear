// Event data passed from controller via global variable
const eventData = window.eventData || {};

// Event Carousel with Auto-run and Hover Backface
document.addEventListener('DOMContentLoaded', function() {
    const carouselTrack = document.querySelector('.carousel-track');
    const carouselItems = document.querySelectorAll('.carousel-item');
    const indicators = document.querySelectorAll('.indicator');
    const storyModal = document.getElementById('storyModal');
    const modalCloseBtn = document.querySelector('.modal-close-btn');
    const modalBackdrop = document.querySelector('.modal-backdrop');
    
    let currentIndex = 0;
    const totalItems = carouselItems.length;
    const itemsPerSlide = 3;
    const totalSlides = Math.ceil(totalItems / itemsPerSlide);
    let autoPlayInterval;
    const autoPlayDelay = 2000; // 2 seconds
    let isHovering = false;
    
    function updateCarousel() {
        const translateX = -(currentIndex * (100 / itemsPerSlide));
        carouselTrack.style.transform = `translateX(${translateX}%)`;
        
        // Update indicators
        const currentSlide = Math.floor(currentIndex / itemsPerSlide);
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentSlide);
        });
    }
    
    function nextSlide() {
        const maxIndex = totalItems - itemsPerSlide;
        currentIndex = (currentIndex + 1) % (maxIndex + 1);
        updateCarousel();
    }
    
    function prevSlide() {
        const maxIndex = totalItems - itemsPerSlide;
        currentIndex = (currentIndex - 1 + maxIndex + 1) % (maxIndex + 1);
        updateCarousel();
    }
    
    function goToSlide(slideIndex) {
        currentIndex = slideIndex * itemsPerSlide;
        const maxIndex = totalItems - itemsPerSlide;
        if (currentIndex > maxIndex) {
            currentIndex = maxIndex;
        }
        updateCarousel();
    }
    
    function startAutoPlay() {
        if (!isHovering) {
            autoPlayInterval = setInterval(nextSlide, autoPlayDelay);
        }
    }
    
    function stopAutoPlay() {
        clearInterval(autoPlayInterval);
    }
    
    // Indicator clicks
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            goToSlide(index);
            stopAutoPlay();
            startAutoPlay();
        });
    });
    
    // Hover to pause auto-play
    carouselTrack.addEventListener('mouseenter', () => {
        isHovering = true;
        stopAutoPlay();
    });
    
    carouselTrack.addEventListener('mouseleave', () => {
        isHovering = false;
        startAutoPlay();
    });
    
    // Click on carousel item to open modal
    carouselItems.forEach(item => {
        item.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            const event = eventData[eventId];
            
            if (event) {
                document.getElementById('storyDate').textContent = event.date;
                document.getElementById('storyTitle').textContent = event.title;
                document.getElementById('storyDescription').innerHTML = event.description;
                document.getElementById('storyImage').src = event.image;
                document.getElementById('storyImage').alt = event.title;
                
                storyModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                stopAutoPlay();
            }
        });
    });
    
    // Close modal
    function closeModal() {
        storyModal.classList.remove('active');
        document.body.style.overflow = '';
        startAutoPlay();
    }
    
    modalCloseBtn.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', closeModal);
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && storyModal.classList.contains('active')) {
            closeModal();
        }
        if (e.key === 'ArrowRight' && !storyModal.classList.contains('active')) {
            stopAutoPlay();
            nextSlide();
            startAutoPlay();
        }
        if (e.key === 'ArrowLeft' && !storyModal.classList.contains('active')) {
            stopAutoPlay();
            prevSlide();
            startAutoPlay();
        }
    });
    
    // Initialize carousel
    updateCarousel();
    startAutoPlay();
});