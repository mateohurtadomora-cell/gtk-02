(function(){
  'use strict';

  /*__I18N_DATA__*/

  var docEl = document.documentElement;
  var CURRENT_LANG = docEl.getAttribute('lang') === 'en' ? 'en' : 'es';

  function escapeForUrl(lang){ return lang; }

  function apply(lang){
    var dict = I18N[lang];
    if(!dict) return;

    var i, el, list;

    list = document.querySelectorAll('[data-i18n]');
    for(i=0;i<list.length;i++){
      el = list[i];
      var key = el.getAttribute('data-i18n');
      if(dict[key] !== undefined){ el.textContent = dict[key]; }
    }

    for(var sk in markTriggered){
      if(markTriggered[sk]){ revealTitleMark(sk); }
    }

    list = document.querySelectorAll('[data-i18n-html]');
    for(i=0;i<list.length;i++){
      el = list[i];
      var hkey = el.getAttribute('data-i18n-html');
      if(dict[hkey] !== undefined){ el.innerHTML = dict[hkey]; }
    }

    list = document.querySelectorAll('[data-i18n-ph]');
    for(i=0;i<list.length;i++){
      el = list[i];
      var pkey = el.getAttribute('data-i18n-ph');
      if(dict[pkey] !== undefined){ el.setAttribute('placeholder', dict[pkey]); }
    }

    list = document.querySelectorAll('[data-i18n-aria]');
    for(i=0;i<list.length;i++){
      el = list[i];
      var akey = el.getAttribute('data-i18n-aria');
      if(dict[akey] !== undefined){ el.setAttribute('aria-label', dict[akey]); }
    }

    docEl.lang = lang;
    document.title = dict.meta_title;

    var metaDesc = document.querySelector('meta[name="description"]');
    if(metaDesc){ metaDesc.setAttribute('content', dict.meta_description); }

    var ogTitle = document.querySelector('meta[property="og:title"]');
    if(ogTitle){ ogTitle.setAttribute('content', dict.meta_title); }

    var ogDesc = document.querySelector('meta[property="og:description"]');
    if(ogDesc){ ogDesc.setAttribute('content', dict.meta_description); }

    var ogLocale = document.querySelector('meta[property="og:locale"]');
    if(ogLocale){ ogLocale.setAttribute('content', lang === 'es' ? 'es_ES' : 'en_US'); }

    var otherLang = lang === 'es' ? 'en' : 'es';

    var canonical = document.querySelector('link[rel="canonical"]');
    var newPath = null;
    if(canonical){
      var href = canonical.getAttribute('href');
      href = href.replace('/' + otherLang + '/', '/' + lang + '/');
      canonical.setAttribute('href', href);
    }

    var ogUrl = document.querySelector('meta[property="og:url"]');
    if(ogUrl){
      var ourl = ogUrl.getAttribute('content');
      ourl = ourl.replace('/' + otherLang + '/', '/' + lang + '/');
      ogUrl.setAttribute('content', ourl);
    }

    var langBtns = document.querySelectorAll('.lang__btn');
    for(i=0;i<langBtns.length;i++){
      var btnLang = langBtns[i].getAttribute('data-lang');
      langBtns[i].setAttribute('aria-pressed', btnLang === lang ? 'true' : 'false');
    }

    if(window.history && window.history.replaceState){
      var path = window.location.pathname.replace('/' + otherLang + '/', '/' + lang + '/');
      window.history.replaceState(null, '', path + window.location.hash);
    }

    CURRENT_LANG = lang;
  }

  var langButtons = document.querySelectorAll('.lang__btn');
  for(var li=0; li<langButtons.length; li++){
    langButtons[li].addEventListener('click', (function(btn){
      return function(){
        apply(btn.getAttribute('data-lang'));
      };
    })(langButtons[li]));
  }

  /* Network background: drifts with scroll depth */
  var netSvg = document.querySelector('#netBg svg');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(netSvg && !reduceMotion){
    var netTicking = false;
    var updateNetBg = function(){
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      var tx = Math.cos(y / 1400) * 18;
      var ty = Math.sin(y / 900) * 34;
      var rot = Math.sin(y / 2200) * 7;
      var scale = 1 + Math.sin(y / 1600) * 0.05;
      netSvg.style.transform = 'translate3d(' + tx.toFixed(2) + 'px,' + ty.toFixed(2) + 'px,0) rotate(' + rot.toFixed(2) + 'deg) scale(' + scale.toFixed(3) + ')';
      netTicking = false;
    };
    window.addEventListener('scroll', function(){
      if(!netTicking){
        window.requestAnimationFrame(updateNetBg);
        netTicking = true;
      }
    }, { passive: true });
    updateNetBg();
  }

  /* Footer year */
  var yearEl = document.getElementById('year');
  if(yearEl){ yearEl.textContent = new Date().getFullYear(); }

  /* Databand stat counters: count up from 0 the first time each cell
     reveals, easing out so it settles rather than ticking mechanically. */
  function countUp(el){
    var target = parseInt(el.getAttribute('data-count'), 10);
    if(isNaN(target)) return;
    if(reduceMotion){ el.textContent = target; return; }
    var duration = 1100;
    var start = null;
    function step(ts){
      if(start === null){ start = ts; }
      var p = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased);
      if(p < 1){ window.requestAnimationFrame(step); }
      else { el.textContent = target; }
    }
    window.requestAnimationFrame(step);
  }

  /* Reveal on scroll */
  var rvEls = document.querySelectorAll('.rv');
  if('IntersectionObserver' in window){
    var rvObserver = new IntersectionObserver(function(entries){
      for(var i=0;i<entries.length;i++){
        if(entries[i].isIntersecting){
          var target = entries[i].target;
          target.classList.add('is-in');
          var counters = target.querySelectorAll('[data-count]');
          for(var ci=0; ci<counters.length; ci++){ countUp(counters[ci]); }
          rvObserver.unobserve(target);
        }
      }
    }, { threshold: 0.12 });
    for(var ri=0; ri<rvEls.length; ri++){ rvObserver.observe(rvEls[ri]); }
  } else {
    var allCounters = document.querySelectorAll('[data-count]');
    for(var cj=0; cj<allCounters.length; cj++){ allCounters[cj].textContent = allCounters[cj].getAttribute('data-count'); }
    for(var rj=0; rj<rvEls.length; rj++){ rvEls[rj].classList.add('is-in'); }
  }

  /* Deterministic active-section nav */
  var sections = document.querySelectorAll('main section[id]');
  var sectionState = {};
  var navLinks = document.querySelectorAll('.rail__nav a, .tabbar a');
  var si;
  for(si=0; si<sections.length; si++){ sectionState[sections[si].id] = false; }

  /* Marker dot -> title underline: the strong blue hub tied to a section
     flies to that title and flattens into its underline, once, the first
     time the section comes into view. */
  var markedSections = { areas: 1, socios: 1, colaboraciones: 1 };
  var markTriggered = {};

  /* Last visible line of a (possibly wrapped) inline title, in viewport coords */
  function lastLineRect(el){
    var rects = el.getClientRects();
    return rects.length ? rects[rects.length - 1] : el.getBoundingClientRect();
  }

  /* Underlines every visible line of the (possibly wrapped) title, not
     just the last one -- one bar per line, each sized to that line's own
     width, so a 3-line heading gets fully underlined, not just its tail. */
  function revealTitleMark(sectionId){
    var section = document.getElementById(sectionId);
    var h2 = section && section.querySelector('h2');
    var titleSpan = h2 && h2.querySelector('[data-i18n]');
    if(!h2 || !titleSpan) return;

    var old = h2.querySelectorAll('.h2mark');
    for(var oi=0; oi<old.length; oi++){ old[oi].parentNode.removeChild(old[oi]); }

    var rects = titleSpan.getClientRects();
    if(!rects.length) return;
    var h2Rect = h2.getBoundingClientRect();
    var marks = [];
    for(var i=0;i<rects.length;i++){
      var r = rects[i];
      var mark = document.createElement('span');
      mark.className = 'h2mark';
      mark.setAttribute('aria-hidden', 'true');
      mark.style.left = (r.left - h2Rect.left) + 'px';
      mark.style.top = (r.bottom - h2Rect.top + 8) + 'px';
      mark.style.width = r.width + 'px';
      h2.appendChild(mark);
      marks.push(mark);
    }
    marks[0].getBoundingClientRect(); /* force layout so opacity transitions */
    for(var j=0;j<marks.length;j++){ marks[j].style.opacity = '1'; }
  }

  function flySectionDot(sectionId){
    if(reduceMotion || !netSvg){ revealTitleMark(sectionId); return; }
    var marker = netSvg.querySelector('[data-section="' + sectionId + '"]');
    var section = document.getElementById(sectionId);
    var h2 = section && section.querySelector('h2');
    var titleSpan = h2 && h2.querySelector('[data-i18n]');
    if(!marker || !h2 || !titleSpan){ revealTitleMark(sectionId); return; }

    /* Anchor to document coordinates (not viewport ones): the flight can
       take a few hundred ms, and if the user is still mid-scroll the page
       keeps moving under a fixed-position element, making it land in the
       wrong spot and then "jump" once revealTitleMark corrects it. An
       absolutely-positioned element scrolls with the page like normal
       content, so it stays glued to its target regardless of scroll. */
    var pageX = window.pageXOffset || document.documentElement.scrollLeft || 0;
    var pageY = window.pageYOffset || document.documentElement.scrollTop || 0;
    var markerRect = marker.getBoundingClientRect();
    var lineRect = lastLineRect(titleSpan);
    var size = Math.max(markerRect.width, markerRect.height, 6);
    var targetLeft = lineRect.left + pageX;
    var targetTop = lineRect.bottom + pageY + 8;
    var targetWidth = lineRect.width;

    marker.classList.add('is-spent');
    var halo = marker.previousElementSibling;
    if(halo){ halo.classList.add('is-spent'); }

    var fly = document.createElement('div');
    fly.className = 'net-fly';
    fly.style.left = (markerRect.left + pageX + markerRect.width / 2 - size / 2) + 'px';
    fly.style.top = (markerRect.top + pageY + markerRect.height / 2 - size / 2) + 'px';
    fly.style.width = size + 'px';
    fly.style.height = size + 'px';
    fly.style.borderRadius = '50%';
    document.body.appendChild(fly);
    fly.getBoundingClientRect(); /* force layout before transitioning */

    window.requestAnimationFrame(function(){
      fly.style.left = targetLeft + 'px';
      fly.style.top = targetTop + 'px';
      fly.style.width = targetWidth + 'px';
      fly.style.height = '3px';
      fly.style.borderRadius = '3px';
    });

    var done = false;
    function finish(){
      if(done) return;
      done = true;
      if(fly.parentNode){ fly.parentNode.removeChild(fly); }
      revealTitleMark(sectionId);
    }
    fly.addEventListener('transitionend', function(e){
      if(e.propertyName === 'width'){ finish(); }
    });
    setTimeout(finish, 900);
  }

  function updateActiveNav(){
    var activeId = null;
    for(var i=0;i<sections.length;i++){
      if(sectionState[sections[i].id]){ activeId = sections[i].id; break; }
    }
    for(var j=0;j<navLinks.length;j++){
      var link = navLinks[j];
      if(activeId && link.getAttribute('data-section') === activeId){
        link.classList.add('is-active');
      } else {
        link.classList.remove('is-active');
      }
    }
  }

  if('IntersectionObserver' in window && sections.length){
    var navObserver = new IntersectionObserver(function(entries){
      for(var i=0;i<entries.length;i++){
        sectionState[entries[i].target.id] = entries[i].isIntersecting;
      }
      updateActiveNav();
    }, { rootMargin: '-45% 0px -50% 0px' });
    for(si=0; si<sections.length; si++){ navObserver.observe(sections[si]); }
  }

  /* Trigger the marker flight as soon as each title is comfortably on
     screen (independent from the nav's mid-viewport "active" logic, which
     fires too late for a title sitting near the top of a tall section). */
  if('IntersectionObserver' in window){
    var titleObserver = new IntersectionObserver(function(entries){
      for(var i=0;i<entries.length;i++){
        if(!entries[i].isIntersecting) continue;
        var id = entries[i].target.getAttribute('data-mark-section');
        if(id && markedSections[id] && !markTriggered[id]){
          markTriggered[id] = true;
          flySectionDot(id);
        }
        titleObserver.unobserve(entries[i].target);
      }
    }, { rootMargin: '0px 0px -35% 0px', threshold: 0 });
    for(var mk in markedSections){
      var mkSection = document.getElementById(mk);
      var mkH2 = mkSection && mkSection.querySelector('h2');
      if(mkH2){
        mkH2.setAttribute('data-mark-section', mk);
        titleObserver.observe(mkH2);
      }
    }
  } else {
    for(var mk2 in markedSections){ revealTitleMark(mk2); }
  }

  /* Colaboraciones: escala del ecosistema (anillos) + carrusel */
  var carousel = document.getElementById('carousel');
  var cards = carousel ? carousel.querySelectorAll('.card') : [];
  var scopeSeg = document.getElementById('scopeSeg');
  var scopeCopy = document.getElementById('scopeCopy');
  var scopeRings = document.querySelectorAll('.scope__ring');
  var prevBtn = document.getElementById('prevBtn');
  var nextBtn = document.getElementById('nextBtn');

  /* Cada escala tiene su propia descripcion; la clave viaja al elemento para
     que el conmutador de idioma sepa cual reponer. */
  var SCOPE_DESC = { 'all':'scope_all_desc', '0':'scope_local_desc', '1':'scope_regional_desc',
                     '2':'scope_national_desc', '3':'scope_global_desc' };

  function scopeCount(level){
    var n = 0;
    for(var i=0;i<cards.length;i++){
      if(level === 'all' || cards[i].getAttribute('data-level') === level){ n++; }
    }
    return n;
  }

  function paintScopeCounts(){
    if(!scopeSeg) return;
    var btns = scopeSeg.querySelectorAll('button');
    for(var i=0;i<btns.length;i++){
      var span = btns[i].querySelector('.scope__count');
      if(span){ span.textContent = scopeCount(btns[i].getAttribute('data-level')); }
    }
  }

  function setScope(level){
    var all = level === 'all';
    var i;
    /* El alcance se lee de dentro afuera: elegir Nacional enciende tambien
       Regional y Local, porque un ecosistema nacional los contiene. */
    for(i=0;i<scopeRings.length;i++){
      var on = all || Number(scopeRings[i].getAttribute('data-level')) <= Number(level);
      scopeRings[i].classList.toggle('is-on', on);
    }
    if(scopeSeg){
      var btns = scopeSeg.querySelectorAll('button');
      for(i=0;i<btns.length;i++){
        btns[i].setAttribute('aria-pressed', btns[i].getAttribute('data-level') === level ? 'true' : 'false');
      }
    }
    for(i=0;i<cards.length;i++){
      cards[i].style.display = (all || cards[i].getAttribute('data-level') === level) ? '' : 'none';
    }
    if(scopeCopy){
      var key = SCOPE_DESC[level];
      /* Se cambia el data-i18n, no solo el texto: al cambiar de idioma se
         traduce la descripcion de la escala elegida, no la de "todas". */
      scopeCopy.setAttribute('data-i18n', key);
      var dict = I18N[docEl.lang === 'en' ? 'en' : 'es'];
      if(dict && dict[key] !== undefined){ scopeCopy.textContent = dict[key]; }
    }
    if(carousel){ carousel.scrollTo({ left: 0, behavior: 'smooth' }); }
  }

  if(scopeSeg){
    paintScopeCounts();
    scopeSeg.addEventListener('click', function(e){
      var target = e.target;
      while(target && target !== scopeSeg && target.tagName !== 'BUTTON'){
        target = target.parentNode;
      }
      if(target && target.tagName === 'BUTTON'){
        setScope(target.getAttribute('data-level'));
      }
    });
  }

  /* La primera tarjeta del DOM puede estar oculta por la escala elegida, y una
     tarjeta oculta mide cero: el paso se toma de la primera visible. */
  function firstVisibleCard(){
    for(var i=0;i<cards.length;i++){
      if(cards[i].style.display !== 'none'){ return cards[i]; }
    }
    return null;
  }

  function cardStep(){
    var card = firstVisibleCard();
    if(!card) return 0;
    var style = window.getComputedStyle(carousel);
    var gap = parseFloat(style.columnGap || style.gap || '16') || 16;
    return card.getBoundingClientRect().width + gap;
  }

  if(prevBtn){
    prevBtn.addEventListener('click', function(){
      carousel.scrollBy({ left: -cardStep(), behavior: 'smooth' });
    });
  }
  if(nextBtn){
    nextBtn.addEventListener('click', function(){
      carousel.scrollBy({ left: cardStep(), behavior: 'smooth' });
    });
  }

  /* Clients ribbon */
  var ribbonTrack = document.getElementById('ribbonTrack');

  function findElClosest(el, cls){
    while(el && el !== document){
      if(el.classList && el.classList.contains(cls)){ return el; }
      el = el.parentNode;
    }
    return null;
  }

  function highlightProjects(nums){
    var i;
    for(i=0;i<cards.length;i++){ cards[i].classList.remove('is-target'); }
    var targets = [];
    for(i=0;i<nums.length;i++){
      var card = carousel.querySelector('.card[data-i="' + nums[i] + '"]');
      if(card){ card.classList.add('is-target'); targets.push(card); }
    }
    if(targets.length){
      setTimeout(function(){
        for(var k=0;k<targets.length;k++){ targets[k].classList.remove('is-target'); }
      }, 4200);
    }
    return targets;
  }

  function scrollToCard(card){
    var gutter = parseFloat(window.getComputedStyle(carousel).paddingLeft) || 20;
    var carouselRect = carousel.getBoundingClientRect();
    var cardRect = card.getBoundingClientRect();
    var delta = (cardRect.left - carouselRect.left) - gutter;
    carousel.scrollTo({ left: carousel.scrollLeft + delta, behavior: 'smooth' });
  }

  function onClientClick(btn){
    var raw = btn.getAttribute('data-p');
    if(!raw) return;
    var nums = raw.split(',');
    setScope('all');
    var colabs = document.getElementById('colaboraciones');
    if(colabs){ colabs.scrollIntoView({ behavior: 'smooth' }); }
    var targets = highlightProjects(nums);
    if(targets.length){
      setTimeout(function(){ scrollToCard(targets[0]); }, 400);
    }
  }

  if(ribbonTrack){
    ribbonTrack.addEventListener('click', function(e){
      var btn = findElClosest(e.target, 'logo__btn');
      if(!btn || btn.classList.contains('is-static') || btn.tagName !== 'BUTTON'){ return; }
      onClientClick(btn);
    });
  }

  /* Contact form: in-page validation, then AJAX submit to Formspree so the
     visitor never leaves the page. Falls back to a normal POST navigation
     if fetch is unavailable. */
  var contactForm = document.getElementById('contactForm');
  if(contactForm){
    var statusEl = contactForm.querySelector('.form__status');
    var submitBtn = contactForm.querySelector('.form__submit');

    function t(key){
      var dict = I18N[CURRENT_LANG];
      return (dict && dict[key] !== undefined) ? dict[key] : '';
    }

    function fieldOf(input){ return findElClosest(input, 'form__field'); }

    function setFieldError(input, msg){
      var field = fieldOf(input);
      if(!field) return;
      var err = field.querySelector('.form__err');
      if(msg){
        field.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if(err){ err.textContent = msg; }
      } else {
        field.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        if(err){ err.textContent = ''; }
      }
    }

    function validateInput(input){
      /* La casilla de terminos no tiene texto que comprobar: lo que se valida
         es que este marcada, y su mensaje de error es propio. */
      if(input.type === 'checkbox'){
        if(!input.checked){ setFieldError(input, t('form_terms_required')); return false; }
        setFieldError(input, '');
        return true;
      }
      var value = input.value.replace(/^\s+|\s+$/g, '');
      if(!value){ setFieldError(input, t('form_required')); return false; }
      if(input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)){
        setFieldError(input, t('form_email_invalid'));
        return false;
      }
      setFieldError(input, '');
      return true;
    }

    var fields = contactForm.querySelectorAll('input, textarea');
    for(var fi=0; fi<fields.length; fi++){
      (function(input){
        /* La casilla se revisa solo al marcarla o desmarcarla: hacerlo al
           perder el foco daria error por el mero hecho de tabular sobre ella. */
        if(input.type === 'checkbox'){
          input.addEventListener('change', function(){ validateInput(input); });
          return;
        }
        input.addEventListener('blur', function(){ validateInput(input); });
        input.addEventListener('input', function(){
          if(fieldOf(input) && fieldOf(input).classList.contains('is-invalid')){ validateInput(input); }
        });
      })(fields[fi]);
    }

    function setStatus(msg, kind){
      if(!statusEl) return;
      statusEl.textContent = msg;
      statusEl.className = 'form__status' + (kind ? ' is-' + kind : '');
    }

    contactForm.addEventListener('submit', function(e){
      var valid = true;
      var firstBad = null;
      for(var i=0;i<fields.length;i++){
        if(!validateInput(fields[i])){
          valid = false;
          if(!firstBad){ firstBad = fields[i]; }
        }
      }
      if(!valid){
        e.preventDefault();
        setStatus('', '');
        if(firstBad){ firstBad.focus(); }
        return;
      }
      if(!window.fetch){ return; } /* let the browser POST normally */

      e.preventDefault();
      submitBtn.disabled = true;
      setStatus(t('form_sending'), '');

      window.fetch(contactForm.action, {
        method: 'POST',
        body: new FormData(contactForm),
        headers: { 'Accept': 'application/json' }
      }).then(function(res){
        if(!res.ok){ throw new Error('bad status'); }
        contactForm.reset();
        setStatus(t('form_success'), 'ok');
      }).catch(function(){
        setStatus(t('form_error'), 'err');
      }).then(function(){
        submitBtn.disabled = false;
      });
    });
  }

  /* Modal dialogs. Generic on purpose: any [data-modal-open="id"] opens the
     container with that id, and any [data-modal-close] inside it (plus the
     scrim, plus Escape) closes it. Today the only one is the terms of use. */
  var openModal = null;
  var modalOpener = null;

  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),' +
                  'select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  function modalFocusables(modal){
    var all = modal.querySelectorAll(FOCUSABLE);
    var out = [];
    for(var i=0;i<all.length;i++){
      /* offsetParent is null for anything display:none inside the panel */
      if(all[i].offsetParent !== null || all[i] === document.activeElement){ out.push(all[i]); }
    }
    return out;
  }

  function showModal(modal){
    if(openModal){ hideModal(); }
    modalOpener = document.activeElement;
    modal.hidden = false;
    document.body.classList.add('is-modal');
    openModal = modal;
    var panel = modal.querySelector('.modal__panel');
    /* Focus the panel itself rather than the close button: a screen reader
       then announces the dialog title before anything else. */
    if(panel){ panel.focus(); }
  }

  function hideModal(){
    if(!openModal){ return; }
    openModal.hidden = true;
    document.body.classList.remove('is-modal');
    openModal = null;
    /* Send the focus back where it came from, not to the top of the page. */
    if(modalOpener && modalOpener.focus){ modalOpener.focus(); }
    modalOpener = null;
  }

  document.addEventListener('click', function(e){
    var opener = findElAttr(e.target, 'data-modal-open');
    if(opener){
      var target = document.getElementById(opener.getAttribute('data-modal-open'));
      if(target){ e.preventDefault(); showModal(target); }
      return;
    }
    if(openModal && findElAttr(e.target, 'data-modal-close')){
      e.preventDefault();
      hideModal();
    }
  });

  document.addEventListener('keydown', function(e){
    if(!openModal){ return; }
    if(e.key === 'Escape' || e.keyCode === 27){
      hideModal();
      return;
    }
    if(e.key !== 'Tab' && e.keyCode !== 9){ return; }
    /* Keep Tab inside the dialog: without this the focus walks off into the
       page behind the scrim, which is still there but not operable. */
    var items = modalFocusables(openModal);
    if(!items.length){ e.preventDefault(); return; }
    var first = items[0], last = items[items.length-1];
    var active = document.activeElement;
    if(e.shiftKey && (active === first || active === openModal.querySelector('.modal__panel'))){
      e.preventDefault(); last.focus();
    } else if(!e.shiftKey && active === last){
      e.preventDefault(); first.focus();
    }
  });

  function findElAttr(el, attr){
    while(el && el !== document){
      if(el.nodeType === 1 && el.hasAttribute(attr)){ return el; }
      el = el.parentNode;
    }
    return null;
  }

})();
