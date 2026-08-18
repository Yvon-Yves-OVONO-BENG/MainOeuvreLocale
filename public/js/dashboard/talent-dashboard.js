/**
 * Tableau de bord Talent
 * Regroupe les interactions du bandeau d'actions, de la carte Leaflet,
 * du calendrier de disponibilité et des composants repliables.
 */

'use strict';

if (!window.MolTalentDashboardAssetsLoaded) {
	window.MolTalentDashboardAssetsLoaded = true;

	(function () {
		const selector = '[data-progress-value]';

		const normalizePercentage = (value) => {
			const parsed = Number.parseFloat(String(value ?? '').replace(',', '.'));

			if (!Number.isFinite(parsed)) {
				return 0;
			}

			return Math.min(100, Math.max(0, parsed));
		};

		const initProgressBars = () => {
			document.querySelectorAll(selector).forEach((element) => {
				const percentage = normalizePercentage(element.dataset.progressValue);
				element.style.width = `${percentage}%`;
			});
		};

		document.addEventListener('DOMContentLoaded', initProgressBars);
		document.addEventListener('turbo:load', initProgressBars);

		if (document.readyState !== 'loading') {
			initProgressBars();
		}
	})();

	(function () {
	                const selector = '.dashboard-publish-actions--below-hero .dashboard-action-link';

	                const resetActionButtons = () => {
	                    document.querySelectorAll(selector).forEach((link) => {
	                        const icon = link.querySelector('.dashboard-publish-icon, .dashboard-boost-icon');

	                        link.classList.remove('is-loading');
	                        link.removeAttribute('aria-busy');
	                        link.removeAttribute('aria-disabled');

	                        if (icon && icon.dataset.originalIconHtml !== undefined) {
	                            icon.innerHTML = icon.dataset.originalIconHtml;
	                        }
	                    });
	                };

	                const initActionSpinners = () => {
	                    document.querySelectorAll(selector).forEach((link) => {
	                        if (link.dataset.spinnerInitialized === '1') return;
	                        link.dataset.spinnerInitialized = '1';

	                        const icon = link.querySelector('.dashboard-publish-icon, .dashboard-boost-icon');
	                        if (icon && icon.dataset.originalIconHtml === undefined) {
	                            icon.dataset.originalIconHtml = icon.innerHTML;
	                        }

	                        link.addEventListener('click', function (event) {
	                            const isNormalClick = event.button === 0
	                                && !event.ctrlKey
	                                && !event.metaKey
	                                && !event.shiftKey
	                                && !event.altKey;

	                            if (!isNormalClick || event.defaultPrevented || link.classList.contains('is-loading')) {
	                                return;
	                            }

	                            const href = link.href;
	                            if (!href) return;

	                            event.preventDefault();
	                            link.classList.add('is-loading');
	                            link.setAttribute('aria-busy', 'true');
	                            link.setAttribute('aria-disabled', 'true');

	                            if (icon) {
	                                icon.innerHTML = '<span class="spinner-border spinner-border-sm dashboard-action-spinner" aria-hidden="true"></span>';
	                            }

	                            /* Petit délai pour garantir l'affichage visuel du spinner avant la navigation. */
	                            window.setTimeout(() => {
	                                window.location.assign(href);
	                            }, 100);
	                        });
	                    });
	                };

	                document.addEventListener('DOMContentLoaded', initActionSpinners);
	                document.addEventListener('turbo:load', function () {
	                    resetActionButtons();
	                    initActionSpinners();
	                });
	                window.addEventListener('pageshow', resetActionButtons);

	                if (document.readyState !== 'loading') {
	                    initActionSpinners();
	                }
	            })();
        

			(function () {
				window.MolDashboardJobsMap = window.MolDashboardJobsMap || {
					map: null,
					mapElement: null,
					eventsBound: false
				};

				const state = window.MolDashboardJobsMap;
				const defaultCenter = [3.8480, 11.5021];

				const escapeHtml = (value) => String(value ?? '')
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#039;');

				const validCoordinates = (latitude, longitude) => {
					const lat = Number(latitude);
					const lng = Number(longitude);

					if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
					if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;

					return [lat, lng];
				};

				const getBrowserPosition = () => new Promise((resolve) => {
					if (!navigator.geolocation) {
						resolve(null);
						return;
					}

					navigator.geolocation.getCurrentPosition(
						(position) => resolve([
							position.coords.latitude,
							position.coords.longitude
						]),
						() => resolve(null),
						{
							enableHighAccuracy: false,
							timeout: 9000,
							maximumAge: 300000
						}
					);
				});

				const geocodeCity = async (city) => {
					const cityName = String(city || '').trim();
					if (!cityName) return null;

					try {
						const query = encodeURIComponent(`${cityName}, Cameroun`);
						const response = await fetch(
							`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=cm&q=${query}`,
							{ headers: { 'Accept': 'application/json' } }
						);
						if (!response.ok) return null;

						const results = await response.json();
						if (!Array.isArray(results) || results.length === 0) return null;

						return validCoordinates(results[0].lat, results[0].lon);
					} catch (error) {
						return null;
					}
				};

				const jobPopup = (job) => {
					const meta = [job.city, job.profession, job.type].filter(Boolean).map(escapeHtml).join(' • ');

					return `<div class="dashboard-job-popup">
						<div class="dashboard-job-popup__title">${escapeHtml(job.title || 'Offre')}</div>
						${meta ? `<div class="dashboard-job-popup__meta">📍 ${meta}</div>` : ''}
						<a class="dashboard-job-popup__link" href="${escapeHtml(job.url || '#')}" data-turbo="false">Voir l’offre</a>
					</div>`;
				};

				const groupedCityPopup = (city, jobs) => {
					const links = jobs.map((job) => (
						`<div class="dashboard-job-popup__item">
							<div class="dashboard-job-popup__item-title">${escapeHtml(job.title || 'Offre')}</div>
							<a class="dashboard-job-popup__link" href="${escapeHtml(job.url || '#')}" data-turbo="false">Voir l’offre</a>
						</div>`
					)).join('');

					return `<div class="dashboard-job-popup">
						<div class="dashboard-job-popup__title">📍 ${escapeHtml(city)}</div>
						<div class="dashboard-job-popup__meta">${jobs.length} offre(s) dans cette zone</div>
						${links}
					</div>`;
				};

				const updateToggle = (button, open, loading = false) => {
					const label = loading
						? (button.dataset.loadingLabel || 'Chargement…')
						: (open ? (button.dataset.closeLabel || 'Masquer la carte') : (button.dataset.openLabel || 'Carte'));

					button.setAttribute('aria-expanded', open.toString());
					button.classList.toggle('is-open', open);
					button.innerHTML = loading
						? `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>${escapeHtml(label)}</span>`
						: `<span aria-hidden="true">🗺️</span><span class="dashboard-nearby-map-toggle-label">${escapeHtml(label)}</span>`;
				};

				const initializeLeafletMap = async (mapElement, statusElement) => {
					if (state.map && state.mapElement === mapElement) {
						window.requestAnimationFrame(() => state.map.invalidateSize(false));
						return;
					}

					if (!window.L) {
						throw new Error('Leaflet indisponible');
					}

					if (state.map) {
						state.map.remove();
					}

					let jobs = [];
					try {
						jobs = JSON.parse(mapElement.dataset.jobs || '[]');
					} catch (error) {
						jobs = [];
					}

					const map = L.map(mapElement, {
						zoomControl: true,
						scrollWheelZoom: false,
						fadeAnimation: false,
						zoomAnimation: false,
						markerZoomAnimation: false
					}).setView(defaultCenter, 11);

					state.map = map;
					state.mapElement = mapElement;

					L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
						maxZoom: 19,
						attribution: '&copy; OpenStreetMap'
					}).addTo(map);

					const bounds = L.latLngBounds([]);
					let markerCount = 0;

					const browserPositionPromise = getBrowserPosition();
					const cityGroups = new Map();

					jobs.forEach((job) => {
						const coordinates = validCoordinates(job.latitude, job.longitude);

						if (coordinates) {
							L.marker(coordinates).addTo(map).bindPopup(jobPopup(job), { maxWidth: 290 });
							bounds.extend(coordinates);
							markerCount++;
							return;
						}

						const city = String(job.city || mapElement.dataset.city || '').trim();
						if (!city) return;

						const key = city.toLocaleLowerCase('fr');
						if (!cityGroups.has(key)) cityGroups.set(key, { city, jobs: [] });
						cityGroups.get(key).jobs.push(job);
					});

					const cityPromises = Array.from(cityGroups.values()).map(async (group) => {
						const coordinates = await geocodeCity(group.city);
						if (!coordinates || !mapElement.isConnected || state.map !== map) return;

						L.marker(coordinates).addTo(map).bindPopup(
							groupedCityPopup(group.city, group.jobs),
							{ maxWidth: 310 }
						);
						bounds.extend(coordinates);
						markerCount++;
					});

					const [browserPosition] = await Promise.all([
						browserPositionPromise,
						Promise.allSettled(cityPromises)
					]);

					if (!mapElement.isConnected || state.map !== map) return;

					if (browserPosition) {
						L.circleMarker(browserPosition, {
							radius: 8,
							color: '#ffffff',
							weight: 3,
							fillColor: '#0d6efd',
							fillOpacity: 1
						}).addTo(map).bindPopup('Vous êtes ici');
						bounds.extend(browserPosition);
					}

					if (bounds.isValid()) {
						map.fitBounds(bounds.pad(.18), { maxZoom: 13, animate: false });
					} else {
						const fallbackCity = mapElement.dataset.city || '';
						const fallbackCoordinates = await geocodeCity(fallbackCity);
						if (fallbackCoordinates && state.map === map) {
							map.setView(fallbackCoordinates, 12, { animate: false });
						}
					}

					window.requestAnimationFrame(() => map.invalidateSize(false));

					if (statusElement) {
						statusElement.textContent = markerCount > 0
							? `${jobs.length} opportunité(s) chargée(s)`
							: 'Aucune position d’offre disponible pour cette zone.';
					}
				};

				state.init = function () {
					const button = document.getElementById('dashboardJobsMapToggle');
					const panel = document.getElementById('dashboardJobsMapPanel');
					const mapElement = document.getElementById('dashboardJobsMap');
					const statusElement = document.getElementById('dashboardJobsMapStatus');

					if (!button || !panel || !mapElement || button.dataset.mapToggleInitialized === '1') return;
					button.dataset.mapToggleInitialized = '1';

					button.addEventListener('click', async (event) => {
						event.preventDefault();

						if (!panel.hidden) {
							panel.hidden = true;
							updateToggle(button, false);
							return;
						}

						panel.hidden = false;
						updateToggle(button, true, !state.map || state.mapElement !== mapElement);

						try {
							await initializeLeafletMap(mapElement, statusElement);
							updateToggle(button, true);
						} catch (error) {
							panel.hidden = true;
							updateToggle(button, false);
							window.location.href = button.href;
						}
					});
				};

				state.destroy = function () {
					if (state.map) {
						state.map.remove();
						state.map = null;
						state.mapElement = null;
					}

					const button = document.getElementById('dashboardJobsMapToggle');
					const panel = document.getElementById('dashboardJobsMapPanel');
					if (button) {
						button.removeAttribute('data-map-toggle-initialized');
						updateToggle(button, false);
					}
					if (panel) panel.hidden = true;
				};

				if (!state.eventsBound) {
					state.eventsBound = true;
					document.addEventListener('DOMContentLoaded', state.init);
					document.addEventListener('turbo:load', state.init);
					document.addEventListener('turbo:before-cache', state.destroy);
				}

				if (document.readyState !== 'loading') {
					state.init();
				}
			})();
	

			(function () {
				window.MolTalentAvailabilityCalendar = window.MolTalentAvailabilityCalendar || {};

				window.MolTalentAvailabilityCalendar.init = async function () {
					if (window.bootstrap && bootstrap.Tooltip) {
						document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
							bootstrap.Tooltip.getOrCreateInstance(el);
						});
					}

					const app = document.getElementById('availabilityCalendarApp');
					if (!app || app.dataset.calendarInitialized === '1') return;

					const badge = document.getElementById('availabilityBadge');
					const msg = document.getElementById('availabilityMsg');
					const monthLabel = document.getElementById('calendarMonthLabel');
					const grid = document.getElementById('availabilityCalendarGrid');
					const prevBtn = document.getElementById('calendarPrevMonth');
					const nextBtn = document.getElementById('calendarNextMonth');
					const saveBtn = document.getElementById('saveAvailabilityCalendar');
					const countEl = document.getElementById('availabilitySelectedCount');

					if (!monthLabel || !grid || !prevBtn || !nextBtn || !saveBtn || !countEl) {
						return;
					}

					app.dataset.calendarInitialized = '1';

					const loadUrl = app.dataset.loadUrl;
					const saveUrl = app.dataset.saveUrl;
					const csrf = app.dataset.csrf;
					const selectedDates = new Set();
					const today = new Date();
					const initialSaveMarkup = saveBtn.innerHTML;
					let currentYear = today.getFullYear();
					let currentMonth = today.getMonth();
					let messageTimer = null;

					const showMsg = (text, type = 'info') => {
						if (!msg) return;

						if (messageTimer) {
							window.clearTimeout(messageTimer);
						}

						msg.hidden = false;
						msg.className = 'small mt-3 dashboard-availability-message alert alert-' + type;
						msg.textContent = text;

						messageTimer = window.setTimeout(() => {
							msg.hidden = true;
						}, 4000);
					};

					const formatDate = (date) => {
						const y = date.getFullYear();
						const m = String(date.getMonth() + 1).padStart(2, '0');
						const d = String(date.getDate()).padStart(2, '0');
						return `${y}-${m}-${d}`;
					};

					const isPastDay = (date) => {
						const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
						const currentDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
						return day < currentDay;
					};

					const updateCount = () => {
						countEl.textContent = selectedDates.size;
					};

					const renderCalendar = () => {
						grid.innerHTML = '';

						const firstDay = new Date(currentYear, currentMonth, 1);
						const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();
						const monthNames = [
							'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
							'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
						];

						monthLabel.textContent = `${monthNames[currentMonth]} ${currentYear}`;

						let startWeekday = firstDay.getDay();
						startWeekday = startWeekday === 0 ? 7 : startWeekday;

						for (let i = 1; i < startWeekday; i++) {
							const empty = document.createElement('span');
							empty.className = 'availability-calendar-day is-empty';
							empty.setAttribute('aria-hidden', 'true');
							grid.appendChild(empty);
						}

						for (let day = 1; day <= totalDays; day++) {
							const dateObj = new Date(currentYear, currentMonth, day);
							const iso = formatDate(dateObj);
							const past = isPastDay(dateObj);
							const dayEl = document.createElement('button');

							dayEl.type = 'button';
							dayEl.className = 'availability-calendar-day';
							dayEl.textContent = String(day);
							dayEl.setAttribute('aria-label', `${day} ${monthNames[currentMonth]} ${currentYear}`);

							if (selectedDates.has(iso)) {
								dayEl.classList.add('is-selected');
								dayEl.setAttribute('aria-pressed', 'true');
							} else {
								dayEl.setAttribute('aria-pressed', 'false');
							}

							if (iso === formatDate(today)) {
								dayEl.classList.add('is-today');
							}

							if (past) {
								dayEl.classList.add('is-past');
								dayEl.disabled = true;
							} else {
								dayEl.addEventListener('click', () => {
									const isSelected = selectedDates.has(iso);

									if (isSelected) {
										selectedDates.delete(iso);
									} else {
										selectedDates.add(iso);
									}

									dayEl.classList.toggle('is-selected', !isSelected);
									dayEl.setAttribute('aria-pressed', (!isSelected).toString());
									updateCount();
								});
							}

							grid.appendChild(dayEl);
						}

						updateCount();
					};

					const loadSelectedDates = async () => {
						try {
							const res = await fetch(loadUrl, {
								credentials: 'same-origin',
								headers: { 'X-Requested-With': 'XMLHttpRequest' }
							});
							const data = await res.json().catch(() => ({}));

							if (!res.ok || !data.ok) {
								showMsg(data.message || 'Impossible de charger les disponibilités.', 'danger');
								return;
							}

							(data.dates || []).forEach((date) => {
								if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
									selectedDates.add(date);
								}
							});

							renderCalendar();
						} catch (error) {
							showMsg('Erreur réseau lors du chargement du calendrier.', 'danger');
						}
					};

					prevBtn.addEventListener('click', () => {
						currentMonth--;
						if (currentMonth < 0) {
							currentMonth = 11;
							currentYear--;
						}
						renderCalendar();
					});

					nextBtn.addEventListener('click', () => {
						currentMonth++;
						if (currentMonth > 11) {
							currentMonth = 0;
							currentYear++;
						}
						renderCalendar();
					});

					saveBtn.addEventListener('click', async () => {
						if (saveBtn.disabled) return;

						saveBtn.disabled = true;
						saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Enregistrement…';

						try {
							const res = await fetch(saveUrl, {
								method: 'POST',
								credentials: 'same-origin',
								headers: {
									'Content-Type': 'application/json',
									'X-Requested-With': 'XMLHttpRequest'
								},
								body: JSON.stringify({
									dates: Array.from(selectedDates).sort(),
									_token: csrf
								})
							});
							const data = await res.json().catch(() => ({}));

							if (!res.ok || !data.ok) {
								showMsg(data.message || 'Impossible d’enregistrer les disponibilités.', 'danger');
								return;
							}

							if (badge) {
								badge.textContent = data.statusLabel || '';
								badge.className = 'badge rounded-pill px-3 py-2 ' + (data.badgeClass || 'text-bg-secondary');
							}

							showMsg(data.message || 'Disponibilités enregistrées.', 'success');
						} catch (error) {
							showMsg('Erreur réseau. Réessayez.', 'danger');
						} finally {
							saveBtn.disabled = false;
							saveBtn.innerHTML = initialSaveMarkup;
						}
					});

					/* Le calendrier est visible immédiatement, même si l'API répond lentement. */
					renderCalendar();
					await loadSelectedDates();
				};

				if (!window.MolTalentAvailabilityCalendar.eventsBound) {
					window.MolTalentAvailabilityCalendar.eventsBound = true;

					document.addEventListener('DOMContentLoaded', () => {
						window.MolTalentAvailabilityCalendar.init();
					});

					document.addEventListener('turbo:load', () => {
						window.MolTalentAvailabilityCalendar.init();
					});
				}

				if (document.readyState !== 'loading') {
					window.MolTalentAvailabilityCalendar.init();
				}
			})();
	

			(function () {
				function initAvailabilityCollapse() {
					const btn = document.getElementById('availabilityCollapseBtn');
					const collapseEl = document.getElementById('availabilityCalendarCollapse');
					const icon = btn ? btn.querySelector('i') : null;

					if (!btn || !collapseEl) return;
					if (btn.dataset.collapseInit === '1') return;

					btn.dataset.collapseInit = '1';

					const updateButton = (isOpen) => {
						btn.setAttribute('aria-expanded', isOpen.toString());

						if (icon) {
							icon.classList.toggle('fa-chevron-up', isOpen);
							icon.classList.toggle('fa-chevron-down', !isOpen);
						}
					};

					const isInitiallyOpen = collapseEl.classList.contains('show');
					updateButton(isInitiallyOpen);

					if (window.bootstrap && bootstrap.Collapse) {
						const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });

						btn.addEventListener('click', function () {
							bsCollapse.toggle();
						});

						collapseEl.addEventListener('show.bs.collapse', function () {
							updateButton(true);
						});

						collapseEl.addEventListener('hide.bs.collapse', function () {
							updateButton(false);
						});

					} else {
						btn.addEventListener('click', function () {
							const isOpen = collapseEl.classList.contains('show');
							collapseEl.classList.toggle('show', !isOpen);
							updateButton(!isOpen);
						});
					}
				}

				document.addEventListener('DOMContentLoaded', initAvailabilityCollapse);
				document.addEventListener('turbo:load', initAvailabilityCollapse);
			})();
}
