<?php

/**
 * config/lang/en.php — English strings for the SITE CHROME.
 *
 * Scope and rules: see the long note at the top of config/lang/vi.php.
 * Short version — this covers header, footer, mega menu and the floating
 * action cluster only. Page content and everything coming from the database
 * (product names, categories, articles, reviews) stays Vietnamese in both
 * languages, and no amount of strings added here will change that.
 *
 * Keys MUST mirror vi.php exactly. A missing key falls back to Vietnamese,
 * then to the key itself — see t() in core/helpers.php.
 */

return [
    'announce'          => 'Free nationwide delivery on orders over 1,000,000₫',

    'nav.home'              => 'Home',
    'nav.products'          => 'Products',
    'nav.tryon'             => 'Virtual try-on',
    'nav.about'             => 'About us',
    'nav.collections'       => 'Collections',
    'nav.contact'           => 'Contact',
    'nav.booking'           => 'Book an eye test',
    'nav.policy'            => 'Policies & FAQ',
    'nav.all_products'      => 'All products',
    'nav.all_collections'   => 'All collections',
    'nav.collections_count' => '%d collections on show',

    'action.search'     => 'Search products',
    'action.account'    => 'My account',
    'action.login'      => 'Sign in',
    'action.cart'       => 'Cart',
    'action.open_menu'  => 'Open navigation menu',
    'action.close_menu' => 'Close menu',
    'action.skip'       => 'Skip navigation, go to main content',

    // Bảng xổ dưới bốn nút tác vụ trên header — xem config/lang/vi.php
    'pop.account'       => 'Account',
    'pop.profile'       => 'Account details',
    'pop.orders'        => 'My orders',
    'pop.bookings'      => 'Eye test bookings',
    'pop.logout'        => 'Sign out',
    'pop.admin'         => 'Admin area',
    'pop.register'      => 'Sign up',
    'pop.cart_empty'    => 'Your cart is empty',
    'pop.cart_count'    => '%d item(s) waiting',
    'pop.cart_view'     => 'View cart',
    'pop.checkout'      => 'Checkout',
    'pop.shop'          => 'Browse products',

    'search.placeholder' => 'Search frames, lenses...',
    'search.submit'      => 'Search',

    'lang.label'        => 'Language',
    'lang.vi'           => 'Tiếng Việt',
    'lang.en'           => 'English',

    'footer.blurb'      => 'Designer frames and genuine lenses. '
                         . 'Free eye tests at all our stores.',
    'footer.products'   => 'Products',
    'footer.about'      => 'About Vin Eyewear',
    'footer.contact'    => 'Contact',
    'footer.hotline'    => 'Hotline:',
    'footer.exam'       => 'Book an eye test',
    'footer.warranty'   => 'Warranty & returns',
    'footer.stores'     => 'Store locations',
    'footer.privacy'    => 'Privacy policy',
    'footer.terms'      => 'Terms of service',

    'fab.call'          => 'Call %s',
    'fab.zalo'          => 'Message on Zalo',
    'fab.messenger'     => 'Chat on Messenger',
    'fab.top'           => 'Back to top',
    'fab.open'          => 'Open support channels',
];
