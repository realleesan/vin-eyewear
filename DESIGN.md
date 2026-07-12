---
name: Lower East Heritage
colors:
  surface: '#fff8f1'
  surface-dim: '#e3d9c7'
  surface-bright: '#fff8f1'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#fdf2e0'
  surface-container: '#f7eddb'
  surface-container-high: '#f1e7d5'
  surface-container-highest: '#ebe1d0'
  on-surface: '#1f1b10'
  on-surface-variant: '#4e4632'
  inverse-surface: '#353024'
  inverse-on-surface: '#faf0dd'
  outline: '#80765f'
  outline-variant: '#d2c5ab'
  surface-tint: '#745b00'
  primary: '#745b00'
  on-primary: '#ffffff'
  primary-container: '#ffcc00'
  on-primary-container: '#6f5700'
  inverse-primary: '#f1c100'
  secondary: '#5e5e5e'
  on-secondary: '#ffffff'
  secondary-container: '#e2e2e2'
  on-secondary-container: '#646464'
  tertiary: '#5e5f5c'
  on-tertiary: '#ffffff'
  tertiary-container: '#d3d2ce'
  on-tertiary-container: '#595a57'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffe08b'
  primary-fixed-dim: '#f1c100'
  on-primary-fixed: '#241a00'
  on-primary-fixed-variant: '#584400'
  secondary-fixed: '#e2e2e2'
  secondary-fixed-dim: '#c6c6c6'
  on-secondary-fixed: '#1b1b1b'
  on-secondary-fixed-variant: '#474747'
  tertiary-fixed: '#e3e2df'
  tertiary-fixed-dim: '#c7c7c3'
  on-tertiary-fixed: '#1b1c1a'
  on-tertiary-fixed-variant: '#464744'
  background: '#fff8f1'
  on-background: '#1f1b10'
  surface-variant: '#ebe1d0'
  heritage-yellow: '#FFCC00'
  ink-black: '#000000'
  paper-white: '#FFFFFF'
  canvas-gray: '#F6F5F1'
typography:
  headline-xl:
    fontFamily: Libre Caslon Text
    fontSize: 64px
    fontWeight: '700'
    lineHeight: 72px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Libre Caslon Text
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
  headline-lg-mobile:
    fontFamily: Libre Caslon Text
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  subheading-caps:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '800'
    lineHeight: 20px
    letterSpacing: 0.1em
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-mono:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.05em
spacing:
  unit: 8px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 64px
  section-gap: 120px
---

## Brand & Style

This design system embodies the "Heritage-meets-Downtown-Cool" essence of a multi-generational New York institution. It balances the precision of an optical laboratory with the grit and soul of the Lower East Side. The visual language is authoritative yet soulful, treating eyewear not as a mere medical device, but as a piece of cultural history.

The design style is a blend of **High-Contrast Bold** and **Modern Retro**. It utilizes a strict, newsprint-inspired color palette punctuated by a vibrant signature yellow. The interface feels "physical"—reminiscent of printed catalogs, vintage shop signage, and acetate frame textures. The user should feel like they are stepping into a neighborhood shop that happens to be a global fashion authority.

**Key Brand Pillars:**
- **Authentic New York Heritage:** Leveraging heavy serif typography and archival photography styles.
- **Craft-Focused:** Emphasizing detail through high-resolution product photography and "Custom Made Tints™" color-focused discovery.
- **No-Nonsense Precision:** Using sharp edges, clear grids, and condensed sans-serifs for functional UI.

## Colors

