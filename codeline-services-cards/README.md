# CodeLine Service Cards

Korte beschrijving

Codeline Service Cards voegt een eenvoudige manier toe om "service cards" te beheren en weer te geven op je site. De plugin registreert een custom post type voor service pages en biedt een dedicated admin-pagina waar je kaart-afbeelding, titel, beschrijving en de gekoppelde service page kunt bekijken en snel inline kunt bewerken.

**Installatie**
- Plaats de pluginmap in `wp-content/plugins/codeline-services-cards`.
- Activeer via het WordPress Plugins scherm.
- Ga naar het beheerpaneel: Service Page → Service Cards

**Admin: Service Cards**
- Locatie: `Service Page` → `Service Cards` (menu onder `Service Page`).
- Klik op "Bewerken" bij een rij om inline de afbeelding, titel en beschrijving te wijzigen.
- Afbeeldingen worden gekozen met de WordPress Media Picker (knop: "Kies afbeelding").
- Na bewaren verschijnt een compacte bevestiging (kleine toast) rechtsboven.

Bestanden en assets
- Admin JavaScript: `assets/admin-cards.js`
- Admin CSS: `assets/admin-cards-styles.css`
- Shortcodes en front-end templates zijn opgenomen in de hoofdpluginfile `codeline-services-cards.php`.

Shortcodes
- `[codeline_services_cards]`
  - Toont de lijst met service cards (meestal gebruikt in templates of pagina's).
  - Geen extra attributen standaard.
- `[codeline_services_page]`
  - Shortcode voor de volledige service page weergave (indien aanwezig in de plugin).

Belangrijke meta keys
- `_cl_service_image_id` — attachment ID van de kaart-afbeelding.
- `_cl_service_card_title` — optionele titel die op de kaart getoond wordt.
- `_cl_service_card_desc` — optionele korte beschrijving voor de kaart.
- `_cl_service_visible` — (optioneel) zichtbaarheid-metakey.
- `_cl_service_card_order` — volgorde/rangschikking van kaarten.

AJAX en hooks
- AJAX endpoint voor inline opslaan: `wp_ajax_clsc_update_card`
- Script-localisatie: `CLSCAdmin` object met `ajax_url` en `nonce` (nonce key: `clsc_card_update`).

Styling en thumbnails
- Admin thumbnails in de tabel worden geforceerd tot `80x80` via `.clsc-thumb` en `object-fit: cover`.
- Edit-preview gebruikt `.clsc-preview-img` voor compacte weergave.

Problemen en troubleshooting
- Media picker werkt niet: controleer dat `wp_enqueue_media()` wordt aangeroepen en dat `assets/admin-cards.js` wordt ingeladen op de admin-pagina `page=clsc-cards`.
- AJAX save faalt: open DevTools Console en Network, check admin-ajax verzoek en nonce. Je kunt server-side snel syntax-check doen met:

```powershell
php -l "wp-content/plugins/codeline-services-cards/codeline-services-cards.php"
```

- Als thumbnails te groot blijven: clear browser cache en controleer dat `assets/admin-cards-styles.css` geladen wordt.

Aanpassen
- Wil je dat de notificaties of thumbnail-grootte anders zijn? Pas `assets/admin-cards-styles.css` en `assets/admin-cards.js` aan.
- Dynamische CSS-variabelen voor front-end blijven inline in de service page output (bewust zo om instellingen snel toe te passen).

Support
- Heb je logs of console-fouten? Plak ze hier en ik help snel met debugging.

---
Versie: aangepaste lokale versie

