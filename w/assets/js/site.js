/* Clean Beans - public site behaviour (no dependencies; Leaflet only on map pages) */
(function () {
  'use strict';

  // Mobile menu
  var toggle = document.querySelector('.menu-toggle');
  var nav = document.getElementById('main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.textContent = open ? 'Close' : 'Menu';
    });
  }

  // Gallery lightbox
  var galleryButtons = document.querySelectorAll('.gallery button[data-full]');
  if (galleryButtons.length && typeof HTMLDialogElement === 'function') {
    var dlg = document.createElement('dialog');
    dlg.className = 'lightbox';
    dlg.innerHTML = '<form method="dialog"><span class="lb-count" aria-live="polite"></span><button type="button" class="lb-prev" aria-label="Previous photo">&lsaquo;</button>'
      + '<button type="button" class="lb-next" aria-label="Next photo">&rsaquo;</button><button type="submit">Close</button></form><img alt="">';
    document.body.appendChild(dlg);
    var img = dlg.querySelector('img'), count = dlg.querySelector('.lb-count');
    var list = Array.prototype.slice.call(galleryButtons), cur = 0;
    function show(i) {
      cur = (i + list.length) % list.length;
      var b = list[cur];
      img.src = b.getAttribute('data-full');
      img.alt = b.getAttribute('data-alt') || '';
      count.textContent = (cur + 1) + ' / ' + list.length;
    }
    list.forEach(function (b, i) {
      b.addEventListener('click', function () { show(i); dlg.showModal(); });
    });
    dlg.querySelector('.lb-prev').addEventListener('click', function () { show(cur - 1); });
    dlg.querySelector('.lb-next').addEventListener('click', function () { show(cur + 1); });
    dlg.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { show(cur - 1); e.preventDefault(); }
      if (e.key === 'ArrowRight') { show(cur + 1); e.preventDefault(); }
    });
    var touchX = null;
    dlg.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
    dlg.addEventListener('touchend', function (e) {
      if (touchX === null) return;
      var dx = e.changedTouches[0].clientX - touchX; touchX = null;
      if (Math.abs(dx) > 50) show(cur + (dx < 0 ? 1 : -1));
    });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  }

  // Service-area map with a true radius circle (Leaflet + OpenStreetMap)
  var mapEl = document.querySelector('[data-map]');
  if (mapEl && window.L) {
    var lat = parseFloat(mapEl.dataset.lat), lng = parseFloat(mapEl.dataset.lng);
    var miles = parseFloat(mapEl.dataset.miles) || 0;
    var map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], parseInt(mapEl.dataset.zoom, 10) || 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    L.marker([lat, lng]).addTo(map).bindPopup(mapEl.dataset.label || '');
    if (miles > 0) {
      var circle = L.circle([lat, lng], { radius: miles * 1609.344, color: '#172a5c', weight: 2, fillColor: '#f5b301', fillOpacity: 0.12 }).addTo(map);
      map.fitBounds(circle.getBounds(), { padding: [10, 10] });
    }
  }
})();
