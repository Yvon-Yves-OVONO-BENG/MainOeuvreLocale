(function () {
const root = document.getElementById('jobNearbyMap');
const form = document.getElementById('jobNearbySearchForm');
if (! root || ! form || root.dataset.bound === '1') 
return;

root.dataset.bound = '1';

const radius = document.getElementById('job_nearby_radius');
const radiusValue = document.getElementById('jobNearbyRadiusValue');
const button = document.getElementById('jobNearbySearchButton');
const status = document.getElementById('jobNearbyStatus');
const panel = document.getElementById('jobNearbyMapSection');
const closeButton = document.getElementById('jobNearbyMapClose');
const summary = document.getElementById('jobNearbyMapSummary');
const message = document.getElementById('jobNearbyMapMessage');
let map = null;
let resultsLayer = null;

const escapeHtml = function (value) {
const node = document.createElement('div');
node.textContent = value == null ? '' : String(value);
return node.innerHTML;
};

const escapeAttribute = function (value) {
return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
return {
'&': '&amp;',
'<': '&lt;',
'>': '&gt;',
"'": '&#39;',
'"': '&quot;'
}[character];
});
};

const setStatus = function (text, isError) {
status.querySelector('span').textContent = text;
status.style.background = isError ? '#fff0f2' : '#eef6ff';
status.style.color = isError ? '#b52d43' : '#355477';
};

const setSearchButtonLoading = function (loading) {
if (loading) {
if (! button.dataset.nearbyOriginalHtml) 
button.dataset.nearbyOriginalHtml = button.innerHTML;

button.innerHTML = '<span class="mol-submit-spinner" aria-hidden="true"></span><span>Traitement...</span>';
button.classList.add('mol-submit-loading');
button.setAttribute('aria-busy', 'true');
button.disabled = true;
return;
}

if (button.dataset.nearbyOriginalHtml) 
button.innerHTML = button.dataset.nearbyOriginalHtml;

button.classList.remove('mol-submit-loading');
button.removeAttribute('aria-busy');
button.disabled = false;
delete button.dataset.nearbyOriginalHtml;
};

const ensureLeaflet = function () {
if (window.L) 
return Promise.resolve(window.L);

if (window.__molLeafletPromise) 
return window.__molLeafletPromise;


window.__molLeafletPromise = new Promise(function (resolve, reject) {
if (!document.querySelector('link[data-mol-leaflet]')) {
const css = document.createElement('link');
css.rel = 'stylesheet';
css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
css.dataset.molLeaflet = '1';
document.head.appendChild(css);
}
const existing = document.querySelector('script[data-mol-leaflet]');
if (existing) {
existing.addEventListener('load', function () {
resolve(window.L);
}, {once: true});
existing.addEventListener('error', reject, {once: true});
return;
}
const script = document.createElement('script');
script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
script.defer = true;
script.dataset.molLeaflet = '1';
script.onload = function () {
resolve(window.L);
};
script.onerror = reject;
document.head.appendChild(script);
});
return window.__molLeafletPromise;
};

const resizeMap = function () {
if (! map || panel.hidden) 
return;

window.requestAnimationFrame(function () {
map.invalidateSize(false);
window.setTimeout(function () {
map.invalidateSize(false);
}, 180);
});
};

const showPanel = function () {
panel.hidden = false;
panel.setAttribute('aria-hidden', 'false');
resizeMap();
window.requestAnimationFrame(function () {
panel.scrollIntoView({behavior: 'smooth', block: 'start'});
});
};

const initMap = function () {
if (map || !window.L) 
return;

map = window.L.map(root, {
preferCanvas: true,
zoomControl: true
}).setView([
7.3697, 12.3547
], 6);
window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
maxZoom: 19,
attribution: '&copy; OpenStreetMap'
}).addTo(map);
resultsLayer = window.L.layerGroup().addTo(map);
resizeMap();

if ('ResizeObserver' in window) {
const observer = new ResizeObserver(resizeMap);
observer.observe(root);
}
window.addEventListener('resize', resizeMap, {passive: true});
window.addEventListener('orientationchange', function () {
window.setTimeout(resizeMap, 280);
}, {passive: true});
};

