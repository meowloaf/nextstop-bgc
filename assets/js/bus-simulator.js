const map = L.map("map").setView([14.568, 121.042], 13);
const activeStopNames = new Set((window.nextStopState?.activeStops?.length ? window.nextStopState.activeStops : []).map(name => String(name)));
const routeWarnings = Array.isArray(window.nextStopState?.alerts) ? window.nextStopState.alerts : [];
const routeOperations = window.nextStopState?.routeOperations || {};
let commuterMarker = null;
let commuterAccuracyCircle = null;
let manualPinMode = false;

L.tileLayer(
    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    { attribution: "© OpenStreetMap contributors" }
).addTo(map);

// Stop coordinates are based on public OpenStreetMap place data.
const stopCatalog = [
    { name: "SM North EDSA", lat: 14.657093, lng: 121.031318, area: "Quezon City" },
    { name: "North Avenue / EDSA", lat: 14.653200, lng: 121.032100, area: "Quezon City" },
    { name: "Ayala MRT / EDSA", lat: 14.549198, lng: 121.027902, area: "Makati" },
    { name: "Guadalupe", lat: 14.566600, lng: 121.046200, area: "Makati" },
    { name: "Market! Market!", lat: 14.548873, lng: 121.056361, area: "BGC" },
    { name: "High Street", lat: 14.550800, lng: 121.054200, area: "BGC" },
    { name: "The Fort", lat: 14.552000, lng: 121.050500, area: "BGC" },
    { name: "Globe Tower", lat: 14.554500, lng: 121.051500, area: "BGC" },
    { name: "32nd Street", lat: 14.553500, lng: 121.056000, area: "BGC" },
    { name: "Venice Grand Canal", lat: 14.533742, lng: 121.051685, area: "McKinley Hill" }
];

const stops = stopCatalog.filter(stop => {
    if (!activeStopNames.size) {
        return true;
    }
    return activeStopNames.has(stop.name);
});

const routeHasActiveStops = route => route.stops.every(stopName => activeStopNames.size === 0 || activeStopNames.has(stopName));
const routeIsOnline = route => routeOperations[route.id] === undefined || Number(routeOperations[route.id]) === 1;

const stopByName = name => stopCatalog.find(stop => stop.name === name);
const point = name => {
    const stop = stopByName(name);
    return stop ? [stop.lat, stop.lng] : [14.568, 121.042];
};

const routeDefinitions = [
    {
        id: "east-express",
        name: "East Express",
        code: "EX",
        color: "#f04f4f",
        stops: ["Ayala MRT / EDSA", "Market! Market!"],
        path: [
            point("Ayala MRT / EDSA"),
            [14.5495, 121.0315],
            [14.5486, 121.0380],
            [14.5482, 121.0460],
            point("Market! Market!")
        ],
        signals: [[14.5486, 121.0380], [14.5482, 121.0460]]
    },
    {
        id: "north-route",
        name: "North Route",
        code: "NR",
        color: "#f59e0b",
        stops: ["SM North EDSA", "North Avenue / EDSA", "Ayala MRT / EDSA", "Market! Market!", "The Fort"],
        path: [
            point("SM North EDSA"),
            [14.6530, 121.0315],
            point("North Avenue / EDSA"),
            [14.6050, 121.0320],
            point("Ayala MRT / EDSA"),
            [14.5480, 121.0340],
            [14.5465, 121.0430],
            point("Market! Market!"),
            point("The Fort")
        ],
        signals: [[14.6050, 121.0320], [14.5480, 121.0340], [14.5465, 121.0430]]
    },
    {
        id: "west-route",
        name: "West Route",
        code: "WR",
        color: "#4fc3ba",
        stops: ["Ayala MRT / EDSA", "Market! Market!", "High Street", "The Fort"],
        path: [
            point("Ayala MRT / EDSA"),
            [14.5487, 121.0328],
            [14.5480, 121.0385],
            point("Market! Market!"),
            point("High Street"),
            point("The Fort")
        ],
        signals: [[14.5487, 121.0328], [14.5480, 121.0385]]
    },
    {
        id: "central-route",
        name: "Central Route",
        code: "CR",
        color: "#8b5cf6",
        stops: ["Market! Market!", "High Street", "The Fort", "Globe Tower", "32nd Street"],
        path: [
            point("Market! Market!"),
            point("High Street"),
            point("The Fort"),
            point("Globe Tower"),
            point("32nd Street"),
            point("Market! Market!")
        ],
        signals: [[14.5508, 121.0542], [14.5535, 121.0560]]
    },
    {
        id: "night-route",
        name: "Night Route",
        code: "NT",
        color: "#facc15",
        stops: ["Market! Market!", "High Street", "The Fort", "32nd Street", "Venice Grand Canal"],
        path: [
            point("Market! Market!"),
            point("High Street"),
            point("The Fort"),
            point("32nd Street"),
            point("Venice Grand Canal")
        ],
        signals: [[14.5508, 121.0542], [14.5535, 121.0560], [14.5337, 121.0517]]
    },
    {
        id: "lrt-ayala-route",
        name: "LRT - Ayala Route",
        code: "LRT",
        color: "#22c55e",
        stops: ["Ayala MRT / EDSA", "Market! Market!", "Globe Tower", "High Street"],
        path: [
            point("Ayala MRT / EDSA"),
            [14.5486, 121.0340],
            [14.5482, 121.0410],
            point("Market! Market!"),
            point("Globe Tower"),
            point("High Street")
        ],
        signals: [[14.5486, 121.0340], [14.5482, 121.0410]]
    }
];

