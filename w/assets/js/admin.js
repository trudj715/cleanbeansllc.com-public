/* Clean Beans - site admin behaviour (no dependencies) */
(function () {
  'use strict';
  var A = window.KM_ADMIN || { base: '' };
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  // Mobile admin menu
  var mb = $('.a-menu'), nav = $('#a-nav');
  if (mb && nav) mb.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    mb.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Confirm before destructive actions
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  // Warn before leaving a form with unsaved changes
  var dirty = false;
  $$('form').forEach(function (f) {
    f.addEventListener('input', function () { dirty = true; });
    f.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- Photo picker (shared dialog) ---------- */
  var pickerDlg = null, pickerCb = null, pickerMulti = false, pickerSel = [];
  function openPicker(multi, cb) {
    pickerMulti = multi; pickerCb = cb; pickerSel = [];
    if (!pickerDlg) {
      pickerDlg = document.createElement('dialog');
      pickerDlg.className = 'picker';
      pickerDlg.innerHTML = '<div class="picker-head"><strong>Choose from the photo library</strong><div class="btn-row">' +
        '<button type="button" class="btn primary sm" data-done>Use selected</button><button type="button" class="btn ghost sm" data-close>Cancel</button></div></div>' +
        '<div class="picker-body"><p class="small muted">Need a new photo? Upload it under Photos first.</p><div class="media-grid"></div></div>';
      document.body.appendChild(pickerDlg);
      $('[data-close]', pickerDlg).addEventListener('click', function () { pickerDlg.close(); });
      $('[data-done]', pickerDlg).addEventListener('click', function () { pickerDlg.close(); if (pickerCb) pickerCb(pickerSel); });
    }
    var grid = $('.media-grid', pickerDlg);
    grid.innerHTML = '<p>Loading…</p>';
    $('[data-done]', pickerDlg).hidden = !multi;
    pickerDlg.showModal();
    fetch(A.base + '/admin/media/picker', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (items) {
      grid.innerHTML = '';
      if (!items.length) grid.innerHTML = '<p>No photos in the library yet.</p>';
      items.forEach(function (m) {
        var b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-pressed', 'false');
        b.innerHTML = '<img loading="lazy" alt=""><div class="cap"></div>';
        b.querySelector('img').src = m.thumb;
        b.querySelector('.cap').textContent = m.alt || ('Photo ' + m.id);
        b.addEventListener('click', function () {
          if (!pickerMulti) { pickerDlg.close(); pickerCb([m]); return; }
          var i = pickerSel.findIndex(function (x) { return x.id === m.id; });
          if (i >= 0) { pickerSel.splice(i, 1); b.setAttribute('aria-pressed', 'false'); }
          else { pickerSel.push(m); b.setAttribute('aria-pressed', 'true'); }
        });
        grid.appendChild(b);
      });
    }).catch(function () { grid.innerHTML = '<p>Could not load photos. Refresh and try again.</p>'; });
  }

  function markDirty() { dirty = true; }

  // Single photo field
  $$('[data-picker="single"]').forEach(function (box) {
    var img = $('img', box), input = $('input[type=hidden]', box), clear = $('[data-clear]', box);
    $('[data-pick]', box).addEventListener('click', function () {
      openPicker(false, function (sel) {
        if (!sel.length) return;
        input.value = sel[0].id; img.src = sel[0].thumb; img.hidden = false; clear.hidden = false; markDirty();
      });
    });
    clear.addEventListener('click', function () { input.value = ''; img.hidden = true; clear.hidden = true; markDirty(); });
  });

  // Ordered list of chosen photos (gallery sections)
  function chosenItem(m, withCtl) {
    var d = document.createElement('div');
    d.className = 'ch'; d.setAttribute('data-id', m.id);
    d.innerHTML = '<img alt="">' + (withCtl ? '<div class="ctl"><button type="button" data-left aria-label="Move left">‹</button><button type="button" data-right aria-label="Move right">›</button><button type="button" data-remove aria-label="Remove">✕</button></div>' : '<div class="ctl"><button type="button" data-remove aria-label="Remove">✕</button></div>');
    d.querySelector('img').src = m.thumb;
    return d;
  }
  function syncIds(box) {
    $('input[type=hidden]', box).value = $$('.chosen .ch', box).map(function (c) { return c.getAttribute('data-id'); }).join(',');
  }
  function wireOrdering(container, after) {
    container.addEventListener('click', function (e) {
      var btn = e.target.closest('button'); if (!btn) return;
      var item = btn.closest('.ch'); if (!item) return;
      if (btn.hasAttribute('data-left') && item.previousElementSibling) container.insertBefore(item, item.previousElementSibling);
      else if (btn.hasAttribute('data-right') && item.nextElementSibling) container.insertBefore(item.nextElementSibling, item);
      else if (btn.hasAttribute('data-remove')) item.remove();
      else return;
      markDirty(); after();
    });
  }
  $$('[data-picker="multi"], [data-picker="multi-add"]').forEach(function (box) {
    var list = $('.chosen', box);
    var withCtl = box.getAttribute('data-picker') === 'multi';
    wireOrdering(list, function () { syncIds(box); });
    $('[data-pick]', box).addEventListener('click', function () {
      openPicker(true, function (sel) {
        sel.forEach(function (m) { list.appendChild(chosenItem(m, withCtl)); });
        syncIds(box); markDirty();
      });
    });
  });

  // Drop zones: drag files onto the box, show chosen file names
  $$('[data-dropzone]').forEach(function (z) {
    var input = $('input[type=file]', z);
    var note = document.createElement('p'); note.className = 'small muted'; z.appendChild(note);
    var show = function () { var n = input.files.length; note.textContent = n ? n + ' photo' + (n > 1 ? 's' : '') + ' ready to upload. Click Save/Upload.' : ''; };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(function (ev) { z.addEventListener(ev, function (e) { e.preventDefault(); z.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { z.addEventListener(ev, function () { z.classList.remove('over'); }); });
    z.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); markDirty(); } });
  });

  // Vehicle fit rows
  var fits = $('[data-fits]');
  if (fits) {
    fits.addEventListener('click', function (e) {
      if (e.target.matches('[data-fit-add]')) {
        var rows = $$('.fit-row', fits).filter(function (r) { return $('input', r); });
        var clone = rows[rows.length - 1].cloneNode(true);
        $$('input', clone).forEach(function (i) { i.value = ''; });
        fits.insertBefore(clone, e.target);
        $('input', clone).focus();
      } else if (e.target.matches('[data-fit-remove]')) {
        var row = e.target.closest('.fit-row');
        if ($$('.fit-row', fits).filter(function (r) { return $('input', r); }).length > 1) row.remove();
        else $$('input', row).forEach(function (i) { i.value = ''; });
        markDirty();
      }
    });
  }

  // FAQ rows
  $$('[data-faq]').forEach(function (box) {
    box.addEventListener('click', function (e) {
      if (e.target.matches('[data-faq-add]')) {
        var rows = $$('.faq-row', box);
        var c = rows[rows.length - 1].cloneNode(true);
        $$('input, textarea', c).forEach(function (i) { i.value = ''; });
        box.insertBefore(c, e.target);
      } else if (e.target.matches('[data-faq-remove]')) {
        var all = $$('.faq-row', box);
        if (all.length > 1) e.target.closest('.faq-row').remove();
        else $$('input, textarea', all[0]).forEach(function (i) { i.value = ''; });
        markDirty();
      }
    });
  });

  // Map: find latitude/longitude from the address (OpenStreetMap Nominatim)
  $$('[data-geocode]').forEach(function (btn) {
    var form = btn.closest('form');
    btn.addEventListener('click', function () {
      var addr = $('[data-geo-address]', form).value.trim();
      var msg = $('[data-geo-msg]', form);
      if (!addr) { msg.textContent = 'Enter the address first.'; return; }
      msg.textContent = 'Looking up…';
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=us&q=' + encodeURIComponent(addr))
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.length) { msg.textContent = 'Address not found. Try adding the city and ZIP.'; return; }
          $('[data-geo-lat]', form).value = parseFloat(res[0].lat).toFixed(6);
          $('[data-geo-lng]', form).value = parseFloat(res[0].lon).toFixed(6);
          msg.textContent = 'Found: ' + res[0].display_name + '. Save the section to use it.';
          markDirty();
        }).catch(function () { msg.textContent = 'Lookup failed. You can type the numbers in instead.'; });
    });
  });

  // QR code for authenticator setup
  var qr = $('#qr');
  if (qr) {
    var draw = function () {
      if (window.QRCode) new window.QRCode(qr, { text: qr.getAttribute('data-otpauth'), width: 196, height: 196 });
      else setTimeout(draw, 150);
    };
    draw();
  }

  /* ---------- Rich text editor (visual by default, with an HTML view) ---------- */
  $$('textarea.rte-input').forEach(function (ta) {
    var wrap = document.createElement('div');
    wrap.className = 'rte';
    var bar = document.createElement('div');
    bar.className = 'rte-bar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Text formatting');
    var area = document.createElement('div');
    area.className = 'rte-area';
    area.contentEditable = 'true';
    area.setAttribute('role', 'textbox');
    area.setAttribute('aria-multiline', 'true');
    area.innerHTML = ta.value;
    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(ta);
    ta.classList.add('rte-src');

    var tools = [
      ['B', 'Bold', function () { document.execCommand('bold'); }],
      ['I', 'Italic', function () { document.execCommand('italic'); }],
      ['H2', 'Heading', function () { document.execCommand('formatBlock', false, 'h2'); }],
      ['H3', 'Subheading', function () { document.execCommand('formatBlock', false, 'h3'); }],
      ['¶', 'Paragraph', function () { document.execCommand('formatBlock', false, 'p'); }],
      null,
      ['• List', 'Bulleted list', function () { document.execCommand('insertUnorderedList'); }],
      ['1. List', 'Numbered list', function () { document.execCommand('insertOrderedList'); }],
      null,
      ['Link', 'Add a link', function () {
        var u = window.prompt('Link address (https://…, tel:…, or a page like /w/about):');
        if (u) document.execCommand('createLink', false, u.trim());
      }],
      ['Unlink', 'Remove link', function () { document.execCommand('unlink'); }],
      ['Photo', 'Insert a photo from the library', function () {
        var range = saveRange();
        openPicker(false, function (sel) {
          if (!sel.length) return;
          restoreRange(range);
          document.execCommand('insertHTML', false, '<img src="' + sel[0].url + '" alt="' + (sel[0].alt || '').replace(/"/g, '&quot;') + '">');
          sync();
        });
      }],
      ['Clear', 'Remove formatting', function () { document.execCommand('removeFormat'); }],
      null,
      ['HTML', 'Edit the HTML directly', null]
    ];
    var htmlBtn;
    tools.forEach(function (t) {
      if (!t) { var s = document.createElement('span'); s.className = 'sep'; bar.appendChild(s); return; }
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = t[0]; b.title = t[1]; b.setAttribute('aria-label', t[1]);
      if (t[2]) {
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () { area.focus(); t[2](); sync(); });
      } else { htmlBtn = b; b.setAttribute('aria-pressed', 'false'); }
      bar.appendChild(b);
    });

    function sync() { if (!wrap.classList.contains('source')) ta.value = area.innerHTML; markDirty(); }
    function saveRange() { var s = window.getSelection(); return s.rangeCount ? s.getRangeAt(0).cloneRange() : null; }
    function restoreRange(r) { area.focus(); if (!r) return; var s = window.getSelection(); s.removeAllRanges(); s.addRange(r); }

    area.addEventListener('input', sync);
    area.addEventListener('paste', function (e) {
      // Paste as plain text to avoid bringing in Word/web formatting
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
    htmlBtn.addEventListener('click', function () {
      var src = wrap.classList.toggle('source');
      htmlBtn.setAttribute('aria-pressed', src ? 'true' : 'false');
      if (src) { ta.value = area.innerHTML; ta.focus(); }
      else { area.innerHTML = ta.value; area.focus(); }
      $$('button', bar).forEach(function (b) { if (b !== htmlBtn) b.disabled = src; });
    });
    ta.form && ta.form.addEventListener('submit', function () { if (!wrap.classList.contains('source')) ta.value = area.innerHTML; });
  });
})();
