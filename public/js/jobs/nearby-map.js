(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatSalary(min, max) {
        const format = (value) => new Intl.NumberFormat('fr-FR').format(Number(value)) + ' FCFA';

        if (min !== null && max !== null) {
            return min === max ? format(min) : format(min) + ' – ' + format(max);
        }
        if (min !== null) return 'Dès ' + format(min);
        if (max !== null) return 'Jusqu’à ' + format(max);

        return 'Salaire à convenir';
    }

    function bootJobNearbyMap() {
        const form = document.getElementById('jobNearbySearchForm');
        const button = document.getElementById('jobNearbyMapButton');
        const section = document.getElementById('jobNearbyMapSection');
        const mapElement = document.getElementById('jobNearbyMap');

        if (!form || !button || !section || !mapElement || mapElement.dataset.bound === '1') return;
        mapElement.dataset.bound = '1';

        const closeButton = document.getElementById('jobNearbyMapClose');
        const summary = document.getElementById('jobNearbyMapSummary');
        const status = document.getElementById('jobNearbyStatus');
        const latitudeInput = document.getElementById('jobSearchLatitude');
        const longitudeInput = document.getElementById('jobSearchLongitude');
        const radiusInput = document.getElementById('jobNearbyRadius');
        const queryInput = document.getElementById('jobNearbyQ');
        const cityInput = document.getElementById('jobNearbyCity');

        let map = null;
        let resultLayer = null;
        let activeRequest = null;

        const setStatus = (message, error) => {
            if (!status) return;
            status.textContent = message;
            status.classList.toggle('is-error', Boolean(error));
        };

        const setSummary = (message) => {
            if (summary) summary.textContent = message;
        };

        const resizeMap = () => {
            if (!map || section.hidden) return;
            window.requestAnimationFrame(() => map.invalidateSize(false));
            window.setTimeout(() => map && map.invalidateSize(false), 180);
        };

        const showMap = () => {
            section.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            resizeMap();
        };

        const showMapError = (message) => {
            showMap();
            setSummary(message);
            setStatus(message, true);
            if (!map) {
                mapElement.innerHTML = '<div class="mol-job-map-error"><i class="fa fa-map-marker" aria-hidden="true"></i><strong>Carte indisponible</strong><span>' + escapeHtml(message) + '</span></div>';
            }
        };

        const ensureMap = (latitude, longitude) => {
            if (!window.L) {
                throw new Error('La carte n’a pas pu être chargée. Vérifiez votre connexion puis rechargez la page.');
            }

            if (!map) {
                map = window.L.map(mapElement, {
                    zoomControl: true,
                    scrollWheelZoom: false,
                    preferCanvas: true
                }).setView([latitude, longitude], 13);

                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                resultLayer = window.L.layerGroup().addTo(map);
            }

            resizeMap();
            return map;
        };

        const renderResults = (payload, latitude, longitude, radius) => {
            const leafletMap = ensureMap(latitude, longitude);
            resultLayer.clearLayers();

            const boundsItems = [];
            const radiusCircle = window.L.circle([latitude, longitude], {
                radius: radius * 1000,
                color: '#155eef',
                weight: 2,
                opacity: 0.62,
                fillColor: '#60a5fa',
                fillOpacity: 0.08,
                interactive: false
            }).addTo(resultLayer);
            boundsItems.push(radiusCircle);

            const userMarker = window.L.circleMarker([latitude, longitude], {
                radius: 8,
                color: '#ffffff',
                weight: 3,
                fillColor: '#155eef',
                fillOpacity: 1
            }).addTo(resultLayer).bindTooltip('Votre position');
            boundsItems.push(userMarker);

            (payload.jobs || []).forEach((job) => {
                const marker = window.L.circleMarker([job.mapLatitude, job.mapLongitude], {
                    radius: 9,
                    color: '#ffffff',
                    weight: 3,
                    fillColor: '#43a700',
                    fillOpacity: 1
                }).addTo(resultLayer);

                const popup = [
                    '<div class="mol-job-map-popup">',
                    '<h4>' + escapeHtml(job.title) + '</h4>',
                    '<p><strong>' + escapeHtml(job.profession) + '</strong> · ' + escapeHtml(job.type) + '</p>',
                    '<p><i class="fa fa-map-marker" aria-hidden="true"></i> ' + escapeHtml(job.city) + ' · ' + escapeHtml(job.distanceKm) + ' km</p>',
                    '<p class="mol-job-map-salary">' + escapeHtml(formatSalary(job.salaryMin, job.salaryMax)) + '</p>',
                    '<a href="' + escapeHtml(job.url) + '" data-turbo="false">Voir l’offre</a>',
                    '</div>'
                ].join('');

                marker.bindPopup(popup, { maxWidth: 285 });
                boundsItems.push(marker);
            });

            const bounds = window.L.featureGroup(boundsItems).getBounds();
            if (bounds.isValid()) {
                leafletMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
            }

            const count = Number(payload.count || 0);
            const label = count === 0
                ? 'Aucune offre géolocalisée dans ce rayon.'
                : count + ' offre' + (count > 1 ? 's' : '') + ' trouvée' + (count > 1 ? 's' : '') + ' dans un rayon de ' + radius + ' km.';
            setSummary(label);
            setStatus(label, false);
            showMap();
        };

        const loadMap = async () => {
            if (!navigator.geolocation || !window.isSecureContext) {
                showMapError('La géolocalisation nécessite une connexion HTTPS et l’autorisation de votre navigateur.');
                return;
            }

            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            setStatus('Détection de votre position…', false);
            setSummary('Détection de votre position…');
            showMap();

            navigator.geolocation.getCurrentPosition(async (position) => {
                const latitude = Number(position.coords.latitude);
                const longitude = Number(position.coords.longitude);
                const radius = Math.max(10, Math.min(50, Number(radiusInput?.value || 10)));

                if (latitudeInput) latitudeInput.value = latitude.toFixed(8);
                if (longitudeInput) longitudeInput.value = longitude.toFixed(8);
                setStatus('Position détectée. Chargement des offres…', false);
                setSummary('Chargement des offres dans votre rayon…');

                if (activeRequest) activeRequest.abort();
                activeRequest = new AbortController();

                try {
                    const response = await fetch(mapElement.dataset.searchUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            _token: mapElement.dataset.csrfToken,
                            latitude,
                            longitude,
                            radius,
                            q: queryInput?.value.trim() || '',
                            city: cityInput?.value.trim() || ''
                        }),
                        signal: activeRequest.signal
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || !payload.ok) {
                        throw new Error(payload.message || 'Impossible de charger les offres sur la carte.');
                    }

                    renderResults(payload, latitude, longitude, radius);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        showMapError(error.message || 'Impossible de charger les offres sur la carte.');
                    }
                } finally {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                }
            }, (error) => {
                const messages = {
                    1: 'Localisation refusée. Autorisez votre position pour afficher les offres proches.',
                    2: 'Votre position est momentanément indisponible.',
                    3: 'La détection de position a pris trop de temps. Réessayez.'
                };
                showMapError(messages[error.code] || 'Impossible de détecter votre position.');
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }, {
                enableHighAccuracy: false,
                timeout: 12000,
                maximumAge: 300000
            });
        };

        button.addEventListener('click', loadMap);
        closeButton?.addEventListener('click', () => {
            section.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            button.focus();
        });
        radiusInput?.addEventListener('change', () => {
            if (!section.hidden) loadMap();
        });

        window.addEventListener('resize', resizeMap, { passive: true });
        window.addEventListener('orientationchange', resizeMap, { passive: true });
        if ('ResizeObserver' in window) {
            new ResizeObserver(resizeMap).observe(mapElement);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootJobNearbyMap, { once: true });
    } else {
        bootJobNearbyMap();
    }
    document.addEventListener('turbo:load', bootJobNearbyMap);
})();
