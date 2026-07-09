# Design System Specification

## 1. Overview & Creative North Star: "The Digital Concierge"

This design system is built upon the North Star of **"The Digital Concierge."** We are moving away from the cluttered, "big-box" retail aesthetic and moving toward a high-end, editorial experience. The goal is to make enterprise-level electronics feel approachable yet authoritative. 

We break the standard e-commerce "template" look through **Intentional Asymmetry** and **Tonal Depth**. Instead of rigid grids, we use generous breathing room and overlapping elements to create a sense of bespoke craftsmanship. The interface shouldn't feel like a website; it should feel like a physical showroom where products are curated, not just listed.

---

## 2. Color & Surface Philosophy

The color palette is anchored in `primary` (#001E40) and `primary_container` (#003366). These colors provide the "Corporate Trust" foundation, while our surface tiers provide the "Modern Tech" atmosphere.

### The "No-Line" Rule
**Explicit Instruction:** Traditional 1px solid borders for sectioning are strictly prohibited. Boundaries must be defined solely through background color shifts or subtle tonal transitions.
- Use `surface_container_low` (#F4F3F3) for the main body.
- Use `surface_container_lowest` (#FFFFFF) for interactive cards.
- This creates a "soft edge" look that feels premium and integrated.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers. We use a "Nesting" approach to define importance:
1.  **Base Layer:** `surface` (#F9F9F9) – The canvas.
2.  **Section Layer:** `surface_container` (#EEEEEE) – Used to group large content areas.
3.  **Interaction Layer:** `surface_container_lowest` (#FFFFFF) – Used for primary cards and input fields to make them "pop" against the section layer.

### The "Glass & Gradient" Rule
To elevate the tech aesthetic, utilize **Glassmorphism** for navigation bars and floating action elements.
- **Glass Token:** `surface_container_lowest` at 70% opacity with a 20px `backdrop-blur`.
- **Signature Textures:** Apply a subtle linear gradient from `primary_container` (#003366) to `primary` (#001E40) on high-value CTAs. This adds a "soul" to the brand that flat hex codes cannot achieve.

---

## 3. Typography: Editorial Authority

We use a dual-font strategy to balance corporate professionalism with modern tech-savviness.

| Level | Token | Font Family | Size | Weight | Character |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Display** | `display-lg` | Inter | 3.5rem | 700 | Commanding, Editorial |
| **Headline**| `headline-md` | Inter | 1.75rem | 600 | Informative, Trustworthy |
| **Title** | `title-lg` | Manrope | 1.375rem| 500 | Human, Approachable |
| **Body** | `body-md` | Manrope | 0.875rem| 400 | Highly Readable |
| **Label** | `label-md` | Inter | 0.75rem | 500 | Technical, Precise |

**The Typography Strategy:** Use `Inter` for high-impact brand moments and `Manrope` for product descriptions and technical specs. The wider apertures of Manrope ensure that even dense technical data feels airy and easy to digest.

---

## 4. Elevation & Depth: Tonal Layering

Depth in this system is achieved through light and color, not heavy shadows.

*   **The Layering Principle:** Place a `surface_container_lowest` card on a `surface_container_low` section. The contrast in brightness creates a natural lift.
*   **Ambient Shadows:** When a float is required (e.g., a hover state), use a shadow with a 32px blur at 4% opacity. The shadow color should use a tint of `on_surface` (#1A1C1C) rather than pure black to keep it organic.
*   **The "Ghost Border":** If a separator is required for accessibility, use the `outline_variant` token at 15% opacity. Never use 100% opaque lines.
*   **Corner Radii:** Use `xl` (1.5rem / 24px) for major containers and `lg` (1rem / 16px) for inner components like buttons or small cards.

---

## 5. Components

### Buttons
*   **Primary:** Background: `primary_container` gradient. Text: `on_primary`. Radius: `full`.
*   **Secondary:** Background: `surface_container_highest`. Text: `on_surface`.
*   **State:** On hover, primary buttons should increase in tonal brightness by 5%, never change hue.

### Input Fields
*   **Style:** `surface_container_lowest` background with a `ghost border`.
*   **Active State:** Transition the border to `primary_container` at 40% opacity. Forbid the "heavy blue box" look.

### Cards & Lists
*   **Rule:** Forbid divider lines.
*   **Implementation:** Separate list items using 12px of vertical white space and a subtle `surface_variant` background on hover to indicate interactivity.

### Product Hero (Signature Component)
Use "Asymmetric Overlap." The product image should bleed out of its `surface_container` and slightly overlap the `headline-lg` text. This breaks the "box" feel and mimics high-end fashion magazine layouts.

---

## 6. Do’s and Don'ts

### Do
*   **DO** use whitespace as a functional tool. If a section feels crowded, increase padding rather than adding a border.
*   **DO** use `surface_bright` to highlight featured enterprise products.
*   **DO** ensure all glassmorphic elements have a high enough contrast ratio for accessibility.

### Don't
*   **DON'T** use pure black (#000000) for text. Use `on_surface` (#1A1C1C) to maintain a sophisticated, soft-contrast look.
*   **DON'T** use standard 4px or 8px corners. Only use the defined Scale (`lg` to `xl`) to maintain the "Modern Tech" friendliness.
*   **DON'T** use "Drop Shadows" that are visible as distinct shapes. Shadows must feel like ambient light.