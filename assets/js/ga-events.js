// Eigen Google Analytics-gebeurtenissen, bovenop wat GA4 zelf al meet
// (paginaweergaven, scrollen, uitgaande links, PDF-downloads). Wordt alleen
// geladen als GA aan staat, na assets/js/gtag.js. Volgt automatisch Consent
// Mode: zonder toestemming gaan alleen cookieloze meldingen naar Google.
//
// - Klikken op elementen met data-ga-event (+ optioneel data-ga-params als
//   JSON), gemaakt met ga_event_attrs() in includes/functions.php.
// - Overige mailto:/tel:-links (ook in CMS-teksten) als contact_click. Het
//   adres of nummer zelf wordt nooit verstuurd.
// - section_view: één keer per pagina als een sectie het midden van het
//   scherm bereikt (Programma, Lid worden, Verhuur, Contact, …).
(function(){
  if(typeof window.gtag !== 'function') return;

  function sectionOf(el){
    var section = el.closest('section[id]');
    return section ? section.id : '';
  }

  document.addEventListener('click', function(e){
    var target = e.target instanceof Element ? e.target : null;
    if(!target) return;

    var tracked = target.closest('[data-ga-event]');
    if(tracked){
      var params = {};
      try { params = JSON.parse(tracked.getAttribute('data-ga-params') || '{}') || {}; } catch(err) { params = {}; }
      window.gtag('event', tracked.getAttribute('data-ga-event'), params);
      return;
    }

    var contact = target.closest('a[href^="mailto:"], a[href^="tel:"]');
    if(contact){
      window.gtag('event', 'contact_click', {
        contact_type: 'overig',
        contact_method: contact.getAttribute('href').indexOf('tel:') === 0 ? 'telefoon' : 'email',
        section: sectionOf(contact)
      });
    }
  });

  if(!('IntersectionObserver' in window)) return;
  var sections = document.querySelectorAll('main section[id]:not(#top)');
  if(!sections.length) return;
  var observer = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if(!entry.isIntersecting) return;
      window.gtag('event', 'section_view', { section: entry.target.id });
      observer.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -50% 0px' });
  sections.forEach(function(section){ observer.observe(section); });
})();