const allRoutePoints = routeDefinitions.flatMap(route => route.path);
const networkBounds = L.latLngBounds(allRoutePoints);
map.fitBounds(networkBounds, { padding: [25, 25] });

stops.forEach((stop, index) => {
    const marker = L.circleMarker([stop.lat, stop.lng], {
        radius: 9,
        color: "#123c4a",
        weight: 3,
        fillColor: "#36c7ad",
        fillOpacity: 1
    }).addTo(map);

    marker.bindPopup(`<b>🚏 ${stop.name}</b><br>${stop.area}<br>Stop #${index + 1}`);
    marker.bindTooltip(stop.name, { direction: "top", offset: [0, -8] });
});

routeWarnings.forEach(alert => {
    const latitude = Number(alert.latitude);
    const longitude = Number(alert.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    const eventMarker = L.marker([latitude, longitude], {
        title: alert.title || "Road event"
    }).addTo(map);
    const areaLabel = alert.affected_area ? `<br><small>${alert.affected_area}</small>` : "";
    eventMarker.bindPopup(`<b>⚠ ${alert.title || "Road event"}</b>${areaLabel}<br>${alert.message || "Road notice"}`);
});

routeDefinitions.forEach(route => {
    route.line = L.polyline(route.path, {
        color: route.color,
        weight: 4,
        opacity: 0.7
    }).addTo(map);

    route.signals.forEach(signal => {
        L.circleMarker(signal, {
            radius: 4,
            color: "#ffffff",
            weight: 2,
            fillColor: "#e24c58",
            fillOpacity: 1
        }).addTo(map).bindPopup("Traffic signal - bus may pause here");
    });
});

async function loadRoadGeometry(route) {
    const coordinates = route.path.map(([lat, lng]) => `${lng},${lat}`).join(";");
    const url = `https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`;

    try {
        const response = await fetch(url);
        const result = await response.json();
        const roadPath = result.routes?.[0]?.geometry?.coordinates;

        if (roadPath?.length) {
            route.path = roadPath.map(([lng, lat]) => [lat, lng]);
            route.line.setLatLngs(route.path);
        }
    } catch (error) {
        // Keep the local route waypoints when the public router is unavailable.
    }
}

const fromLocationInput = document.getElementById("from-location");
const toLocationInput = document.getElementById("to-location");
const stopSuggestions = document.getElementById("stop-suggestions");
const tripStatus = document.getElementById("trip-status");
const routeList = document.getElementById("route-list");
const routeCount = document.getElementById("route-count");
const tripDetails = document.getElementById("trip-details");
const busEta = document.getElementById("bus-eta");
const nearestStop = document.getElementById("nearest-stop");
const passingStopList = document.getElementById("passing-stop-list");
const rideActionButton = document.getElementById("ride-action-button");
const findButton = document.querySelector(".find-button");
const liveStatusList = document.getElementById("live-status-list");
let selectedStartIndex = 0;
let selectedDestinationIndex = 1;
let selectedRoute = null;
let rideState = "waiting";
let rideArrivalTimer = null;
let routeSearchRunning = false;
let fleet = [];

const routeSchedules = {
    "east-express": { eta: 8, every: 1 },
    "north-route": { eta: 12, every: 1 },
    "west-route": { eta: 10, every: 1 },
    "central-route": { eta: 7, every: 1 },
    "night-route": { eta: 15, every: 1 },
    "lrt-ayala-route": { eta: 6, every: 1 }
};

const routeCatalog = routeDefinitions.map(route => ({
    id: route.id,
    name: route.name,
    liveName: {
        "east-express": "East Express",
        "north-route": "North Route",
        "west-route": "West Route",
        "central-route": "Central Route",
        "night-route": "Night Route",
        "lrt-ayala-route": "LRT - Ayala Route"
    }[route.id] || route.name,
    code: route.code,
    from: route.stops[0],
    to: route.stops[route.stops.length - 1],
    stops: route.stops,
    baseEta: routeSchedules[route.id]?.eta || 5,
    eta: `${routeSchedules[route.id]?.eta || 5} min`,
    every: `Every ${routeSchedules[route.id]?.every || 10} min`,
    color: route.color,
    status: !routeIsOnline(route) ? "OFFLINE" : (routeHasActiveStops(route) ? "ON TIME" : "UNAVAILABLE"),
    available: routeIsOnline(route) && routeHasActiveStops(route),
    warning: routeHasWarning(route)
}));

function getRouteEta(route) {
    const bus = fleet.find(item => item.route.id === route.id);
    if (!bus) {
        return route.eta;
    }

    const totalPoints = Math.max(1, bus.route.path.length - 1);
    const progress = Math.min(1, bus.position / totalPoints);
    const remainingMinutes = Math.ceil(route.baseEta * (1 - progress));
    return `${Math.max(2, remainingMinutes)} min`;
}

function renderLiveStatus() {
    if (!liveStatusList) {
        return;
    }

    liveStatusList.innerHTML = routeCatalog.map(route => `
        <div class="live-status-row" data-route-id="${route.id}">
            <i class="status-dot" style="background: ${route.color}"></i>
            <span>${route.liveName}</span>
            <strong data-live-eta>${getRouteEta(route)}</strong>
        </div>
    `).join("");
}

function refreshRouteDisplays() {
    routeCatalog.forEach(route => {
        route.eta = getRouteEta(route);
    });

    renderLiveStatus();
    document.querySelectorAll("[data-route-id]").forEach(row => {
        const route = routeCatalog.find(item => item.id === row.dataset.routeId);
        const etaElements = row.querySelectorAll("[data-route-eta], [data-live-eta]");

        if (route) {
            etaElements.forEach(etaElement => {
                etaElement.textContent = route.eta;
            });
        }
    });
}

function routeHasWarning(route) {
    const routeAreas = route.stops
        .map(stopName => stopCatalog.find(stop => stop.name === stopName)?.area || "")
        .map(area => area.toLowerCase());

    return routeWarnings.some(alert => {
        const affectedArea = String(alert.affected_area || "").toLowerCase();
        if (affectedArea && routeAreas.some(area => area.includes(affectedArea) || affectedArea.includes(area))) {
            return true;
        }

        if (alert.latitude === null || alert.longitude === null || alert.latitude === undefined || alert.longitude === undefined) {
            return false;
        }

        const latitude = Number(alert.latitude);
        const longitude = Number(alert.longitude);
        return route.path.some(([pathLatitude, pathLongitude]) => {
            const latitudeDistance = (pathLatitude - latitude) * 111;
            const longitudeDistance = (pathLongitude - longitude) * 111 * Math.cos(latitude * Math.PI / 180);
            return Math.sqrt(latitudeDistance ** 2 + longitudeDistance ** 2) <= 1.5;
        });
    });
}

stops.forEach(stop => stopSuggestions.appendChild(new Option(stop.name)));

if (stops.length > 0) {
    fromLocationInput.value = "";
    toLocationInput.value = "";
}

function renderRoutes(routes) {
    routeList.innerHTML = "";
    routeCount.textContent = `${routes.length} found`;

    routes.forEach((routeItem, index) => {
        const card = document.createElement("article");
        card.className = `route-card${index === 0 ? " selected" : ""}${routeItem.available === false ? " unavailable" : ""}`;
        card.style.setProperty("--route-color", routeItem.color);
        const routeNotice = routeItem.warning && routeItem.available !== false
            ? `<div class="route-warning" role="status">Road/event notice affects this route</div>`
            : "";
        const transferNotice = routeItem.transferLeg
            ? `<div class="route-warning route-warning--transfer" role="status">${routeItem.transferLeg === 1 ? `Ride to ${routeItem.transferStop}, then change to the next route` : `Transfer from ${routeItem.transferStop} to continue to your destination`}</div>`
            : "";
        card.innerHTML = `
            <div class="route-card-top">
                <strong><i class="status-dot" style="background: ${routeItem.color}"></i> ${routeItem.name} <small>${routeItem.code}</small></strong>
                <span class="route-badge ${routeItem.recommended || routeItem.transferLeg ? "route-badge--recommended" : ""} ${routeItem.available === false ? "delay" : ""}">${routeItem.transferLeg ? `TRANSFER ${routeItem.transferLeg}/2` : (routeItem.recommended ? "RECOMMENDED" : routeItem.status)}</span>
            </div>
            <div class="route-card-meta"><span>${routeItem.from} → ${routeItem.to}</span><strong>${routeItem.eta}</strong></div>
            <div class="route-card-meta route-card-meta--status">
                <span>ETA ${routeItem.available === false ? "Unavailable" : routeItem.eta}</span>
                <span class="route-line" style="background: ${routeItem.color}"></span>
                <span>${routeItem.every}</span>
            </div>
            ${routeItem.available === false && routeItem.status === "OFFLINE" ? '<div class="route-warning route-warning--offline" role="status">Buses are currently offline on this route</div>' : ""}
            ${routeNotice}
            ${transferNotice}
        `;
        if (routeItem.available !== false) {
            card.addEventListener("click", () => selectRoute(routeItem, card));
        } else {
            card.setAttribute("aria-disabled", "true");
            card.setAttribute("title", "This route is temporarily unavailable due to an inactive stop or active road warning.");
        }
        routeList.appendChild(card);
    });
}

function rankRoutes(startName, destinationName) {
    return routeCatalog
        .map(route => {
            const startPosition = route.stops.indexOf(startName);
            const destinationPosition = route.stops.indexOf(destinationName);
            const servesBoth = startPosition >= 0 && destinationPosition >= 0 && destinationPosition > startPosition;
            const servesBothEitherDirection = startPosition >= 0 && destinationPosition >= 0;
            const servesStart = startPosition >= 0;
            let score = 0;

            if (servesBoth) {
                score += 100;
            } else if (servesBothEitherDirection) {
                score += 70;
            } else if (servesStart) {
                score += 35;
            } else if (route.stops.includes(destinationName)) {
                score += 20;
            }

            if (route.available) {
                score += 10;
            }
            if (route.warning) {
                score -= 5;
            }

            return { route, score, servesBoth };
        })
        .sort((first, second) => second.score - first.score)
        .map((item, index) => ({ ...item.route, recommended: index === 0 && item.route.available && item.score >= 35 }));
}

function findTransferSuggestion(startName, destinationName) {
    const availableRoutes = routeCatalog.filter(route => route.available);
    let bestSuggestion = null;

    availableRoutes.forEach(firstRoute => {
        const startPosition = firstRoute.stops.indexOf(startName);
        if (startPosition < 0) {
            return;
        }

        availableRoutes.forEach(secondRoute => {
            if (firstRoute.id === secondRoute.id) {
                return;
            }

            const destinationPosition = secondRoute.stops.indexOf(destinationName);
            if (destinationPosition < 0) {
                return;
            }

            firstRoute.stops.slice(startPosition + 1).some(transferStop => {
                const secondPosition = secondRoute.stops.indexOf(transferStop);
                if (secondPosition < 0 || secondPosition >= destinationPosition) {
                    return false;
                }

                const suggestion = {
                    firstRouteId: firstRoute.id,
                    secondRouteId: secondRoute.id,
                    transferStop,
                    score: firstRoute.stops.length + secondRoute.stops.length
                };
                if (!bestSuggestion || suggestion.score < bestSuggestion.score) {
                    bestSuggestion = suggestion;
                }
                return true;
            });
        });
    });

    return bestSuggestion;
}

function selectRoute(routeItem, card) {
    if (routeItem.available === false) {
        tripStatus.textContent = "This route is temporarily unavailable. Please choose another route or wait for the operating notice to clear.";
        tripStatus.className = "trip-status is-warning";
        return;
    }

    fromLocationInput.value = routeItem.from;
    toLocationInput.value = routeItem.to;
    routeList.querySelectorAll(".route-card").forEach(item => item.classList.remove("selected"));
    card.classList.add("selected");
    selectedRoute = routeDefinitions.find(route => route.id === routeItem.id) || null;
    highlightRoute(selectedRoute);
    updateTrip(routeItem.from, routeItem.to);
    zoomToRoute(selectedRoute);
}

function highlightRoute(route) {
    routeDefinitions.forEach(candidate => {
        const unavailable = !routeHasActiveStops(candidate) || !routeIsOnline(candidate);
        const hasWarning = routeHasWarning(candidate);
        candidate.line.setStyle({
            weight: candidate === route ? 7 : 3,
            opacity: candidate === route ? (unavailable ? 0.35 : 1) : (unavailable ? 0.12 : 0.25),
            color: !routeIsOnline(candidate) ? "#9aa5a9" : (unavailable ? "#d97706" : (hasWarning ? "#d97706" : candidate.color)),
            dashArray: unavailable || hasWarning ? "10 8" : ""
        });
    });

    if (route?.line) {
        route.line.bringToFront();
    }
}

function zoomToRoute(route) {
    if (route?.line) {
        map.fitBounds(route.line.getBounds(), { padding: [35, 35], maxZoom: 15 });
    }
}

function distanceBetween(first, second) {
    const latDistance = (first.lat - second.lat) * 111;
    const lngDistance = (first.lng - second.lng) * 111 * Math.cos(first.lat * Math.PI / 180);
    return Math.sqrt(latDistance ** 2 + lngDistance ** 2);
}

function nearestMappedStop(latitude, longitude) {
    const location = { lat: latitude, lng: longitude };
    return stops.reduce((nearest, stop) => {
        return distanceBetween(location, stop) < distanceBetween(location, nearest) ? stop : nearest;
    });
}

function resolveStop(value) {
    const query = value.trim().toLowerCase();
    if (!query) {
        return null;
    }

    return stops.find(stop => stop.name.toLowerCase() === query) ||
        stops.find(stop => stop.name.toLowerCase().includes(query) || query.includes(stop.name.toLowerCase()));
}

async function geocodeLocation(value) {
    const localStop = resolveStop(value);
    if (localStop) {
        return localStop;
    }

    const normalizedValue = value.trim().replace(/\s+/g, " ");
    const searchQueries = [
        `${normalizedValue}, BGC, Taguig, Metro Manila`,
        `${normalizedValue}, Taguig, Metro Manila`,
        `${normalizedValue}, Metro Manila`
    ];

    for (const query of searchQueries) {
        try {
            const params = new URLSearchParams({
                format: "jsonv2",
                limit: "5",
                countrycodes: "ph",
                addressdetails: "1",
                q: query
            });
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 6000);
            const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, { signal: controller.signal });
            clearTimeout(timeout);
            const results = await response.json();
            if (results.length) {
                const localResult = results.find(result => {
                    const displayName = String(result.display_name || "").toLowerCase();
                    return displayName.includes("bgc") || displayName.includes("taguig") || displayName.includes("metro manila");
                }) || results[0];
                return nearestMappedStop(Number(localResult.lat), Number(localResult.lon));
            }
        } catch (error) {
            // Try the broader fallback query before showing an error.
        }
    }

    return null;
}

