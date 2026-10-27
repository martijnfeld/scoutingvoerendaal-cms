(function(){
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainNav');
  var submenuToggles = Array.prototype.slice.call(nav.querySelectorAll('.header-submenu-toggle'));
  function setSubmenu(button, open) {
    button.setAttribute('aria-expanded', String(open));
    document.getElementById(button.getAttribute('aria-controls')).hidden = !open;
  }
  function closeSubmenus(except) {
    submenuToggles.forEach(function(button) {
      if (button !== except) setSubmenu(button, false);
    });
  }
  function closeNavigation() {
    nav.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
    closeSubmenus();
  }
  toggle.addEventListener('click', function() {
    var open = !nav.classList.contains('open');
    nav.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (!open) closeSubmenus();
  });
  submenuToggles.forEach(function(button) {
    button.addEventListener('click', function() {
      var open = button.getAttribute('aria-expanded') !== 'true';
      closeSubmenus(button);
      setSubmenu(button, open);
    });
    button.addEventListener('keydown', function(event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        closeSubmenus(button);
        setSubmenu(button, true);
        document.getElementById(button.getAttribute('aria-controls')).querySelector('a').focus();
      }
    });
  });
  nav.addEventListener('click', function(event) {
    if (event.target.closest('a')) closeNavigation();
  });
  document.addEventListener('click', function(event) {
    if (!nav.contains(event.target) && !toggle.contains(event.target)) closeNavigation();
  });
  nav.addEventListener('focusout', function(event) {
    if (!nav.contains(event.relatedTarget)) closeSubmenus();
  });
  document.addEventListener('keydown', function(event) {
    if (event.key !== 'Escape') return;
    var opened = submenuToggles.find(function(button) { return button.getAttribute('aria-expanded') === 'true'; });
    if (opened) {
      closeSubmenus();
      opened.focus();
    } else if (nav.classList.contains('open')) {
      closeNavigation();
      toggle.focus();
    }
  });

  function escapeHtml(s){
    return String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function renderFeed(container, items){
    container.innerHTML = '';
    if(!items || items.length === 0){
      container.innerHTML = '<p class="prog-empty">Er staat op dit moment niets gepland.</p>';
      return;
    }
    items.forEach(function(item){
      var d = new Date(item.date);
      var dateStr = escapeHtml(d.toLocaleDateString('nl-NL', {weekday:'short', year:'numeric', month:'short', day:'numeric'}));
      var img = item.image_url ? '<div class="prog-img"><img loading="lazy" width="150" height="150" src="'+escapeHtml(item.image_url)+'" alt="'+escapeHtml(item.name || '')+'"></div>' : '';
      var desc = item.omschrijving || '';
      var html =
        '<div class="prog-item">'+
          '<div class="prog-side">'+
            '<div class="prog-date">'+dateStr+'</div>'+
            img+
          '</div>'+
          '<div class="prog-body">'+
            '<div class="prog-title">'+escapeHtml(item.name || '')+'</div>'+
            '<div class="prog-desc">'+desc+'</div>'+
          '</div>'+
        '</div>';
      container.insertAdjacentHTML('beforeend', html);
    });
  }

  function showFeedError(container){
    container.innerHTML = '<p class="prog-error">Programma kon niet worden geladen. Probeer het later opnieuw, kijk in de Scouting-app of neem contact op.</p>';
  }

  function loadAllFeeds(){
    var containers = document.querySelectorAll('.prog-list[data-feed-slug]');
    if(!containers.length) return;
    fetch('api/opkomsten.php?_t=' + Date.now())
      .then(function(r){
        if(!r.ok) throw new Error('HTTP '+r.status);
        return r.json();
      })
      .then(function(data){
        containers.forEach(function(container){
          var slug = container.getAttribute('data-feed-slug');
          var entry = data[slug];
          if(entry && Array.isArray(entry.items) && (entry.ok || entry.items.length)){
            renderFeed(container, entry.items);
          } else {
            showFeedError(container);
          }
        });
      })
      .catch(function(err){
        console.error('Kon programma niet laden:', err);
        containers.forEach(showFeedError);
      });
  }
  document.addEventListener("DOMContentLoaded", function(event){
    loadAllFeeds();
  });

  var navLinks = Array.prototype.slice.call(document.querySelectorAll('#mainNav .header-main-link[href^="#"]'));
  var sections = navLinks.map(function(a){
    return document.getElementById(a.getAttribute('href').slice(1));
  }).filter(Boolean);

  function setActiveLink(id){
    navLinks.forEach(function(a){
      var active = a.getAttribute('href') === '#' + id;
      a.classList.toggle('active', active);
      a.parentElement.classList.toggle('is-active', active);
      if (active) a.setAttribute('aria-current', 'location');
      else a.removeAttribute('aria-current');
    });
  }

  if(sections.length){
    var headerOffset = 100;
    var scrollTimer = null;

    function updateActiveLink(){
      sections.sort(function(a, b) { return a.offsetTop - b.offsetTop; });
      var pos = window.scrollY + headerOffset;
      var current = sections[0];
      sections.forEach(function(sec){
        if(sec.offsetTop <= pos){ current = sec; }
      });
      var atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
      if(atBottom){ current = sections[sections.length - 1]; }
      setActiveLink(current.id);
    }

    window.addEventListener('scroll', function(){
      if(scrollTimer === null){
        scrollTimer = setTimeout(function(){
          updateActiveLink();
          scrollTimer = null;
        }, 50);
      }
    }, {passive: true});
    window.addEventListener('resize', updateActiveLink);
    updateActiveLink();
  }

  var revealEls = Array.prototype.slice.call(document.querySelectorAll(
    '.section-title, .section-sub, .feature-card, .info-card, .contact-card, .verhuur-grid > div'
  ));
  if(revealEls.length){
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if(reduceMotion || !('IntersectionObserver' in window)){
      revealEls.forEach(function(el){ el.classList.add('is-visible'); });
    } else {
      var revealObserver = new IntersectionObserver(function(entries, observer){
        entries.forEach(function(entry){
          if(entry.isIntersecting){
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, {threshold: 0.15, rootMargin: '0px 0px -40px 0px'});
      revealEls.forEach(function(el){ revealObserver.observe(el); });
    }
  }
})();
