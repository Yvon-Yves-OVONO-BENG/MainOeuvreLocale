(function () {
    'use strict';

    // Avec Turbo, ce fichier peut etre rencontre plusieurs fois alors que la
    // page precedente a deja initialise le controleur. Dans ce cas, on relie
    // simplement le nouveau formulaire au controleur existant.
    if (
        window.MOL_TALENT_NEARBY_MAP_CONTROLLER &&
        typeof window.MOL_TALENT_NEARBY_MAP_CONTROLLER.bind === 'function'
    ) {
        window.MOL_TALENT_NEARBY_MAP_CONTROLLER.bind();
        return;
    }

    const CACHE_KEY = 'mol_nearby_last_position';
    const CACHE_MAX_AGE = 30 * 60 * 1000;
    let map = null;
    let mapContainer = null;
    let markerLayer = null;
    let radiusLayer = null;
    let currentRequest = null;
    let searchSequence = 0;

    function setSummary(message) {
        const summary = document.getElementById('vipNearbyTalentMapSummary');
        if (summary) summary.textContent = message;
    }

    function readCachedPosition() {
        try {
            const value = JSON.parse(window.localStorage.getItem(CACHE_KEY) || 'null');
            if (!value || Date.now() - Number(value.savedAt || 0) > CACHE_MAX_AGE) return null;
            if (!Number.isFinite(Number(value.latitude)) || !Number.isFinite(Number(value.longitude))) return null;
            return { latitude: Number(value.latitude), longitude: Number(value.longitude), cached: true };
        } catch (error) {
            return null;
        }
    }

    function savePosition(position) {
        try {
            window.localStorage.setItem(CACHE_KEY, JSON.stringify({
                latitude: position.latitude,
                longitude: position.longitude,
                savedAt: Date.now()
            }));
        } catch (error) {
            // Le mode privé peut interdire localStorage : la recherche continue.
        }
    }

    function positionAttempt(options) {
        return new Promise(function (resolve, reject) {
            navigator.geolocation.getCurrentPosition(function (position) {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    cached: false
                });
            }, reject, options);
        });
    }

    async function getAutomaticPosition() {
        if (!navigator.geolocation) {
            const cached = readCachedPosition();
            if (cached) return cached;
            throw new Error('La géolocalisation n’est pas prise en charge par ce téléphone.');
        }

        // Sur les anciens téléphones, la position réseau est souvent plus rapide
        // que le GPS haute précision. Une seconde tentative GPS est automatique.
        try {
            const position = await positionAttempt({
                enableHighAccuracy: false,
                timeout: 30000,
                maximumAge: 10 * 60 * 1000
            });
            savePosition(position);
            return position;
        } catch (firstError) {
            if (firstError && firstError.code === 1) {
                throw new Error('Autorisez la localisation dans votre navigateur pour afficher la carte.');
            }

            setSummary('La première détection a échoué. Nouvelle tentative automatique en cours…');

            try {
                const position = await positionAttempt({
                    enableHighAccuracy: true,
                    timeout: 45000,
                    maximumAge: 0
                });
                savePosition(position);
                return position;
            } catch (secondError) {
                const cached = readCachedPosition();
                if (cached) return cached;

                if (secondError && secondError.code === 1) {
                    throw new Error('Autorisez la localisation dans votre navigateur pour afficher la carte.');
                }

                throw new Error('Position indisponible. Activez la localisation du téléphone puis touchez de nouveau Rechercher.');
            }
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateRangeVisual(input) {
        if (!input) return;

        const min = Number(input.min) || 10;
        const max = Number(input.max) || 50;
        const value = Math.max(min, Math.min(max, Number(input.value) || min));
        const progress = max > min ? ((value - min) / (max - min)) * 100 : 0;
        const field = input.closest('.vip-directory-range-field');

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
        if (currentRequest) {
            currentRequest.abort();
            currentRequest = null;
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

    function ensureMap(container, position, radius) {
        if (typeof window.L === 'undefined') {
            throw new Error('La carte n’a pas pu être chargée. Vérifiez votre connexion internet.');
        }

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

        window.L.circleMarker(center, {
            radius: 8,
            color: '#ffffff',
            weight: 3,
            fillColor: '#155eef',
            fillOpacity: 1
        }).addTo(markerLayer).bindPopup('Votre position');

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

    function renderTalents(talents) {
        const bounds = [];

        talents.forEach(function (talent) {
            const lat = Number(talent.mapLatitude);
            const lng = Number(talent.mapLongitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

            bounds.push([lat, lng]);
            const profileButton = talent.profileUrl
                ? '<a class="mol-talent-map-profile-btn" href="' + escapeHtml(talent.profileUrl) + '" data-turbo="false">'
                    + '<i class="fa fa-address-card" aria-hidden="true"></i> Afficher le profil'
                    + '</a>'
                : '';
            const html = '<div class="mol-talent-map-popup">'
                + '<h4>' + escapeHtml(talent.name || 'Talent') + '</h4>'
                + '<p><strong>' + escapeHtml(talent.profession || 'Profession non renseignée') + '</strong></p>'
                + '<p><i class="fa fa-location-dot"></i> ' + escapeHtml(talent.city || talent.country || 'Localisation disponible') + '</p>'
                + '<p>' + escapeHtml(talent.distanceKm) + ' km de vous</p>'
                + profileButton
                + '</div>';

            window.L.marker([lat, lng]).addTo(markerLayer).bindPopup(html);
        });

        return bounds;
    }

    async function runSearch() {
        const activeSearch = ++searchSequence;
        const section = document.getElementById('vipNearbyTalentMapSection');
        const container = document.getElementById('vipNearbyTalentMap');
        const button = document.getElementById('vipTalentSearchBtn');
        const qInput = document.getElementById('vipTalentSearch');
        const cityInput = document.getElementById('vipTalentCity');
        const radiusInput = document.getElementById('vipTalentRadius');

        if (!section || !container || !radiusInput) return;

        section.hidden = false;
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setSummary('Détection automatique de votre position…');
        updateRangeVisual(radiusInput);
        setButtonLoading(button, true);

        try {
            await waitForVisibleContainer(container);
            const position = await getAutomaticPosition();
            if (activeSearch !== searchSequence) return;

            const radius = Math.max(10, Math.min(50, Number(radiusInput.value) || 10));
            ensureMap(container, position, radius);
            setSummary('Position trouvée. Recherche des talents…');

            if (currentRequest) currentRequest.abort();
            currentRequest = new AbortController();

            const response = await fetch(container.dataset.searchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: currentRequest.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    _token: container.dataset.csrfToken,
                    latitude: position.latitude,
                    longitude: position.longitude,
                    radius: radius,
                    q: qInput ? qInput.value.trim() : '',
                    city: cityInput ? cityInput.value.trim() : ''
                })
            });
            const data = await response.json().catch(function () { return {}; });
            if (activeSearch !== searchSequence) return;
            if (!response.ok || !data.ok) throw new Error(data.message || 'La recherche a échoué.');

            const talents = Array.isArray(data.talents) ? data.talents : [];
            renderTalents(talents);
            setSummary(talents.length
                ? talents.length + ' talent' + (talents.length > 1 ? 's' : '') + ' trouvé' + (talents.length > 1 ? 's' : '') + ' dans un rayon de ' + radius + ' km.'
                : 'Aucun talent géolocalisé ne correspond dans ce rayon.');
            safeInvalidateSize(map, container);
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            setSummary(error && error.message ? error.message : 'La recherche a échoué.');
        } finally {
            if (activeSearch === searchSequence) setButtonLoading(button, false);
        }
    }

    function onSearchButtonClick(event) {
        event.preventDefault();
        runSearch();
    }

    function onSearchFormSubmit(event) {
        event.preventDefault();
        runSearch();
    }

    function onCloseMap() {
        const section = document.getElementById('vipNearbyTalentMapSection');
        if (section) section.hidden = true;
    }

    function onRadiusInput(event) {
        const output = document.getElementById('vipTalentRadiusValue');
        if (output) output.textContent = event.currentTarget.value + ' km';
        updateRangeVisual(event.currentTarget);
    }

    function bindControls() {
        const form = document.getElementById('vipTalentSearchForm');
        const button = document.getElementById('vipTalentSearchBtn');
        const closeButton = document.getElementById('vipNearbyTalentMapClose');
        const radiusInput = document.getElementById('vipTalentRadius');

        // Ne pas utiliser data-* comme marqueur ici : Turbo clone ces
        // attributs dans son cache, mais pas les vrais event listeners.
        if (form && !form.__molNearbyMapBound) {
            form.__molNearbyMapBound = true;
            form.addEventListener('submit', onSearchFormSubmit);
        }

        if (button && !button.__molNearbyMapBound) {
            button.__molNearbyMapBound = true;
            button.addEventListener('click', onSearchButtonClick);
        }

        if (closeButton && !closeButton.__molNearbyMapBound) {
            closeButton.__molNearbyMapBound = true;
            closeButton.addEventListener('click', onCloseMap);
        }

        if (radiusInput) {
            updateRangeVisual(radiusInput);

            if (!radiusInput.__molNearbyMapBound) {
                radiusInput.__molNearbyMapBound = true;
                radiusInput.addEventListener('input', onRadiusInput);
            }
        }
    }

    window.MOL_TALENT_NEARBY_MAP_CONTROLLER = {
        bind: bindControls,
        destroy: destroyMap,
        search: runSearch
    };

    bindControls();
    document.addEventListener('DOMContentLoaded', bindControls);
    document.addEventListener('turbo:load', bindControls);
    document.addEventListener('turbo:render', bindControls);
    document.addEventListener('turbo:before-cache', destroyMap);
})();