async function findRoutes() {
    if (routeSearchRunning) {
        return;
    }

    routeSearchRunning = true;
    findButton.disabled = true;
    findButton.textContent = "Finding...";
    tripStatus.textContent = "Finding the nearest stops...";
    tripStatus.className = "trip-status is-active";

    try {
        const startStop = await geocodeLocation(fromLocationInput.value);
        const destinationStop = await geocodeLocation(toLocationInput.value);

        if (!startStop || !destinationStop) {
            tripStatus.textContent = "Choose a mapped stop or use your location first.";
            tripStatus.className = "trip-status";
            return;
        }

        const unavailableStop = !activeStopNames.size || !activeStopNames.has(startStop.name) || !activeStopNames.has(destinationStop.name);
        if (unavailableStop) {
            tripStatus.textContent = "One or more stops are temporarily unavailable. Please choose another location or check the active road notices.";
            tripStatus.className = "trip-status is-warning";
            return;
        }

        fromLocationInput.value = startStop.name;
        toLocationInput.value = destinationStop.name;
        const startName = startStop.name;
        const destinationName = destinationStop.name;
        const directRoute = routeCatalog.find(route => {
            const startPosition = route.stops.indexOf(startName);
            const destinationPosition = route.stops.indexOf(destinationName);
            return route.available && startPosition >= 0 && destinationPosition > startPosition;
        });
        const transferSuggestion = directRoute ? null : findTransferSuggestion(startName, destinationName);
        let routes = rankRoutes(startName, destinationName);

        if (transferSuggestion) {
            const firstLeg = routeCatalog.find(route => route.id === transferSuggestion.firstRouteId);
            const secondLeg = routeCatalog.find(route => route.id === transferSuggestion.secondRouteId);
            const transferRoutes = [
                { ...firstLeg, recommended: false, transferLeg: 1, transferStop: transferSuggestion.transferStop },
                { ...secondLeg, recommended: false, transferLeg: 2, transferStop: transferSuggestion.transferStop }
            ];
            routes = [...transferRoutes, ...routes.filter(route => !transferRoutes.some(transferRoute => transferRoute.id === route.id))];
        }

        renderRoutes(routes);
        selectedRoute = routeDefinitions.find(route => route.id === routes[0]?.id) || null;
        highlightRoute(selectedRoute);
        updateTrip(startName, destinationName);
        zoomToRoute(selectedRoute);
        const recommendedRoute = routes.find(route => route.recommended);
        if (transferSuggestion) {
            tripStatus.textContent = `No direct route. Take ${routeCatalog.find(route => route.id === transferSuggestion.firstRouteId).name} to ${transferSuggestion.transferStop}, then transfer to ${routeCatalog.find(route => route.id === transferSuggestion.secondRouteId).name} to reach ${destinationName}.`;
            tripStatus.className = "trip-status is-active";
        } else {
            tripStatus.textContent = recommendedRoute
                ? `Recommended route: ${recommendedRoute.name}. Nearby places are matched to the closest mapped stop.`
                : "No available direct or transfer route serves both locations. Choose another route or check the service notices.";
            tripStatus.className = recommendedRoute ? "trip-status is-active" : "trip-status is-warning";
        }
    } finally {
        routeSearchRunning = false;
        findButton.disabled = false;
        findButton.textContent = "Find Routes";
    }
}