The palette is anchored by the iconic **Heritage Yellow (#FFCC00)**, used strategically for primary calls-to-action and brand accents. This is set against a high-contrast foundation of **Ink Black** and **Paper White**.

- **Primary (Heritage Yellow):** Used for buttons, highlight banners, and key navigational indicators. It represents the "Optical Shop" signage and the energy of NYC.
- **Secondary (Ink Black):** Used for typography, borders, and dark-mode containers. It provides the "bold and classic" structure.
- **Neutral (Canvas Gray/White):** `F6F5F1` acts as a warmer, archival-quality alternative to pure white for page backgrounds, softening the digital glare and evoking aged paper or premium packaging.
- **Functional Tints:** While the UI is monochromatic, the product discovery experience should vibrantly showcase the "Custom Made Tints™" range (e.g., Purple Nurple, Bel Air Blue) as the primary source of chromatic variety.

## Typography

The typography system creates a "Editorial meets Laboratory" hierarchy.

- **Headlines:** Use **Libre Caslon Text** (or a similar heritage serif). Large, bold, and authoritative. Headlines should often be presented in high-contrast black on a white or yellow background.
- **Body:** Use **Hanken Grotesk**. This provides a clean, modern contrast to the serif headings, ensuring readability for product descriptions and FAQs.
- **Data/Utility:** Use **JetBrains Mono** for technical specs, prices, and "optical" metadata. The monospaced nature evokes the technical precision of an optometrist's prescription or a vintage typewriter.
- **Navigation:** Navigation items and section headers should use All-Caps **Hanken Grotesk** with generous letter spacing to maintain a structured, professional feel.

## Layout & Spacing

The layout philosophy follows a **Fixed Grid** approach for content containers, set within a fluid canvas. 

- **Grid:** Use a 12-column grid for desktop with 24px gutters. Product listings typically span 3 or 4 columns (4 or 3 items per row).
- **Rhythm:** Spacing is generous. Large vertical gaps (120px+) between major sections (Stories vs. Shop) create a premium, editorial feel that prevents the site from looking like a cluttered discount retailer.
- **Margins:** Desktop views should maintain wide "safe areas" (64px+) to frame the content, while mobile scales down to 16px margins to maximize product imagery.
- **Reflow:** On mobile, product grids collapse to 1 or 2 columns. Imagery remains hero-centric.

## Elevation & Depth

This design system avoids soft shadows and "morphic" effects in favor of **Flat Tonal Layers** and **Bold Borders**.

- **Hierarchy through Contrast:** Depth is created by layering different background colors (e.g., a Black footer against a Paper White body) rather than drop shadows.
- **Outlines:** UI elements like cards and input fields should use a 1px or 2px solid black border. This reinforces the "Optical Shop" and "Print Catalog" aesthetic.
- **Overlays:** When modals or "Quick Shop" drawers are used, utilize a high-opacity (90%) white or black wash to maintain the high-contrast look, avoiding soft blurs.
- **Product Depth:** Depth should come from the photography itself—using crisp, high-quality images of frames with natural shadows, rather than CSS shadows on the container.

## Shapes

The shape language is strictly **Sharp (0)**. 

To mirror the precision of frame manufacturing and the architectural lines of New York City, all buttons, input fields, and containers must have 0px corner radii. This creates a bold, uncompromising look that differentiates the system from the "friendly and rounded" aesthetic of modern tech startups.

Exceptions are made only for specific iconography representing the eyewear itself (e.g., circular icons for round frames), where the geometry must be literal.

## Components

- **Buttons:** Primary buttons are sharp rectangles with a `Heritage Yellow` background and `Ink Black` text. Secondary buttons are "Inverted" (Black background, White text) or "Ghosted" (Black 2px border, no fill). All-caps text is mandatory for buttons.
- **Product Cards:** Cards have no shadows and 0px borders. They rely on generous white space and bold, all-caps product names (e.g., "THE LEMTOSH").
- **Custom Made Tints™ Picker:** A signature component. Use circular swatches for lens colors, arranged in a clean grid with the color name appearing in `label-mono` on hover.
- **Inputs:** Text fields should be simple 2px bottom-borders or full 1px black rectangles with no rounding. Labels use the `label-mono` style.
- **Chips/Badges:** Use "Heritage Yellow" for status badges like "New" or "Limited Edition," placed in the top-left corner of product images.
- **Navigation:** The top-level nav should be clean, high-contrast black text on white, with a subtle "under-line" or color-shift to Yellow on hover.