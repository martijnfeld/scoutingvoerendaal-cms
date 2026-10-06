(function(){
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainNav');
  toggle.addEventListener('click', function(){
    nav.classList.toggle('open');
  });
  nav.addEventListener('click', function(e){
    if(e.target.tagName === 'A'){ nav.classList.remove('open'); }
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

  var navLinks = Array.prototype.slice.call(document.querySelectorAll('#mainNav a[href^="#"]'));
  var sections = navLinks.map(function(a){
    return document.getElementById(a.getAttribute('href').slice(1));
  }).filter(Boolean);

  function setActiveLink(id){
    navLinks.forEach(function(a){
      a.classList.toggle('active', a.getAttribute('href') === '#' + id);
    });
  }

  if(sections.length){
    var headerOffset = 100;
    var scrollTimer = null;

    function updateActiveLink(){
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