function useMyLocation() {
    if (!navigator.geolocation) {
        tripStatus.textContent = "Location services are not available in this browser.";
        return;
    }

    tripStatus.textContent = "Finding your nearest bus stop...";
    navigator.geolocation.getCurrentPosition(position => {
        pinCommuterLocation(position.coords.latitude, position.coords.longitude, Number(position.coords.accuracy) || 80, "Your location");
    }, () => {
        tripStatus.textContent = "Location access was unavailable. Type your starting place instead.";
        tripStatus.className = "trip-status";
    });
}

function pinCommuterLocation(latitude, longitude, accuracy = 35, label = "Selected location") {
    const commuterLocation = [latitude, longitude];

    if (commuterMarker) {
        commuterMarker.setLatLng(commuterLocation);
    } else {
        commuterMarker = L.circleMarker(commuterLocation, {
            radius: 11,
            color: "#123c4a",
            weight: 3,
            fillColor: "#246bfe",
            fillOpacity: 1,
            bubblingMouseEvents: false
        }).addTo(map);
    }
    commuterMarker.bringToFront();
    commuterMarker.bindPopup(`<b>${label}</b><br>Routes are matched from your nearest station.`);

    if (commuterAccuracyCircle) {
        commuterAccuracyCircle.setLatLng(commuterLocation).setRadius(accuracy);
    } else {
        commuterAccuracyCircle = L.circle(commuterLocation, {
            radius: accuracy,
            color: "#246bfe",
            weight: 1,
            fillColor: "#246bfe",
            fillOpacity: 0.12
        }).addTo(map);
    }

    map.setView(commuterLocation, Math.max(map.getZoom(), 14), { animate: true });
    const stop = nearestMappedStop(latitude, longitude);
    fromLocationInput.value = stop.name;
    tripStatus.textContent = `${label} pinned. Nearest station: ${stop.name}.`;
    tripStatus.className = "trip-status is-active";
    if (toLocationInput.value.trim()) {
        findRoutes();
    }
}

