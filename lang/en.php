<?php

/**
 * lang/en.php — English interface labels.
 *
 * Translated FROM lang/vi.php, which is the source of truth: when the two
 * disagree, the Vietnamese file is right.
 *
 * Only chrome is translated — navigation, buttons, headings, status lines.
 * Product names, category names, collection names, store names and policy
 * copy all live in the database in Vietnamese only, so an English page still
 * shows Vietnamese product names. That is the correct state for the data we
 * have, not a missing translation. See the header of core/i18n.php.
 *
 * Missing a key here is not fatal: t() falls back to the Vietnamese string
 * before it gives up and prints the raw key.
 */

return [

    // ── Accessibility and page chrome ────────────────────────────────────
    'a11y.skip'         => 'Skip navigation, go to main content',
    'announce.shipping' => 'Free nationwide delivery on orders over 1,000,000₫',
    'lang.label'        => 'Language',

    // ── Navigation ───────────────────────────────────────────────────────
    'nav.aria.main'    => 'Main navigation',
    'nav.home'         => 'Home',
    'nav.products'     => 'Eyewear',
    'nav.ar'           => 'Virtual try-on',
    'nav.about'        => 'About us',
    'nav.collections'  => 'Collections',
    'nav.contact'      => 'Contact',
    'nav.booking'      => 'Book an eye test',
    'nav.policy'       => 'Policies & FAQ',

    'mega.view'        => '· View now →',
    'mega.all_collections' => 'All collections',
    'mega.collections_count' => ':n collections on show',
    'mega.all_products' => 'All eyewear',

    'menu.open'        => 'Open navigation menu',
    'menu.close'       => 'Close menu',
    'menu.aria'        => 'Navigation menu',

    // ── Search ───────────────────────────────────────────────────────────
    'search.aria'        => 'Search products',
    'search.title'       => 'Search products',
    'search.placeholder' => 'Search frames, lenses…',
    'search.submit'      => 'Search',

    // ── Account ──────────────────────────────────────────────────────────
    'account.title'        => 'Account',
    'account.mine'         => 'My account',
    'account.login'        => 'Sign in',
    'account.register'     => 'Create account',
    'account.info'         => 'Account details',
    'account.orders'       => 'My orders',
    'account.appointments' => 'Eye test appointments',
    'account.logout'       => 'Sign out',

    // ── Cart ─────────────────────────────────────────────────────────────
    'cart.title'  => 'Cart',
    'cart.aria'   => 'Cart, :n',
    'cart.empty'  => 'Your cart is empty',
    'cart.browse' => 'Browse eyewear',
    'cart.recent' => 'Just added',
    'cart.more'   => ':n more in your cart',
    'cart.view'   => 'View cart',

    // ── Home page ────────────────────────────────────────────────────────
    'home.see_all'        => 'View all →',
    'home.strip.prev'     => 'Previous products',
    'home.strip.next'     => 'Next products',

    'home.hero.eyebrow'   => '2026 collection',
    'home.hero.title_1'   => 'See clearly,',
    'home.hero.title_2'   => 'wear it daily.',
    'home.hero.lead'      => 'Authentic titanium and acetate frames, with a free eye test by a qualified optician before you decide.',
    'home.hero.cta_shop'  => 'Explore the collection',
    'home.hero.cta_book'  => 'Book a free eye test',
    'home.hero.ar'        => 'Or try frames on with your camera (AR) →',
    'home.hero.prev'      => 'Previous image',
    'home.hero.next'      => 'Next image',
    'home.hero.alt1'      => 'A customer trying on frames at Vin Eyewear',
    'home.hero.cap1'      => 'Clinical-grade eye tests · Hanoi',
    'home.hero.alt2'      => 'Sunglasses display at the store',
    'home.hero.cap2'      => 'Sunglasses 2026 · Polarised UV400',
    'home.hero.alt3'      => 'Ultra-light titanium frames, just in',
    'home.hero.cap3'      => 'Titanium frames, 9 grams · Just in',

    'home.trust.exam'     => 'Free eye test',
    'home.trust.returns'  => '7-day returns',
    'home.trust.warranty' => '24-month warranty',
    'home.trust.shipping' => 'Fast nationwide delivery',

    'home.new.eyebrow'    => 'Just in',
    'home.new.title'      => 'New arrivals',
    'home.best.eyebrow'   => 'Most loved',
    'home.best.title'     => 'Bestsellers',

    'home.coll.eyebrow'   => 'Curated by theme',
    'home.coll.title'     => 'New collections',
    'home.coll.explore'   => '· Explore →',
    'home.coll.all'       => 'All collections →',

    'home.cat.eyebrow'    => 'Browse',
    'home.cat.title'      => 'Categories',
    'home.cat.prev'       => 'Previous categories',
    'home.cat.next'       => 'Next categories',
    'home.cat.count'      => ':n styles',
    'home.cat.soon'       => 'Coming soon',
    'home.cat.view'       => 'View category',
    'home.cat.all'        => 'All eyewear →',

    'home.lens.eyebrow'   => 'Genuine lenses',
    'home.lens.title'     => 'Glazed the same day, genuine lenses',
    'home.lens.alt'       => 'The glazing bench at Vin Eyewear',
    'home.lens.f1'        => 'Free eye test',
    'home.lens.f2'        => 'Ready in 60–90 minutes',
    'home.lens.f4'        => '90-day warranty',
    'home.lens.packages'  => 'Popular lens packages',
    'home.lens.from'      => 'From',
    'home.lens.ask'       => 'On request',
    'home.lens.cta'       => 'Get lens advice',

    'home.exam.eyebrow'   => 'In-store service',
    'home.exam.title'     => 'Clinical-grade eye tests, free of charge',
    'home.exam.alt'       => 'Inside a Vin Eyewear store',
    'home.exam.s1'        => 'Check in',
    'home.exam.s1n'       => '2 minutes',
    'home.exam.s2'        => 'Auto-refraction',
    'home.exam.s2n'       => 'Clinical-grade equipment',
    'home.exam.s3'        => 'Trial lenses',
    'home.exam.s3n'       => 'Adjusted to your real prescription',
    'home.exam.s4'        => 'Lens advice',
    'home.exam.s4n'       => 'Based on your script and daily use',
    'home.exam.s5'        => 'Glazing and fitting',
    'home.exam.s5n'       => '60–90 minutes',

    'home.rev.eyebrow'    => 'Reviews',
    'home.rev.title'      => 'What our customers say',
    'home.rev.prev'       => 'Previous reviews',
    'home.rev.next'       => 'Next reviews',

    // ── Product card (used on 5 pages) ───────────────────────────────────
    'product.out_of_stock' => 'Out of stock',
    'product.badge_new'    => 'New',
    'product.badge_hot'    => 'Bestseller',
    'product.no_image'     => 'No image yet',
    'product.price_label'  => 'Price',
    'product.was_label'    => 'Was',
    'product.buy_now'      => 'Buy now',
    'product.add_to_cart'  => 'Add to cart',
    'product.choose_option' => 'Choose options',
    'product.details'      => 'Details',

    // ── Shared calls to action ───────────────────────────────────────────
    'cta.book' => 'Book an eye test',
    'cta.call' => 'Call :phone',

    // ── Footer ───────────────────────────────────────────────────────────
    'footer.blurb'      => 'Authentic frames and lenses. Free eye tests at every store.',
    'footer.statement'  => 'Eyewear made to be worn every day, not kept in a case.',
    'footer.products'   => 'Eyewear',
    'footer.about'      => 'About Vin Eyewear',
    'footer.contact'    => 'Contact',
    'footer.hotline'    => 'Hotline',
    'footer.stores'     => 'Our stores',
    'footer.email'      => 'Email',
    'footer.cta'        => 'Get in touch',
    'footer.legal_nav'  => 'Legal links',
    'footer.privacy'    => 'Privacy policy',
    'footer.terms'      => 'Terms',
    'footer.tax'        => 'Tax ID',

    'services.about'    => 'About us',
    'services.booking'  => 'Book an eye test',
    'services.ar'       => 'Virtual try-on',
    'services.warranty' => 'Warranty & returns',
    'services.policy'   => 'Policies & FAQ',
];
