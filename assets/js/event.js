// Event data passed from controller via global variable
const eventData = window.eventData || {};

// Slideshow functionality
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.event-slide');
    const dots = document.querySelectorAll('.nav-dot');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const modal = document.getElementById('eventDetailModal');
    let currentSlide = 0;
    const totalSlides = slides.length;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.remove('active');
            dots[i].classList.remove('active');
        });
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentSlide = index;
    }

    function nextSlide() {
        showSlide((currentSlide + 1) % totalSlides);
    }

    function prevSlide() {
        showSlide((currentSlide - 1 + totalSlides) % totalSlides);
    }

    // Navigation buttons
    nextBtn.addEventListener('click', nextSlide);
    prevBtn.addEventListener('click', prevSlide);

    // Dot navigation
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => showSlide(index));
    });

    // Auto-play slideshow
    let autoPlay = setInterval(nextSlide, 5000);

    // Pause on hover
    const slideshowContainer = document.querySelector('.slideshow-container');
    slideshowContainer.addEventListener('mouseenter', () => clearInterval(autoPlay));
    slideshowContainer.addEventListener('mouseleave', () => autoPlay = setInterval(nextSlide, 5000));

    // Click on slide to open modal
    slides.forEach(slide => {
        slide.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            const event = eventData[eventId];
            
            if (event) {
                document.getElementById('modalEventDate').textContent = event.date;
                document.getElementById('modalEventTitle').textContent = event.title;
                document.getElementById('modalEventDescription').innerHTML = event.description;
                document.getElementById('modalEventImage').src = event.image;
                document.getElementById('modalEventImage').alt = event.title;
                
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    // Close modal
    document.querySelector('.modal-close').addEventListener('click', function() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Product carousel
    const carouselTrack = document.querySelector('.carousel-track');
    const carouselPrev = document.querySelector('.carousel-prev');
    const carouselNext = document.querySelector('.carousel-next');
    let carouselPosition = 0;
    const cardWidth = 280;
    const cardsVisible = Math.floor(carouselTrack.parentElement.offsetWidth / cardWidth);
    const maxScroll = (carouselTrack.children.length - cardsVisible) * cardWidth;

    carouselNext.addEventListener('click', function() {
        if (carouselPosition < maxScroll) {
            carouselPosition += cardWidth;
            carouselTrack.style.transform = `translateX(-${carouselPosition}px)`;
        }
    });

    carouselPrev.addEventListener('click', function() {
        if (carouselPosition > 0) {
            carouselPosition -= cardWidth;
            carouselTrack.style.transform = `translateX(-${carouselPosition}px)`;
        }
    });
});