function toggleManualPinMode() {
    manualPinMode = !manualPinMode;
    const pinButton = document.getElementById("drop-pin-button");
    if (pinButton) {
        pinButton.classList.toggle("is-active", manualPinMode);
        pinButton.textContent = manualPinMode ? "Click map to place pin" : "Drop pin on map";
    }
    map.getContainer().classList.toggle("is-pin-mode", manualPinMode);
    tripStatus.textContent = manualPinMode ? "Click the map where the commuter is waiting." : "Choose a starting point and destination.";
    tripStatus.className = manualPinMode ? "trip-status is-active" : "trip-status";
}

function handleManualMapClick(event) {
    if (!manualPinMode) {
        return;
    }

    pinCommuterLocation(event.latlng.lat, event.latlng.lng, 35, "Commuter location");
    manualPinMode = false;
    const pinButton = document.getElementById("drop-pin-button");
    if (pinButton) {
        pinButton.classList.remove("is-active");
        pinButton.textContent = "Drop pin on map";
    }
    map.getContainer().classList.remove("is-pin-mode");
}

map.on("click", handleManualMapClick);
window.toggleManualPinMode = toggleManualPinMode;

const dropPinButton = document.getElementById("drop-pin-button");
if (dropPinButton) {
    dropPinButton.addEventListener("click", toggleManualPinMode);
}

