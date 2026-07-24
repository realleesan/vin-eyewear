// Category Page - Filter Functionality
// Note: Mobile slide-over is handled by filter-sidebar.js component
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality for category page
    const filterChips = document.querySelectorAll('[data-filter-category]');
    const kindChips = document.querySelectorAll('[data-filter-kind]');
    const priceChips = document.querySelectorAll('[data-filter-price]');
    const productCards = document.querySelectorAll('.product-card');

    // Category filter
    filterChips.forEach(chip => {
        chip.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-filter-category');

            // Update active state
            filterChips.forEach(c => {
                c.classList.remove('is-active');
                c.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('is-active');
            this.setAttribute('aria-pressed', 'true');

            // Filter product cards (placeholder for future filtering logic)
            filterProducts();
        });
    });

    // Kind filter
    kindChips.forEach(chip => {
        chip.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-filter-kind');

            // Update active state
            kindChips.forEach(c => {
                c.classList.remove('is-active');
                c.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('is-active');
            this.setAttribute('aria-pressed', 'true');

            filterProducts();
        });
    });

    // Price filter
    priceChips.forEach(chip => {
        chip.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-filter-price');

            // Update active state
            priceChips.forEach(c => {
                c.classList.remove('is-active');
                c.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('is-active');
            this.setAttribute('aria-pressed', 'true');

            filterProducts();
        });
    });

    function filterProducts() {
        // Placeholder for filtering logic
        // This will be implemented when product data is connected
        console.log('Filter products - placeholder for future implementation');
    }
});
