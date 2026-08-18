(() => {
  'use strict';

  const boot = () => {
    const root = document.getElementById('annonceNearbyMap');
    const form = document.getElementById('annonceNearbySearchForm');
    if (!root || !form || root.dataset.annonceMapReady === '1') return;
    root.dataset.annonceMapReady = '1';

    const panel = document.getElementById('annonceNearbyMapSection');
    const closeButton = document.getElementById('annonceNearbyMapClose');
    const button = document.getElementById('annonceNearbySearchButton');
    const summary = document.getElementById('annonceNearbyMapSummary');
    const message = document.getElementById('annonceNearbyMapMessage');
    const radius = document.getElementById('annonce_radius');
    let map = null;
    let markers = null;

    const escapeHtml = (value) => {
      const node = document.createElement('div');
      node.textContent = value == null ? '' : String(value);
      return node.innerHTML;
    };

    const setLoading = (loading) => {
      if (!button) return;
      if (loading) {
        button.dataset.originalHtml ||= button.innerHTML;
        button.innerHTML = '<span class="mol-submit-spinner" aria-hidden="true"></span><span>Traitement…</span>';
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
      } else {
        if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        button.disabled = false;
        button.removeAttribute('aria-busy');
      }
    };

    const setMessage = (text, isError = false) => {
      if (!message) return;
      message.hidden = false;
      message.textContent = text;
      message.classList.toggle('is-error', isError);
    };

    const ensureLeaflet = () => {
      if (window.L) return Promise.resolve(window.L);
      window.__molLeafletPromise ||= new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-mol-leaflet]')) {
          const css = document.createElement('link');
          css.rel = 'stylesheet';
          css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
          css.dataset.molLeaflet = '1';
          document.head.appendChild(css);
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.defer = true;
        script.dataset.molLeaflet = '1';
        script.onload = () => resolve(window.L);
        script.onerror = reject;
        document.head.appendChild(script);
      });
      return window.__molLeafletPromise;
    };

    const currentPosition = () => new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('La géolocalisation n’est pas disponible sur cet appareil.'));
        return;
      }
      navigator.geolocation.getCurrentPosition(resolve, () => {
        reject(new Error('Autorisez la localisation pour afficher les annonces proches.'));
      }, {enableHighAccuracy: false, timeout: 12000, maximumAge: 300000});
    });

    const parseJsonResponse = async (response) => {
      const text = await response.text();
      try {
        return JSON.parse(text);
      } catch (error) {
        // Une page HTML, un warning PHP ou une redirection ne doit jamais être
        // affiché tel quel dans la carte.
        throw new Error('Le serveur a renvoyé une réponse invalide. Rechargez la page puis réessayez.');
      }
    };

    const render = async (payload, position) => {
      const L = await ensureLeaflet();
      panel.hidden = false;
      message.hidden = true;

      if (!map) {
        map = L.map(root, {scrollWheelZoom: false}).setView([
          position.coords.latitude,
          position.coords.longitude,
        ], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap',
        }).addTo(map);
        markers = L.featureGroup().addTo(map);
      }

      markers.clearLayers();
      const bounds = [];
      const userPoint = [position.coords.latitude, position.coords.longitude];
      L.circleMarker(userPoint, {radius: 7, weight: 3}).bindTooltip('Votre position').addTo(markers);
      bounds.push(userPoint);

      payload.annonces.forEach((annonce) => {
        const lat = Number(annonce.mapLatitude);
        const lng = Number(annonce.mapLongitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const point = [lat, lng];
        const salary = annonce.dailySalary == null
          ? 'Tarif à convenir'
          : `${new Intl.NumberFormat('fr-FR').format(annonce.dailySalary)} FCFA/jour`;
        const popup = `
          <div class="anpub-map-popup">
            <strong>${escapeHtml(annonce.title)}</strong>
            <p>${escapeHtml(annonce.profession)} · ${escapeHtml(annonce.city)}</p>
            <p>${escapeHtml(salary)} · ${escapeHtml(annonce.distanceKm)} km</p>
            <a href="${escapeHtml(annonce.url)}">Voir l’annonce</a>
          </div>`;
        L.marker(point).bindPopup(popup).addTo(markers);
        bounds.push(point);
      });

      if (bounds.length > 1) map.fitBounds(bounds, {padding: [35, 35], maxZoom: 13});
      else map.setView(userPoint, 10);
      window.setTimeout(() => map.invalidateSize(), 80);

      if (summary) {
        summary.textContent = `${payload.count} annonce(s) trouvée(s) dans un rayon de ${radius.value} km.`;
      }
      panel.scrollIntoView({behavior: 'smooth', block: 'start'});
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      setLoading(true);
      panel.hidden = false;
      setMessage('Recherche des annonces proches…');

      try {
        const position = await currentPosition();
        const response = await fetch(root.dataset.searchUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type': 'application/json', Accept: 'application/json'},
          body: JSON.stringify({
            _token: root.dataset.csrfToken,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            radius: radius.value,
            q: document.getElementById('annonce_q')?.value.trim() || '',
            city: document.getElementById('annonce_city')?.value.trim() || '',
          }),
        });
        const payload = await parseJsonResponse(response);
        if (!response.ok || payload.ok === false) {
          throw new Error(payload.message || 'La recherche a échoué.');
        }
        await render(payload, position);
      } catch (error) {
        setMessage(error.message || 'La carte est momentanément indisponible.', true);
      } finally {
        setLoading(false);
      }
    });

    closeButton?.addEventListener('click', () => {
      panel.hidden = true;
    });
  };

  document.addEventListener('DOMContentLoaded', boot, {once: true});
  document.addEventListener('turbo:load', boot);
})();