renderRoutes(routeCatalog);

const busIcon = L.divIcon({
    html: `<div style="font-size: 25px; text-align: center;">🚌</div>`,
    className: "",
    iconSize: [32, 32],
    iconAnchor: [16, 16]
});

fleet = routeDefinitions.map((route, index) => {
    const marker = L.marker(route.path[0], { icon: busIcon }).addTo(map);
    marker.bindPopup(`<b>🚌 ${route.code}-${index + 1}</b><br>${route.name}<br>Stops at mapped stops and signals`);
    marker.setOpacity(routeIsOnline(route) ? 1 : 0);
    return { route, marker, position: 0, waitTicks: index % 2 };
});

renderLiveStatus();

routeDefinitions.forEach(loadRoadGeometry);

let simulationTimer = null;
let simulationSpeed = 1000;

function startSimulation() {
    if (simulationTimer) {
        return;
    }

    tripStatus.textContent = "Buses are moving along their routes.";
    tripStatus.className = "trip-status is-active";
    simulationTimer = setInterval(moveFleet, simulationSpeed);
}

function pauseSimulation() {
    if (!simulationTimer) {
        return;
    }

    clearInterval(simulationTimer);
    simulationTimer = null;
    tripStatus.textContent = "Bus simulation paused.";
    tripStatus.className = "trip-status";
}