const locate = function () {
return new Promise(function (resolve, reject) {
if (!navigator.geolocation || !window.isSecureContext) {
reject(new Error('La géolocalisation nécessite une connexion HTTPS et un navigateur compatible.'));
return;
}
navigator.geolocation.getCurrentPosition(resolve, function (error) {
const messages = {
1: 'Autorisez la localisation dans votre navigateur pour afficher les offres proches.',
2: 'Votre position est momentanément indisponible.',
3: 'La détection de votre position a pris trop de temps. Réessayez.'
};
reject(new Error(messages[error.code] || 'Impossible de détecter votre position.'));
}, {
enableHighAccuracy: false,
timeout: 12000,
maximumAge: 300000
});
});
};

const salaryText = function (job) {
const formatter = new Intl.NumberFormat('fr-FR');
if (job.salaryMin !== null && job.salaryMax !== null) 
return formatter.format(job.salaryMin) + ' – ' + formatter.format(job.salaryMax) + ' FCFA';

if (job.salaryMin !== null) 
return 'Dès ' + formatter.format(job.salaryMin) + ' FCFA';

if (job.salaryMax !== null) 
return 'Jusqu’à ' + formatter.format(job.salaryMax) + ' FCFA';

return 'Salaire à convenir';
};

const knownCities = {
'yaounde': [
3.8480, 11.5021
],
'douala': [
4.0511, 9.7679
],
'bafoussam': [
5.4781, 10.4179
],
'bamenda': [
5.9631, 10.1591
],
'garoua': [
9.3014, 13.3977
],
'maroua': [
10.5910, 14.3159
],
'ngaoundere': [
7.3277, 13.5847
],
'bertoua': [
4.5773, 13.6846
],
'ebolowa': [
2.9000, 11.1500
],
'buea': [
4.1527, 9.2410
],
'limbe': [
4.0227, 9.1954
],
'kumba': [
4.6363, 9.4469
],
'kribi': [
2.9373, 9.9077
],
'edea': [
3.7960, 10.1330
],
'nkongsamba': [
4.9547, 9.9404
],
'foumban': [
5.7266, 10.8987
],
'dschang': [
5.4456, 10.0493
],
'mbalmayo': [
3.5167, 11.5000
],
'mfou': [
3.7167, 11.6333
],
'obala': [
4.1667, 11.5333
],
'sangmelima': [
2.9333, 11.9833
],
'mbouda': [
5.6261, 10.2542
],
'batouri': [
4.4333, 14.3667
],
'meiganga': [
6.5167, 14.3000
],
'mokolo': [
10.7424, 13.8023
],
'yagoua': [
10.3411, 15.2329
],
'kumbo': [6.2000, 10.6667]
};

const normalizeCity = function (value) {
return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
};

const cityCacheKey = function (city) {
return 'mol-job-city:' + normalizeCity(city);
};

const cachedCity = function (city) {
try {
const raw = window.localStorage.getItem(cityCacheKey(city));
const point = raw ? JSON.parse(raw) : null;
return Array.isArray(point) && point.length === 2 ? point : null;
} catch (error) {
return null;
}
};

const cacheCity = function (city, point) {
try {
window.localStorage.setItem(cityCacheKey(city), JSON.stringify(point));
} catch (error) {}
};

const geocodeCity = async function (city) {
const normalized = normalizeCity(city);
const knownKey = Object.keys(knownCities).find(function (name) {
return normalized === name || normalized.startsWith(name + ' ') || normalized.startsWith(name + ',');
});
if (knownKey) 
return knownCities[knownKey];

const cached = cachedCity(city);
if (cached) 
return cached;


const endpoint = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=cm&q=' + encodeURIComponent(city + ', Cameroun');
const response = await fetch(endpoint, {
headers: {
'Accept': 'application/json'
}
});
if (! response.ok) 
return null;

const rows = await response.json();
if (!Array.isArray(rows) || ! rows.length) 
return null;

const point = [
Number(rows[0].lat),
Number(rows[0].lon)
];
if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) 
return null;

cacheCity(city, point);
return point;
};

