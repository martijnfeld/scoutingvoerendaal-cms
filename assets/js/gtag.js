// Google Analytics (GA4) met Consent Mode v2 en een cookiebanner van
// orestbida/cookieconsent (v3, geladen vanaf jsDelivr vóór dit bestand).
// Staat in een eigen bestand in plaats van inline in de pagina, omdat de
// Content-Security-Policy (zie includes/site_layout_top.php) geen
// inline-scripts toestaat. Het measurement-ID en de bannerteksten komen uit
// de data-attributen van de <script>-tag (zie includes/site_layout_bottom.php).
//
// Werking: gtag laadt altijd, maar met alle toestemmingen op 'denied'. Dan
// plaatst Google Analytics geen cookies en stuurt het alleen anonieme,
// cookieloze pings. Pas als de bezoeker "Accepteren" kiest, gaat
// analytics_storage op 'granted'. Advertentiefuncties blijven altijd uit.
(function(){
  var script = document.currentScript;
  var id = script && script.getAttribute('data-ga-id');
  if(!id) return;

  var texts = {};
  try { texts = JSON.parse(script.getAttribute('data-cc-texts') || '{}') || {}; } catch(e) { texts = {}; }

  window.dataLayer = window.dataLayer || [];
  window.gtag = function(){ window.dataLayer.push(arguments); };
  window.gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    wait_for_update: 500
  });
  window.gtag('js', new Date());
  // Paginacontext (page_type, page_slug) gaat mee met elke gebeurtenis van
  // deze tag; zichtbaar in rapporten na registratie als aangepaste dimensie.
  var config = { allow_google_signals: false, allow_ad_personalization_signals: false };
  try {
    var page = JSON.parse(script.getAttribute('data-ga-page') || '{}') || {};
    for(var key in page){
      if(Object.prototype.hasOwnProperty.call(page, key)) config[key] = page[key];
    }
  } catch(e) {}
  window.gtag('config', id, config);

  // Zonder cookieconsent-bibliotheek (CDN geblokkeerd) blijft alles op
  // 'denied': geen banner, maar ook geen cookies.
  var cc = window.CookieConsent;
  if(!cc || typeof cc.run !== 'function') return;

  function updateConsent(){
    window.gtag('consent', 'update', {
      analytics_storage: cc.acceptedCategory('analytics') ? 'granted' : 'denied'
    });
  }

  function escapeAttr(value){
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  var privacyLink = texts.privacy_link
    ? '<a href="' + escapeAttr(texts.privacy_link) + '">Privacyverklaring</a>'
    : '';

  cc.run({
    // Keuze wordt 182 dagen bewaard in de first-party cookie cc_cookie.
    cookie: {
      name: 'cc_cookie',
      expiresAfterDays: 182,
      sameSite: 'Lax',
      secure: location.protocol === 'https:'
    },
    guiOptions: {
      consentModal: { layout: 'box', position: 'bottom right', equalWeightButtons: true, flipButtons: false },
      preferencesModal: { layout: 'box', equalWeightButtons: true, flipButtons: false }
    },
    categories: {
      necessary: { enabled: true, readOnly: true },
      // Na intrekken: _ga-cookies wissen (ook op het domein zonder www.) en
      // de pagina herladen, zodat gtag ze niet direct opnieuw zet.
      analytics: { autoClear: { cookies: [{ name: /^_ga/ }], reloadPage: true } }
    },
    onConsent: updateConsent,
    onChange: updateConsent,
    language: {
      default: 'nl',
      translations: {
        nl: {
          consentModal: {
            title: texts.title,
            description: texts.text,
            acceptAllBtn: texts.accept,
            acceptNecessaryBtn: texts.reject,
            showPreferencesBtn: texts.settings,
            footer: privacyLink
          },
          preferencesModal: {
            title: 'Cookie-instellingen',
            acceptAllBtn: texts.accept,
            acceptNecessaryBtn: texts.reject,
            savePreferencesBtn: 'Keuze opslaan',
            closeIconLabel: 'Sluiten',
            sections: [
              {
                description: 'Hier kies je welke cookies we mogen gebruiken. Je kunt je keuze altijd wijzigen via "Cookie-instellingen" onderaan de pagina.'
                  + (privacyLink ? ' Meer informatie staat in onze ' + privacyLink.replace('>Privacyverklaring<', '>privacyverklaring<') + '.' : '')
              },
              {
                title: 'Noodzakelijke cookies',
                description: 'Onthouden alleen jouw cookiekeuze (cookie <code>cc_cookie</code>). Deze kun je niet uitzetten.',
                linkedCategory: 'necessary'
              },
              {
                title: 'Analytische cookies (Google Analytics)',
                description: 'Helpen ons te zien hoe de website wordt gebruikt, zodat we hem kunnen verbeteren (cookies <code>_ga</code> en <code>_ga_*</code>). Advertentie- en personalisatiefuncties staan uit.',
                linkedCategory: 'analytics'
              }
            ]
          }
        }
      }
    }
  });
})();
