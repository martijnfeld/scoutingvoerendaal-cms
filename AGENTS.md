# Project instructions

Before analysing or changing this project, read `CLAUDE.md` completely. It is
the authoritative technical map and contains the deployment constraints,
architecture, security rules, migration/release process, and conventions used
by the existing code.

Key constraints: this is plain PHP + MySQL for shared hosting (PHP 7.4+), with
no Composer, npm, framework, build step, or server-side shell dependency.
Keep user-facing copy, comments, and CMS UI in Dutch. For database changes,
update both `sql/install.sql` and a new ordered migration in
`sql/migrations/`.

## Frontendstandaarden

### Styling

- Bootstrap 5 is de standaardbasis voor nieuwe UI- en stylingwerkzaamheden.
- Gebruik bestaande Bootstrap-componenten en -utilities vóór nieuwe custom CSS: layout, grid/flex,
  spacing, formulieren, knoppen, alerts, badges, cards, modals, navigatie en dropdowns.
- Custom CSS is voor projectspecifieke vormgeving en uitzonderingen; refactor bestaande legacy-styling
  niet uitsluitend om Bootstrap te gebruiken, tenzij de taak dat expliciet vraagt.
- Voeg geen nieuw CSS-framework toe zonder expliciete opdracht.
- Gebruik de lokale Bootstrap-versie in `assets/css/bootstrap/` en `assets/js/bootstrap/`.

### Iconen

- Tabler Icons is de standaardiconlibrary; gebruik uitsluitend de lokale SVG's in
  `assets/svg/tabler-icons/icons/`.
- Controleer Tabler Icons voordat je een eigen SVG maakt. Alleen wanneer aantoonbaar geen geschikt
  Tabler-icoon bestaat, is een projectspecifiek custom SVG toegestaan.
- Gebruik Tabler Icons als eerste keuze voor nieuwe iconen.
- Gebruik geen emoji als vervanging voor normale UI-iconen.

### Dependencies

Dit project moet eenvoudig lokaal en op shared hosting werken. Voeg voor frontend-assets niet zonder
expliciete opdracht npm, yarn, pnpm, Composer-pakketten of een verplichte buildpipeline toe. Lokale
frontendlibraries moeten direct vanuit de repository bruikbaar zijn.