const distanceKm = function (from, to) {
const rad = function (value) {
return value * Math.PI / 180;
};
const dLat = rad(to[0] - from[0]);
const dLon = rad(to[1] - from[1]);
const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(from[0])) * Math.cos(rad(to[0])) * Math.sin(dLon / 2) ** 2;
return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const resolveJobCoordinates = async function (jobs, position) {
const origin = [position.coords.latitude, position.coords.longitude];
const maximum = Number(radius.value);
const resolved = [];
const cityPoints = new Map();

for (const job of jobs) {
let point = null;
if (job.mapLatitude !== null && job.mapLongitude !== null) {
point = [
Number(job.mapLatitude),
Number(job.mapLongitude)
];
} else {
const key = normalizeCity(job.city);
if (! cityPoints.has(key)) 
cityPoints.set(key, await geocodeCity(job.city));

point = cityPoints.get(key);
}
if (! point || !Number.isFinite(point[0]) || !Number.isFinite(point[1])) 
continue;

const distance = job.distanceKm !== null ? Number(job.distanceKm) : distanceKm(origin, point);
if (distance > maximum) 
continue;

resolved.push(Object.assign({}, job, {
mapLatitude: point[0],
mapLongitude: point[1],
distanceKm: Math.round(distance * 100) / 100
}));
}
return resolved;
};

const renderResults = function (payload, position) {
resultsLayer.clearLayers();
const userPoint = [position.coords.latitude, position.coords.longitude];
const radiusMeters = Number(radius.value) * 1000;
window.L.circle(userPoint, {
radius: radiusMeters,
color: '#155eef',
weight: 2,
fillColor: '#155eef',
fillOpacity: .07
}).addTo(resultsLayer);
window.L.circleMarker(userPoint, {
radius: 7,
color: '#fff',
weight: 3,
fillColor: '#155eef',
fillOpacity: 1
}).addTo(resultsLayer).bindPopup('Votre position approximative');

const bounds = window.L.latLngBounds([userPoint]);
payload.jobs.forEach(function (job) {
const point = [
Number(job.mapLatitude),
Number(job.mapLongitude)
];
bounds.extend(point);
const popup = '<div class="mol-job-popup"><h4>' + escapeHtml(job.title) + '</h4><p><strong>' + escapeHtml(job.profession) + '</strong> · ' + escapeHtml(job.city) + '</p><p>' + escapeHtml(salaryText(job)) + ' · ' + escapeHtml(job.distanceKm) + ' km</p><a href="' + escapeAttribute(job.url) + '">Voir l’offre</a></div>';
window.L.marker(point).addTo(resultsLayer).bindPopup(popup);
});

if (payload.jobs.length > 0) 
map.fitBounds(bounds.pad(.16), {maxZoom: 14});
 else 
map.setView(userPoint, Number(radius.value) <= 20 ? 11 : 10);
 message.hidden = true;
summary.textContent = payload.jobs.length + ' offre(s) trouvée(s) dans un rayon de ' + radius.value + ' km.';
setStatus(payload.jobs.length + ' offre(s) proche(s)', false);
resizeMap();
};

radius.addEventListener('input', function () {
if (radiusValue) radiusValue.value = radius.value + ' km';
});

closeButton.addEventListener('click', function () {
panel.hidden = true;
panel.setAttribute('aria-hidden', 'true');
});

form.addEventListener('submit', async function (event) {
event.preventDefault();
showPanel();
setSearchButtonLoading(true);
setStatus('Détection de votre position…', false);
summary.textContent = 'Préparation de la recherche géolocalisée…';
message.textContent = 'Chargement de la carte…';
message.hidden = false;
try {
await ensureLeaflet();
initMap();
resizeMap();
const position = await locate();
setStatus('Recherche des offres…', false);
const response = await fetch(root.dataset.searchUrl, {
method: 'POST',
headers: {
'Content-Type': 'application/json',
'X-Requested-With': 'XMLHttpRequest'
},
credentials: 'same-origin',
body: JSON.stringify(
{
_token: root.dataset.csrfToken,
latitude: position.coords.latitude,
longitude: position.coords.longitude,
radius: Number(radius.value),
q: document.getElementById('job_nearby_q').value.trim(),
city: document.getElementById('job_nearby_city').value.trim()
}
)
});
const rawPayload = await response.text();
let payload;
try {
payload = JSON.parse(rawPayload);
} catch (parseError) {
throw new Error('Le serveur a renvoyé une réponse invalide. Rechargez la page puis réessayez.');
}
if (! response.ok || ! payload.ok) 
throw new Error(payload.message || 'La recherche a échoué.');

setStatus('Positionnement des offres…', false);
payload.jobs = await resolveJobCoordinates(payload.jobs, position);
payload.count = payload.jobs.length;
renderResults(payload, position);
} catch (error) {
const text = error && error.message ? error.message : 'Impossible d’afficher la carte pour le moment.';
setStatus(text, true);
summary.textContent = text;
message.textContent = text;
message.hidden = false;
resizeMap();
} finally {
setSearchButtonLoading(false);
}
});
})();
