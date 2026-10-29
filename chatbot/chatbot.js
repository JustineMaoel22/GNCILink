/*!
 * GNCILink AI Assistant - frontend.
 * Talks ONLY to the site's own PHP endpoint (data-endpoint). No API keys or Google URLs exist in this file.
 * Conversation lives in sessionStorage (cleared when the tab closes or on "New conversation") - nothing is stored on the server.
 */
(function () {
  'use strict';

  var root = document.getElementById('gncbot');
  if (!root) { return; }
  // Keep fixed positioning independent of transformed page/animation containers.
  if (root.parentNode !== document.body) { document.body.appendChild(root); }

  var MAX_LEN = 500, MAX_HISTORY = 6, MAX_STORED = 40, TIMEOUT_MS = 35000, STORE_KEY = 'gncbot.v1';
  var FRIENDLY = "Sorry, I'm having trouble responding right now. Please try again later.";
  var OFFLINE = "I can't reach the server right now. Please check your internet connection and try again.";
  var WELCOME = "Hello! \uD83D\uDC4B I'm Cian, your GNCILink AI Assistant. I can help you find information about Guagua National Colleges.";

  var el = {
    launcher: root.querySelector('.gncbot-launcher'),
    panel: root.querySelector('.gncbot-panel'),
    list: root.querySelector('.gncbot-messages'),
    scroller: root.querySelector('.gncbot-conversation'),
    chips: root.querySelector('.gncbot-suggestions'),
    form: root.querySelector('.gncbot-form'),
    input: root.querySelector('.gncbot-input'),
    send: root.querySelector('.gncbot-send'),
    close: root.querySelector('.gncbot-close'),
    fresh: root.querySelector('.gncbot-new')
  };

  var endpoint = root.getAttribute('data-endpoint') || '/chatbot/chatbot.php';
  var state = { messages: [], busy: false, open: false, ctrl: null, typingNode: null };

  /* ------------------------------------------------------------ page context */
  function pageContext() {
    var q = new URLSearchParams(location.search);
    var prog = root.getAttribute('data-program') || q.get('program') || '';
    if (!/^[A-Za-z\-]{2,10}$/.test(prog) || prog.toUpperCase() === 'ALL') { prog = ''; }
    return {
      url: (location.pathname + location.search).slice(0, 200),
      title: (document.title || '').slice(0, 100),
      program: prog,
      dept: root.getAttribute('data-dept') || ''
    };
  }

  function suggestions() {
    var p = pageContext().program;
    if (p) {
      return ['What events are coming up for ' + p + '?', 'Show me ' + p + ' announcements.', 'Tell me about ' + p + '.', 'Where is GNC located?'];
    }
    return ['What programs does GNC offer?', 'What are the latest announcements?', 'Where is GNC located?'];
  }

  /* ------------------------------------------------------------ safe rendering */
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function unesc(s) {
    return String(s).replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
  }

  /** Only same-site links or the official Facebook pages are ever turned into <a>. */
  function safeHref(raw) {
    try {
      var u = new URL(raw, location.origin);
      if (u.protocol !== 'http:' && u.protocol !== 'https:') { return null; }
      if (u.origin === location.origin) {
        // Conversations saved before the route correction still contain the old URL.
        var path = u.pathname.replace(/\/pages\/academics\/college\/college-departments\.php$/, '/pages/academics/college/college-dept.php');
        return { href: path + u.search + u.hash, external: false };
      }
      if (/(^|\.)facebook\.com$/i.test(u.hostname)) { return { href: u.href, external: true }; }
    } catch (e) { /* ignore */ }
    return null;
  }

  function inline(escaped) {
    var out = escaped.replace(/\[([^\]]{1,120})\]\(([^)\s]{1,300})\)/g, function (m, text, url) {
      var h = safeHref(unesc(url));
      if (!h) { return text; }
      return '<a href="' + esc(h.href) + '"' + (h.external ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + text + '</a>';
    });
    return out.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
  }

  /** Tiny markdown subset (paragraphs, bullets, numbered lists, bold, links). Input is escaped FIRST. */
  function render(text) {
    var lines = esc(text).replace(/\r/g, '').split('\n');
    var html = '', para = [], list = null;
    function flushPara() { if (para.length) { html += '<p>' + para.map(inline).join('<br>') + '</p>'; para = []; } }
    function flushList() { if (list) { html += '<' + list.t + '>' + list.items.map(function (i) { return '<li>' + inline(i) + '</li>'; }).join('') + '</' + list.t + '>'; list = null; } }
    lines.forEach(function (ln) {
      var b = ln.match(/^\s*[-*\u2022]\s+(.*)$/), n = ln.match(/^\s*\d+[.)]\s+(.*)$/);
      if (b || n) {
        flushPara();
        var t = b ? 'ul' : 'ol';
        if (!list || list.t !== t) { flushList(); list = { t: t, items: [] }; }
        list.items.push((b || n)[1]);
      } else if (ln.trim() === '') { flushPara(); flushList(); }
      else { flushList(); para.push(ln); }
    });
    flushPara(); flushList();
    return html;
  }

  /* ------------------------------------------------------------ storage */
  function load() {
    try {
      var d = JSON.parse(sessionStorage.getItem(STORE_KEY) || 'null');
      if (d && Array.isArray(d.messages)) {
        state.messages = d.messages.filter(function (m) { return m && (m.role === 'user' || m.role === 'model') && typeof m.text === 'string'; }).slice(-MAX_STORED);
      }
    } catch (e) { state.messages = []; }
  }
  function save() {
    try { sessionStorage.setItem(STORE_KEY, JSON.stringify({ messages: state.messages.slice(-MAX_STORED) })); } catch (e) { /* private mode */ }
  }

  /* ------------------------------------------------------------ DOM helpers */
  function scrollDown() { el.scroller.scrollTop = el.scroller.scrollHeight; }

  function addBubble(role, text, opts) {
    opts = opts || {};
    var wrap = document.createElement('div');
    wrap.className = 'gncbot-msg ' + (role === 'user' ? 'is-user' : 'is-bot') + (opts.error ? ' is-error' : '');
    var b = document.createElement('div');
    b.className = 'gncbot-bubble';
    if (role === 'user') { b.textContent = text; } else { b.innerHTML = render(text); }
    wrap.appendChild(b);
    if (opts.links && opts.links.length) {
      var box = document.createElement('div'); box.className = 'gncbot-links';
      opts.links.slice(0, 3).forEach(function (l) {
        var h = l && safeHref(String(l.url || ''));
        if (!h) { return; }
        var a = document.createElement('a'); a.className = 'gncbot-linkchip'; a.href = h.href; a.textContent = String(l.label || 'Open page').slice(0, 60);
        if (h.external) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
        box.appendChild(a);
      });
      if (box.childNodes.length) { wrap.appendChild(box); }
    }
    if (opts.retry) {
      var r = document.createElement('button'); r.type = 'button'; r.className = 'gncbot-retry'; r.textContent = 'Try again';
      r.addEventListener('click', function () { wrap.remove(); ask(opts.retry, true); });
      wrap.appendChild(r);
    }
    el.list.appendChild(wrap);
    scrollDown();
    return wrap;
  }

  function showTyping(on) {
    if (on && !state.typingNode) {
      var w = document.createElement('div'); w.className = 'gncbot-msg is-bot';
      w.innerHTML = '<div class="gncbot-bubble gncbot-typing" role="status" aria-label="Cian is typing"><span></span><span></span><span></span></div>';
      el.list.appendChild(w); state.typingNode = w; scrollDown();
    } else if (!on && state.typingNode) { state.typingNode.remove(); state.typingNode = null; }
  }

  function setBusy(b) {
    state.busy = b; el.send.disabled = b; el.input.setAttribute('aria-busy', b ? 'true' : 'false');
  }

  function drawChips() {
    el.chips.innerHTML = '';
    if (state.messages.length) { return; }
    suggestions().forEach(function (s) {
      var c = document.createElement('button'); c.type = 'button'; c.className = 'gncbot-chip'; c.textContent = s;
      c.addEventListener('click', function () { ask(s, false); });
      el.chips.appendChild(c);
    });
  }

  function redraw() {
    el.list.innerHTML = '';
    addBubble('model', WELCOME);
    state.messages.forEach(function (m) { addBubble(m.role, m.text, { links: m.links }); });
    drawChips();
  }

  /* ------------------------------------------------------------ chat flow */
  function ask(text, isRetry) {
    var privacyChoice=document.getElementById('gncbot-ai-choice');
    if(!privacyChoice || !privacyChoice.checked){if(privacyChoice){privacyChoice.focus();privacyChoice.setCustomValidity('Please choose whether to send your messages to Google Gemini.');privacyChoice.reportValidity();privacyChoice.addEventListener('change',function(){privacyChoice.setCustomValidity('');},{once:true});}return;}
    text = String(text || '').replace(/\s+/g, ' ').trim();
    if (!text || state.busy) { return; }
    if (text.length > MAX_LEN) { addBubble('model', 'Your message is too long. Please keep it under ' + MAX_LEN + ' characters.', { error: true }); return; }

    // History = only completed question/answer pairs (a failed or pending message is never sent as context).
    var history = state.messages.slice(-MAX_HISTORY).map(function (m) { return { role: m.role, text: m.text.slice(0, 600) }; });
    if (!isRetry) { addBubble('user', text); el.chips.innerHTML = ''; }
    el.input.value = ''; autosize(); setBusy(true); showTyping(true);

    if (state.ctrl) { state.ctrl.abort(); }
    var ctrl = new AbortController(); state.ctrl = ctrl;
    var timer = setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS);

    fetch(endpoint, {
      method: 'POST', credentials: 'same-origin', signal: ctrl.signal,
      headers: { 'Content-Type': 'application/json', 'X-GNCI-Chat': '1' },
      body: JSON.stringify({ message: text, history: history, page: pageContext(), ai_notice_accepted: true })
    }).then(function (res) {
      return res.text().then(function (t) { var d = null; try { d = JSON.parse(t); } catch (e) { /* non-JSON (e.g. PHP die) */ } return d; });
    }).then(function (d) {
      showTyping(false);
      if (d && d.ok && typeof d.reply === 'string') {
        state.messages.push({ role: 'user', text: text }, { role: 'model', text: d.reply, links: d.links || [] }); save();
        addBubble('model', d.reply, { links: d.links });
      } else {
        var msg = (d && typeof d.message === 'string' && d.message) || FRIENDLY;
        var rejected = d && (d.error === 'empty' || d.error === 'too_long');
        addBubble('model', msg, { error: true, retry: rejected ? null : text });
      }
    }).catch(function (err) {
      showTyping(false);
      if (err && err.name === 'AbortError' && state.ctrl !== ctrl) { return; }   // superseded by "New conversation"
      addBubble('model', err && err.name === 'AbortError' ? FRIENDLY : OFFLINE, { error: true, retry: text });
    }).then(function () {
      clearTimeout(timer); if (state.ctrl === ctrl) { state.ctrl = null; }
      if (state.ctrl === null || state.ctrl === ctrl) { setBusy(false); }
      if (state.open && matchMedia('(hover: hover)').matches) { el.input.focus(); }
    });
  }

  function newConversation() {
    if (state.ctrl) { state.ctrl.abort(); state.ctrl = null; }
    showTyping(false); setBusy(false);
    state.messages = []; save(); redraw(); el.input.value = ''; autosize();
  }

  /* ------------------------------------------------------------ open / close */
  var mobileMQ = window.matchMedia('(max-width: 576px)');
  var closeTimer = null, pageScroll = null;

  function lockPage(lock) {
    if (lock && !pageScroll) {
      pageScroll = { x: window.scrollX, y: window.scrollY };
      document.body.style.setProperty('--gb-scroll-top', -pageScroll.y + 'px');
      document.body.classList.add('gncbot-noscroll');
    } else if (!lock && pageScroll) {
      var saved = pageScroll; pageScroll = null;
      document.body.classList.remove('gncbot-noscroll');
      document.body.style.removeProperty('--gb-scroll-top');
      window.scrollTo({ left: saved.x, top: saved.y, behavior: 'instant' });
    }
  }

  function fitViewport() {
    var h = (window.visualViewport && mobileMQ.matches) ? window.visualViewport.height : 0;
    // When there is no measured height, remove the inline override so the CSS default
    // (100vh, upgraded to 100dvh where supported) applies. Works on older phones too.
    if (h) {
      root.style.setProperty('--gb-vh', h + 'px');
      root.style.setProperty('--gb-top', window.visualViewport.offsetTop + 'px');
    } else {
      root.style.removeProperty('--gb-vh'); root.style.removeProperty('--gb-top');
    }
    lockPage((state.open || root.classList.contains('is-closing')) && mobileMQ.matches);
  }

  function setOpen(open) {
    clearTimeout(closeTimer);
    state.open = open;
    el.launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    el.launcher.setAttribute('aria-label', open ? 'Close chat with Cian' : 'Open chat with Cian');
    if (open) {
      root.classList.remove('is-closing');
      root.classList.add('is-open', 'has-opened');
      el.panel.hidden = false; el.panel.removeAttribute('inert');
      fitViewport(); scrollDown();
      if (matchMedia('(hover: hover)').matches) { el.input.focus(); }
    } else {
      if (el.panel.hidden) { return; }
      el.panel.setAttribute('inert', '');
      if (el.panel.contains(document.activeElement)) { document.activeElement.blur(); }
      root.classList.add('is-closing');
      function finishClose() {
        el.panel.hidden = true;
        root.classList.remove('is-open', 'is-closing');
        lockPage(false);
        el.launcher.focus({ preventScroll: true });
      }
      if (matchMedia('(prefers-reduced-motion: reduce)').matches) { finishClose(); }
      else { closeTimer = setTimeout(finishClose, 200); }
    }
  }

  /* ------------------------------------------------------------ composer */
  function autosize() { el.input.style.height = 'auto'; el.input.style.height = Math.min(el.input.scrollHeight, 110) + 'px'; }

  el.launcher.addEventListener('click', function () { setOpen(!state.open); });
  el.close.addEventListener('click', function () { setOpen(false); });
  el.fresh.addEventListener('click', newConversation);
  el.form.addEventListener('submit', function (e) { e.preventDefault(); ask(el.input.value, false); });
  el.input.addEventListener('input', autosize);
  el.input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); ask(el.input.value, false); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open) { setOpen(false); } });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', fitViewport);
    window.visualViewport.addEventListener('scroll', fitViewport);
  }
  window.addEventListener('resize', fitViewport);
  window.addEventListener('orientationchange', fitViewport);

  /* ------------------------------------------------------------ init */
  /**
   * Keep the widget hidden until chatbot.css has really loaded, however long that takes
   * (slow phones / cold CDN). Reacts to the stylesheet's load event, the window load event,
   * and polls for up to 30s as a safety net.
   */
  function revealWhenStyled() {
    function isStyled() { return getComputedStyle(root).getPropertyValue('--gb-green').trim() !== ''; }
    function reveal() { root.classList.add('gncbot-ready'); }
    if (isStyled()) { reveal(); return; }

    var link = document.querySelector('link[href*="chatbot.css"]');
    if (link) { link.addEventListener('load', function () { if (isStyled()) { reveal(); } }); }
    window.addEventListener('load', function () { if (isStyled()) { reveal(); } });

    var tries = 0;
    (function poll() {
      if (root.classList.contains('gncbot-ready')) { return; }
      if (isStyled()) { reveal(); return; }
      if (++tries < 60) { setTimeout(poll, 500); return; }
      if (window.console) { console.error('[GNCILink chatbot] chatbot.css did not load - widget kept hidden. Check that ' + endpoint.replace(/chatbot\.php$/, 'chatbot.css') + ' opens in the browser.'); }
    })();
  }
  revealWhenStyled();

  load(); redraw();
  window.GNCBot = { open: function () { setOpen(true); }, close: function () { setOpen(false); }, reset: newConversation };   // handy hook for other pages/buttons
})();
