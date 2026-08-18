(function () {
    'use strict';

    if (window.MOL_ANNONCE_NEARBY_MAP_READY) return;
    window.MOL_ANNONCE_NEARBY_MAP_READY = true;

    const CACHE_KEY = 'mol_nearby_last_position';
    const CACHE_MAX_AGE = 30 * 60 * 1000;
    let map = null;
    let mapContainer = null;
    let markerLayer = null;
    let radiusLayer = null;
    let requestController = null;
    let searchSequence = 0;

    function summary(message) {
        const element = document.getElementById('annonceNearbyMapSummary');
        if (element) element.textContent = message;
    }

    function readCache() {
        try {
            const value = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
            if (!value || Date.now() - Number(value.savedAt || 0) > CACHE_MAX_AGE) return null;
            const latitude = Number(value.latitude);
            const longitude = Number(value.longitude);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;
            return { latitude: latitude, longitude: longitude };
        } catch (error) {
            return null;
        }
    }

    function saveCache(position) {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify({
                latitude: position.latitude,
                longitude: position.longitude,
                savedAt: Date.now()
            }));
        } catch (error) {}
    }

    function locate(options) {
        return new Promise(function (resolve, reject) {
            navigator.geolocation.getCurrentPosition(function (position) {
                resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude });
            }, reject, options);
        });
    }

    async function automaticPosition() {
        if (!navigator.geolocation) {
            const cached = readCache();
            if (cached) return cached;
            throw new Error('Ce téléphone ne prend pas en charge la géolocalisation.');
        }

        try {
            const position = await locate({ enableHighAccuracy: false, timeout: 30000, maximumAge: 600000 });
            saveCache(position);
            return position;
        } catch (firstError) {
            if (firstError && firstError.code === 1) throw new Error('Autorisez la localisation pour afficher les annonces sur la carte.');
            summary('Nouvelle tentative automatique de localisation…');

            try {
                const position = await locate({ enableHighAccuracy: true, timeout: 45000, maximumAge: 0 });
                saveCache(position);
                return position;
            } catch (secondError) {
                const cached = readCache();
                if (cached) return cached;
                if (secondError && secondError.code === 1) throw new Error('Autorisez la localisation pour afficher les annonces sur la carte.');
                throw new Error('Position indisponible. Activez la localisation du téléphone puis réessayez.');
            }
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function updateRangeVisual(input) {
        if (!input) return;

        const min = Number(input.min) || 10;
        const max = Number(input.max) || 50;
        const value = Math.max(min, Math.min(max, Number(input.value) || min));
        const progress = max > min ? ((value - min) / (max - min)) * 100 : 0;
        const field = input.closest('.anpub-range');

        input.style.setProperty('--mol-range-progress', progress + '%');
        if (field) field.style.setProperty('--mol-range-progress', progress + '%');
    }

    function setButtonLoading(button, loading) {
        if (!button) return;

        const icon = button.querySelector('.mol-search-button__icon');
        const label = button.querySelector('.mol-search-button__label');

        button.disabled = loading;
        button.classList.toggle('is-loading', loading);
        button.setAttribute('aria-busy', loading ? 'true' : 'false');

        if (icon) {
            if (!icon.dataset.originalClass) icon.dataset.originalClass = icon.className;
            icon.className = loading
                ? 'fa-solid fa-spinner fa-spin mol-search-button__icon'
                : icon.dataset.originalClass;
        }

        if (label) {
            if (!label.dataset.originalText) label.dataset.originalText = label.textContent.trim();
            label.textContent = loading ? 'Recherche…' : label.dataset.originalText;
        }
    }

    function waitForVisibleContainer(container) {
        return new Promise(function (resolve) {
            let attempts = 0;

            function checkSize() {
                attempts += 1;

                if ((container.clientWidth > 0 && container.clientHeight > 0) || attempts >= 12) {
                    resolve();
                    return;
                }

                window.requestAnimationFrame(checkSize);
            }

            window.requestAnimationFrame(checkSize);
        });
    }

    function destroyMap() {
        if (requestController) {
            requestController.abort();
            requestController = null;
        }

        if (map) {
            try {
                map.stop();
                map.remove();
            } catch (error) {
                // La page peut avoir été remplacée par Turbo avant le nettoyage.
            }
        }

        map = null;
        mapContainer = null;
        markerLayer = null;
        radiusLayer = null;
    }

    function safeInvalidateSize(activeMap, container) {
        window.setTimeout(function () {
            if (map !== activeMap || !document.documentElement.contains(container)) return;

            try {
                activeMap.invalidateSize({ animate: false, pan: false });
            } catch (error) {
                // Le conteneur a pu être remplacé entre-temps par une navigation AJAX/Turbo.
            }
        }, 100);
    }

    function prepareMap(container, position, radius) {
        if (typeof window.L === 'undefined') throw new Error('La carte n’a pas pu être chargée. Vérifiez votre connexion.');

        const center = [position.latitude, position.longitude];

        if (map && (mapContainer !== container || !document.documentElement.contains(mapContainer))) {
            destroyMap();
        }

        if (!map) {
            mapContainer = container;
            map = window.L.map(container, {
                scrollWheelZoom: false,
                zoomAnimation: false,
                fadeAnimation: false,
                markerZoomAnimation: false
            }).setView(center, 12, { animate: false });

            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            markerLayer = window.L.layerGroup().addTo(map);
        } else {
            map.stop();
            map.invalidateSize({ animate: false, pan: false });
            map.setView(center, map.getZoom() || 12, { animate: false });
        }

        markerLayer.clearLayers();

        window.L.circleMarker(center, { radius: 8, color: '#fff', weight: 3, fillColor: '#155eef', fillOpacity: 1 })
            .addTo(markerLayer).bindPopup('Votre position');

        if (!radiusLayer) {
            radiusLayer = window.L.circle(center, {
                radius: radius * 1000,
                color: '#155eef',
                weight: 2,
                fillColor: '#155eef',
                fillOpacity: 0.07
            }).addTo(map);
        } else {
            radiusLayer.setLatLng(center).setRadius(radius * 1000);
        }

        map.fitBounds(radiusLayer.getBounds(), { padding: [20, 20], animate: false });
        safeInvalidateSize(map, container);
    }

    function addAnnouncements(items) {
        items.forEach(function (item) {
            const lat = Number(item.mapLatitude);
            const lng = Number(item.mapLongitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            const salary = item.dailySalary
                ? new Intl.NumberFormat('fr-FR').format(Number(item.dailySalary)) + ' FCFA/jour'
                : 'Tarif à convenir';
            const html = '<div class="anpub-map-popup">'
                + '<h4>' + escapeHtml(item.title) + '</h4>'
                + '<p><strong>' + escapeHtml(item.profession) + '</strong> · ' + escapeHtml(item.city) + '</p>'
                + '<p>' + escapeHtml(salary) + ' · ' + escapeHtml(item.distanceKm) + ' km</p>'
                + '<p>' + escapeHtml(item.description) + '</p>'
                + '<a href="' + escapeHtml(item.url) + '" data-turbo="false">Voir l’annonce</a>'
                + '</div>';
            window.L.marker([lat, lng]).addTo(markerLayer).bindPopup(html);
        });
    }

    async function search() {
        const activeSearch = ++searchSequence;
        const section = document.getElementById('annonceNearbyMapSection');
        const container = document.getElementById('annonceNearbyMap');
        const q = document.getElementById('annonce_q');
        const city = document.getElementById('annonce_city');
        const radiusInput = document.getElementById('annonce_radius');
        const button = document.getElementById('annonceNearbySearchButton');
        if (!section || !container || !radiusInput) return;

        section.hidden = false;
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
        summary('Détection automatique de votre position…');
        updateRangeVisual(radiusInput);
        setButtonLoading(button, true);

        try {
            await waitForVisibleContainer(container);
            const position = await automaticPosition();
            if (activeSearch !== searchSequence) return;

            const radius = Math.max(10, Math.min(50, Number(radiusInput.value) || 10));
            prepareMap(container, position, radius);
            summary('Position trouvée. Recherche des annonces…');

            if (requestController) requestController.abort();
            requestController = new AbortController();
            const response = await fetch(container.dataset.searchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: requestController.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    _token: container.dataset.csrfToken,
                    latitude: position.latitude,
                    longitude: position.longitude,
                    radius: radius,
                    q: q ? q.value.trim() : '',
                    city: city ? city.value.trim() : ''
                })
            });
            const data = await response.json().catch(function () { return {}; });
            if (activeSearch !== searchSequence) return;
            if (!response.ok || !data.ok) throw new Error(data.message || 'La recherche a échoué.');
            const items = Array.isArray(data.annonces) ? data.annonces : [];
            addAnnouncements(items);
            summary(items.length
                ? items.length + ' annonce' + (items.length > 1 ? 's' : '') + ' trouvée' + (items.length > 1 ? 's' : '') + ' dans un rayon de ' + radius + ' km.'
                : 'Aucune annonce géolocalisée ne correspond dans ce rayon.');
            safeInvalidateSize(map, container);
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            summary(error && error.message ? error.message : 'La recherche a échoué.');
        } finally {
            if (activeSearch === searchSequence) setButtonLoading(button, false);
        }
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        if (event.target.closest('#annonceNearbySearchButton')) {
            event.preventDefault();
            search();
        } else if (event.target.closest('#annonceNearbyMapClose')) {
            const section = document.getElementById('annonceNearbyMapSection');
            if (section) section.hidden = true;
        }
    });

    document.addEventListener('submit', function (event) {
        if (event.target && event.target.id === 'annonceNearbySearchForm') {
            event.preventDefault();
            search();
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target && event.target.id === 'annonce_radius') {
            const output = document.getElementById('annonceRadiusValue');
            if (output) output.textContent = event.target.value + ' km';
            updateRangeVisual(event.target);
        }
    });

    const initialRadius = document.getElementById('annonce_radius');
    if (initialRadius) updateRangeVisual(initialRadius);

    document.addEventListener('turbo:before-cache', destroyMap);
})();
