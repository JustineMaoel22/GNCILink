# GNCI Website — Design System

## Primary rule: consistency over redesign
The task is usually to make existing pages visually consistent, **not** to redesign pages without permission. Inspect current site components and CSS before selecting tokens. Reuse the established GNCI branding, fonts, colors, imagery, and navigation. Do not assume the example token values below are the current brand standards.

## Design principles
- Modern, clean, minimalist, professional academic identity.
- Consistent hierarchy, spacing, alignment, navigation, button states, and responsive behavior.
- Use visual interest strategically, without overwhelming animation or excessive text.
- Maintain consistency across public pages, program and department details, athletics, campus events, admissions, student organizations, PDFs, and admin/editor UIs where relevant.
- Treat the existing approved component as the visual source of truth unless instructed otherwise.

## Token strategy
Locate existing CSS variables/tokens first. Add missing semantic aliases centrally, not page-by-page duplicate values.

```css
/* Illustrative fallback values ONLY; replace with verified project values. */
:root {
  --color-brand-primary: #176b42;
  --color-text: #17231e;
  --color-text-muted: #64716a;
  --color-surface: #ffffff;
  --color-surface-subtle: #f5f7f5;
  --color-border: #dce4dd;
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 20px;
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-6: 24px;
  --space-8: 32px;
  --space-12: 48px;
  --container-max: 1200px;
  --transition-fast: 160ms ease;
}
```

## Components
- **Navbar:** same placement, dropdown logic, type scale, hover/focus behavior, and breakpoint handling across pages; preserve dynamic categories and links.
- **Page header/hero:** common breadcrumb, heading hierarchy, intro width, and spacing; vary content, not the design language.
- **Cards:** consistent padding, radius, border/shadow, image ratios, and action placement.
- **Buttons/links:** clear primary/secondary styles and visible hover, focus, disabled, and loading states.
- **Forms/editor:** clear labels, inline validation, error summaries, publish warnings, and predictable confirmation dialogs.
- **Modal/PDF viewer:** accessible close control, Escape support, keyboard focus management, and responsive content.
- **Empty states:** only where content absence warrants a message. Hide optional public-facing sections with no published content.

## Typography and layout
- Reuse existing font families and weights. Set a consistent heading/body scale, line-height, and maximum reading width.
- Use a mobile-first approach, fluid containers, and reusable grid spacing.
- Avoid arbitrary `px` tweaks per page when a shared token/component can fix the issue.
- Ensure no horizontal overflow at common mobile, tablet, and desktop widths.

## Motion and interaction
- Prefer subtle entrance, hover, and state transitions; avoid constant distracting motion.
- Animate `transform` and `opacity` where possible.
- Honor `prefers-reduced-motion: reduce`.
- Do not add animation merely to make the interface look 'modern'.

## Accessibility
- Preserve semantic heading order and keyboard-operable controls.
- Provide visible keyboard focus indicators; do not use color alone for status.
- Aim for WCAG AA text contrast (typically 4.5:1 normal text, 3:1 large text) and adequately sized targets.
- Provide useful alt text for meaningful images; decorative imagery may use empty alt text.

## Special GNCI patterns
- Program and department detail pages must share layout/template rules, including dynamically added records.
- Graduate School must maintain the expected navbar position without relying on static item counts.
- Athletics can distinguish Green Mamba (Regular/College) and Falcons (Montessori) within the same system of colors, cards, spacing, and headings; preserve verified branding assets.
- A PDF tuition/fees or curriculum view must work on mobile, offer an accessible open/download fallback, and avoid displaying empty curriculum sections.
- Image repositioning/crop UI should communicate drag-to-adjust behavior and save the chosen focal point when the existing image pipeline supports it.

## Pre-delivery visual QA
Compare the changed page with 2–3 established GNCI pages. Check nav, typography, colors, spacing, components, interaction states, mobile layouts, and reduced-motion behavior. Report what you checked and any deviations. Never claim a visual comparison without actually viewing the files/pages/screenshots.
