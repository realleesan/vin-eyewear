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
