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
- Gebruik geen Bootstrap-CDN: uitsluitend de lokale versie in `assets/css/bootstrap/` en
  `assets/js/bootstrap/`.

### Iconen

- Tabler Icons is de standaardiconlibrary; gebruik uitsluitend de lokale SVG's in
  `assets/svg/tabler-icons/icons/`.
- Controleer Tabler Icons voordat je een eigen SVG maakt. Alleen wanneer aantoonbaar geen geschikt
  Tabler-icoon bestaat, is een projectspecifiek custom SVG toegestaan.
- Voeg zonder expliciete opdracht geen Font Awesome, Material Icons, Lucide, Heroicons of andere
  iconlibrary toe en gebruik geen externe icon-CDN's of SVG-URL's.
- Gebruik geen emoji als vervanging voor normale UI-iconen.
- Bestaand Font Awesome-gebruik is technische schuld en valt buiten een gewone featurewijziging:
  voeg geen nieuw gebruik toe en migreer het alleen wanneer de taak dat vraagt.

### Dependencies

Dit project moet eenvoudig lokaal en op shared hosting werken. Voeg voor frontend-assets niet zonder
expliciete opdracht npm, yarn, pnpm, Composer-pakketten, CDN-afhankelijkheden of een verplichte
buildpipeline toe. Lokale frontendlibraries moeten direct vanuit de repository bruikbaar zijn.
