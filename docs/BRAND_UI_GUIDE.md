# Casa Paraiso Brand and UI Guide

## Direction

Casa Paraiso should feel warm, tropical, premium, calm, and personally cared for. Guest and customer copy is inviting and restorative. Admin and staff copy is direct, concise, and operational.

The signature visual is the **tropical canopy**: an arched image frame paired with restrained five-leaf linework inspired by the existing logo. Use it for high-emotion marketing moments, not as decoration on every screen.

## Logo

- Use `public/images/casa_paraiso_logo.jpg` without recoloring, cropping, stretching, transparency, gradients, or effects.
- Place the logo on white or Casa Paper surfaces so the supplied white background feels intentional.
- Keep clear space around the artwork equal to the approximate height of its leaf mark.
- Keep the full logo at least 120px wide in digital interfaces.

## Color System

| Token | Hex | Primary use |
| --- | --- | --- |
| Casa Cacao | `#7A3E14` | Brand emphasis, editorial labels, links |
| Casa Palm | `#4F6A4E` | Primary actions and active states |
| Deep Palm | `#334736` | Hover states and dark green contrast |
| Casa Brass | `#B38745` | Restrained accent, dividers, decorative emphasis |
| Brass Light | `#D4AF73` | Small labels and meaningful text on dark surfaces |
| Casa Sand | `#F3EBDD` | Secondary surfaces and grouped controls |
| Casa Paper | `#FFFCF7` | Cards, forms, and logo surfaces |
| Casa Ink | `#232620` | Primary text |
| Casa Background | `#F5F0E7` | Application background |
| Casa Muted | `#67675F` | Secondary text |
| Casa Border | `#DCD2C2` | Decorative separators and low-emphasis card edges |
| Casa Control Border | `#9C8C76` | Input, button, filter, and interactive boundaries |
| Success | `#376B46` | Confirmed and successful states |
| Warning | `#8A5A17` | Partial or attention-required states |
| Danger | `#A23B32` | Cancelled, invalid, destructive, and error states |
| Information | `#285F77` | Neutral operational information |

Casa Brass is decorative and should not carry small text. Use Brass Light for meaningful text on dark surfaces. Success, warning, error, and information states must include text or icons rather than relying on color alone.

## Typography

- **Cormorant Garamond 600–700:** marketing heroes, customer lounge display moments, and short emotional statements.
- **Manrope 400–800:** navigation, operational headings, body text, forms, tables, buttons, metrics, and captions.
- Both families are bundled locally through Fontsource variable WOFF2 assets with `font-display: swap`; no remote font request or production Node runtime is required.
- Body copy is 16px where space permits and never below 14px for meaningful content.
- Uppercase text is limited to short labels with increased letter spacing.

## Components and Layout

- Marketing and customer editorial cards use 24–32px radii and generous breathing room.
- Admin and staff surfaces use 14–16px radii, compact metrics, and information-dense tables.
- Primary controls have a minimum 44px target size.
- Customer appointment and feedback history use responsive cards. Operational record lists remain tables with keyboard-focusable horizontal scrolling.
- Admin and staff use a persistent desktop sidebar and mobile drawer. Customers use a desktop sidebar and mobile bottom navigation for Appointments, Feedback, and Profile.

## Imagery

Use warm editorial treatment details, anonymous hands, natural linens, dark wood, woven cane, ceramic vessels, restrained foliage, and believable window light. Avoid identifiable faces, clinical equipment, excessive candles, glossy stock-photo styling, heavy orange casts, text, and third-party logos.

Project imagery is stored under `public/images/spa/` as responsive WebP variants. Load the landing hero eagerly and all below-the-fold imagery lazily.

## Voice and Tone

| Context | Voice | Example |
| --- | --- | --- |
| Public marketing | Warm and restorative | “Let the day soften here.” |
| Customer workflow | Calm and transparent | “Your time and therapist are reserved as soon as booking succeeds.” |
| Staff operations | Clear and focused | “Review the requested time and treatment.” |
| Admin operations | Concise and decision-oriented | “Review time-sensitive requests first.” |
| Errors | Plain and helpful | “Availability could not be loaded. Try another service or month.” |
| Success | Reassuring and specific | “Appointment confirmed and added to the schedule.” |

Preserve established service names, prices, business hours, statuses, and the line “Reserve your spot. You deserve this.”

## Accessibility and Motion

- Meet WCAG AA: 4.5:1 for normal text and 3:1 for large text and interface boundaries.
- Every control needs a visible label or accessible name and a visible keyboard focus state. Use the Casa Paper inner ring plus Deep Palm outer ring on both light and dark surfaces.
- Keep motion between 160–220ms and use it only to explain navigation or state changes.
- Honor `prefers-reduced-motion` and never make animation necessary to understand content.