function resetSimulation() {
    clearInterval(simulationTimer);
    simulationTimer = null;

    fleet.forEach(bus => {
        bus.position = 0;
        bus.waitTicks = 0;
        bus.marker.setLatLng(bus.route.path[0]);
        bus.marker.setOpacity(routeIsOnline(bus.route) ? 1 : 0);
    });

    refreshRouteDisplays();
    tripStatus.textContent = "Route simulation reset.";
    tripStatus.className = "trip-status";
}

function changeSpeed() {
    const speedSelect = document.getElementById("speed");
    if (!speedSelect) {
        return;
    }

    simulationSpeed = Number(speedSelect.value) || 1000;

    if (simulationTimer) {
        clearInterval(simulationTimer);
        simulationTimer = setInterval(moveFleet, simulationSpeed);
    }
}

function isNear(first, second) {
    return Math.abs(first[0] - second[0]) < 0.0008 && Math.abs(first[1] - second[1]) < 0.0008;
}

function moveFleet() {
    fleet.forEach(bus => {
        if (!routeIsOnline(bus.route)) {
            bus.marker.setOpacity(0);
            return;
        }
        bus.marker.setOpacity(1);
        if (bus.waitTicks > 0) {
            bus.waitTicks -= 1;
            return;
        }

        bus.position = (bus.position + 1) % bus.route.path.length;
        const nextPoint = bus.route.path[bus.position];
        bus.marker.setLatLng(nextPoint);

        const atStop = bus.route.stops.some(stopName => isNear(nextPoint, point(stopName)));
        const atSignal = bus.route.signals.some(signal => isNear(nextPoint, signal));
        if (atStop) {
            bus.waitTicks = 2;
        } else if (atSignal) {
            bus.waitTicks = 1;
        }
    });

    refreshRouteDisplays();
}

function updateTrip(startName = resolveStop(fromLocationInput.value)?.name, destinationName = resolveStop(toLocationInput.value)?.name) {
    clearTimeout(rideArrivalTimer);
    rideState = "waiting";

    if (!startName || !destinationName) {
        tripStatus.textContent = "Choose a starting point and destination.";
        tripDetails.hidden = true;
        return;
    }

    if (!activeStopNames.size || !activeStopNames.has(startName) || !activeStopNames.has(destinationName)) {
        tripStatus.textContent = "This stop is temporarily closed. Please choose another stop from the active network.";
        tripStatus.className = "trip-status is-warning";
        tripDetails.hidden = true;
        return;
    }

    selectedStartIndex = stops.findIndex(stop => stop.name === startName);
    selectedDestinationIndex = stops.findIndex(stop => stop.name === destinationName);

    if (startName === destinationName) {
        tripStatus.textContent = "Choose a different destination stop.";
        tripStatus.className = "trip-status";
        tripDetails.hidden = true;
        return;
    }

    const matchingRoute = routeDefinitions.find(route => {
        const startPosition = route.stops.indexOf(startName);
        const destinationPosition = route.stops.indexOf(destinationName);
        return startPosition >= 0 && destinationPosition > startPosition;
    }) || routeDefinitions.find(route => route.stops.includes(startName));
    selectedRoute = selectedRoute || matchingRoute;
    const routeStops = matchingRoute?.stops || [startName, destinationName];
    const startPosition = Math.max(routeStops.indexOf(startName), 0);
    const destinationPosition = Math.max(routeStops.indexOf(destinationName), startPosition + 1);
    const passingStops = routeStops.slice(startPosition, destinationPosition + 1);
    const estimatedMinutes = 4 + Math.abs(selectedDestinationIndex - selectedStartIndex) * 3 + (matchingRoute?.id === "sm-north-bgc" ? 12 : 0);

    busEta.textContent = `${estimatedMinutes} min`;
    nearestStop.textContent = startName;
    passingStopList.innerHTML = passingStops.map(stop => `<li>${stop}</li>`).join("");
    tripDetails.hidden = false;
    rideActionButton.disabled = false;
    rideActionButton.textContent = "Bus is here!";
    tripStatus.textContent = `Ready to travel from ${startName} to ${destinationName}.`;
    tripStatus.className = "trip-status";
}

function handleRideAction() {
    if (rideState === "waiting") {
        rideState = "riding";
        rideActionButton.disabled = true;
        rideActionButton.textContent = "You are riding the bus";
        tripStatus.textContent = "You are on the bus. We will notify you near your destination.";
        tripStatus.className = "trip-status is-active";

        // Short demo timer represents the bus approaching the destination.
        rideArrivalTimer = setTimeout(() => {
            rideState = "arrived";
            rideActionButton.disabled = false;
            rideActionButton.textContent = "I'm at my destination";
            tripStatus.textContent = "You are near your destination.";
            tripStatus.className = "trip-status is-active";
        }, 8000);
        return;
    }

    if (rideState === "arrived") {
        completeRide();
    }
}

async function completeRide() {
    const startName = resolveStop(fromLocationInput.value)?.name;
    const destinationName = resolveStop(toLocationInput.value)?.name;

    if (!startName || !destinationName || startName === destinationName) {
        return;
    }

    rideActionButton.disabled = true;
    rideActionButton.textContent = "Saving ride...";

    try {
        const response = await fetch("api/ride-history.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                start_stop: startName,
                destination_stop: destinationName
            })
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Unable to save ride");
        }

        rideState = "completed";
        rideActionButton.textContent = "Ride saved to history";
        tripStatus.textContent = `Ride completed: ${startName} to ${destinationName}.`;
        tripStatus.className = "trip-status is-complete";
    } catch (error) {
        rideActionButton.disabled = false;
        rideActionButton.textContent = "I'm at my destination";
        tripStatus.textContent = "Could not save this ride. Please try again.";
        tripStatus.className = "trip-status";
    }
}

if (routeWarnings.length) {
    const latestAlert = routeWarnings[0];
    tripStatus.textContent = `${latestAlert.title}: ${latestAlert.message}`;
    tripStatus.className = "trip-status is-warning";
}

startSimulation();