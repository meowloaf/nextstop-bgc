const map = L.map("map").setView([14.568, 121.042], 13);
const activeStopNames = new Set((Array.isArray(window.nextStopState?.activeStops) ? window.nextStopState.activeStops : []).map(name => String(name)));
const routeWarnings = Array.isArray(window.nextStopState?.alerts) ? window.nextStopState.alerts : [];
const routeOperations = window.nextStopState?.routeOperations || {};
const routeBuses = Array.isArray(window.nextStopState?.routeBuses) ? window.nextStopState.routeBuses : [];
const fareType = ["regular", "student", "senior", "pwd"].includes(window.nextStopState?.fareType)
    ? window.nextStopState.fareType
    : "regular";
const hasFareDiscount = ["student", "senior", "pwd"].includes(fareType);
let commuterMarker = null;
let commuterAccuracyCircle = null;
let manualPinMode = false;

L.tileLayer(
    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    { attribution: "© OpenStreetMap contributors" }
).addTo(map);

// Stop coordinates are based on public OpenStreetMap place data.
const stopCatalog = [
    { name: "EDSA Ayala", lat: 14.54894167, lng: 121.02767222, area: "Makati", labelDirection: "left", photoUrl: "https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9b/Line_3_Ayala_Station_Platform_1.jpg/330px-Line_3_Ayala_Station_Platform_1.jpg", photoSource: "https://en.wikipedia.org/wiki/Ayala_station" },
    { name: "Market! Market!", lat: 14.5492886, lng: 121.0559966, area: "BGC", labelDirection: "right", photoUrl: "https://thumb.wikimedia.org/wikipedia/en/thumb/3/39/Market%21_Market_BGC.jpg/330px-Market%21_Market_BGC.jpg", photoSource: "https://en.wikipedia.org/wiki/Market!_Market!" },
    { name: "HSBC", lat: 14.5535091, lng: 121.0485311, area: "BGC", labelDirection: "left" },
    { name: "Turf BGC", lat: 14.5540884, lng: 121.0519522, area: "BGC", labelDirection: "right" },
    { name: "Avida 34th", lat: 14.5547163, lng: 121.0540338, area: "BGC", labelDirection: "left" },
    { name: "Uptown Mall", lat: 14.5563154, lng: 121.0544068, area: "BGC", labelDirection: "right" },
    { name: "Uptown Parade", lat: 14.5580384, lng: 121.0543999, area: "BGC", labelDirection: "left" },
    { name: "The Globe Tower", lat: 14.5528868, lng: 121.0501066, area: "BGC", labelDirection: "right" },
    { name: "Arca South", lat: 14.50555556, lng: 121.04411111, area: "Taguig", photoUrl: "https://thumb.wikimedia.org/wikipedia/commons/thumb/a/aa/Arca_South_2025.jpg/330px-Arca_South_2025.jpg", photoSource: "https://en.wikipedia.org/wiki/Arca_South" },
    { name: "NutriAsia", lat: 14.5517828, lng: 121.0512573, area: "BGC", labelDirection: "left" },
    { name: "The Fort", lat: 14.5518, lng: 121.0505, area: "BGC", labelDirection: "top" },
    { name: "One/NEO", lat: 14.5497492, lng: 121.0455238, area: "BGC", labelDirection: "left" },
    { name: "Bonifacio Stopover", lat: 14.5546751, lng: 121.0458416, area: "BGC", labelDirection: "left" },
    { name: "Crescent Park West", lat: 14.5544405, lng: 121.0438408, area: "BGC", labelDirection: "left" },
    { name: "Fort Victoria", lat: 14.5471058, lng: 121.0456866, area: "BGC", labelDirection: "left" },
    { name: "McKinley Parkway", lat: 14.55158, lng: 121.0558313, area: "BGC", labelDirection: "left" },
    { name: "Arya Residences", lat: 14.5462322, lng: 121.0482351, area: "BGC", labelDirection: "left" },
    { name: "The Finance Centre", lat: 14.546628, lng: 121.0500938, area: "BGC", labelDirection: "right" },
    { name: "One Parkade", lat: 14.550067, lng: 121.0495318, area: "BGC", labelDirection: "left" },
    { name: "University Parkway", lat: 14.5541658, lng: 121.0572816, area: "BGC", labelDirection: "right" },
    { name: "MECC", lat: 14.5538, lng: 121.0245, area: "Makati (approximate)", labelDirection: "left" },
    { name: "Makati Stock Exchange", lat: 14.5557166, lng: 121.0230533, area: "Makati", labelDirection: "left" },
    { name: "PBCOM", lat: 14.5585501, lng: 121.0193853, area: "Makati", labelDirection: "right" },
    { name: "RCBC", lat: 14.5607615, lng: 121.0163798, area: "Makati", labelDirection: "right" },
    { name: "The Columns", lat: 14.5618494, lng: 121.0162319, area: "Makati", labelDirection: "left" },
    { name: "Chino Roces", lat: 14.5616346, lng: 121.0109937, area: "Makati", labelDirection: "left" },
    { name: "Mayapis", lat: 14.5635114, lng: 121.005145, area: "Makati", labelDirection: "left" },
    { name: "LRT Buendia", lat: 14.5541275, lng: 120.9971759, area: "Pasay", labelDirection: "left" },
    { name: "Leveriza", lat: 14.558357, lng: 120.993362, area: "Pasay", labelDirection: "right" },
    { name: "Makati Mati", lat: 14.5593307, lng: 121.0147968, area: "Makati (approximate)", labelDirection: "right" },
    { name: "City Gate", lat: 14.5596055, lng: 121.0154958, area: "Makati (approximate)", labelDirection: "left" },
    { name: "Security Bank", lat: 14.558617, lng: 121.0187768, area: "Makati", labelDirection: "right" },
    { name: "SGV", lat: 14.5555299, lng: 121.0224672, area: "Makati", labelDirection: "right" },
    { name: "Glorietta", lat: 14.55124, lng: 121.02537, area: "Makati", labelDirection: "left", photoUrl: "https://thumb.wikimedia.org/wikipedia/commons/thumb/1/13/Glorietta_2024.jpg/330px-Glorietta_2024.jpg", photoSource: "https://en.wikipedia.org/wiki/Glorietta" },
    { name: "One Ayala", lat: 14.55047, lng: 121.02793, area: "Makati", labelDirection: "right", photoUrl: "https://thumb.wikimedia.org/wikipedia/en/thumb/b/b2/One_Ayala_12-2022.jpg/330px-One_Ayala_12-2022.jpg", photoSource: "https://en.wikipedia.org/wiki/One_Ayala" }
];

const stops = stopCatalog.filter(stop => {
    return activeStopNames.has(stop.name);
});

const routeHasActiveStops = route => route.stops.filter(stopName => activeStopNames.has(stopName)).length >= 2;
const routeIsOnline = route => routeOperations[route.id] === undefined || Number(routeOperations[route.id]) === 1;

const stopByName = name => stopCatalog.find(stop => stop.name === name);
const point = name => {
    const stop = stopByName(name);
    return stop ? [stop.lat, stop.lng] : [14.568, 121.042];
};

function routeStopSegment(route, startName, destinationName) {
    if (!route) {
        return [];
    }

    const startIndex = route.stops.indexOf(startName);
    const destinationIndex = route.stops.indexOf(destinationName);
    if (startIndex < 0 || destinationIndex < 0 || startIndex === destinationIndex) {
        return [];
    }

    const direction = destinationIndex > startIndex ? 1 : -1;
    const segment = [];
    for (let index = startIndex; ; index += direction) {
        segment.push(route.stops[index]);
        if (index === destinationIndex) {
            return segment;
        }
    }
}

function routeDistanceKm(route, startName, destinationName) {
    const segment = routeStopSegment(route, startName, destinationName);
    let distanceKm = 0;
    const toRadians = degrees => degrees * Math.PI / 180;

    for (let index = 0; index < segment.length - 1; index += 1) {
        const first = stopByName(segment[index]);
        const second = stopByName(segment[index + 1]);
        if (!first || !second) {
            continue;
        }

        const latitudeDifference = toRadians(second.lat - first.lat);
        const longitudeDifference = toRadians(second.lng - first.lng);
        const haversine = Math.sin(latitudeDifference / 2) ** 2 +
            Math.cos(toRadians(first.lat)) * Math.cos(toRadians(second.lat)) *
            Math.sin(longitudeDifference / 2) ** 2;
        distanceKm += 6371 * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
    }

    return distanceKm;
}

function fareForDistance(distanceKm) {
    const regularFare = 15 + Math.ceil(Math.max(0, distanceKm - 7)) * 2;
    const discountedFare = hasFareDiscount ? regularFare * 0.8 : regularFare;
    return Math.round(discountedFare * 100) / 100;
}

function formatFare(amount) {
    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(amount);
}

function updateFare(startName, destinationName, transferPlan, matchingRoute) {
    const fareSummary = document.getElementById("fare-summary");
    const fareTotal = document.getElementById("fare-total");
    const fareBreakdown = document.getElementById("fare-breakdown");
    const fareNote = document.getElementById("fare-note");
    if (!fareSummary || !fareTotal || !fareBreakdown || !fareNote) {
        return;
    }

    let fares;
    if (transferPlan) {
        fares = transferPlan.legs.map(leg => {
            const route = routeDefinitions.find(candidate => candidate.id === leg.routeId);
            if (!route || routeStopSegment(route, leg.from, leg.to).length < 2) {
                return null;
            }
            return {
                route,
                amount: fareForDistance(routeDistanceKm(route, leg.from, leg.to))
            };
        });
        if (fares.some(fare => fare === null)) {
            fareSummary.hidden = true;
            return;
        }
    } else if (matchingRoute && routeStopSegment(matchingRoute, startName, destinationName).length >= 2) {
        fares = [{ route: matchingRoute, amount: fareForDistance(routeDistanceKm(matchingRoute, startName, destinationName)) }];
    } else {
        fareSummary.hidden = true;
        return;
    }

    fareTotal.textContent = formatFare(fares.reduce((total, leg) => total + leg.amount, 0));
    fareBreakdown.textContent = fares.length > 1
        ? fares.map(leg => `${leg.route.name}: ${formatFare(leg.amount)}`).join(" + ")
        : `${fares[0].route.name}: ${formatFare(fares[0].amount)}`;
    fareNote.textContent = hasFareDiscount
        ? "Each bus leg is charged separately: ₱15 covers the first 7 km, then ₱2 per additional kilometer, with a 20% discount for your account category."
        : "Each bus leg is charged separately: ₱15 covers the first 7 km, then ₱2 per additional kilometer.";
    fareSummary.hidden = false;
}

const routeDefinitions = [
    {
        id: "east-express",
        name: "East Express",
        code: "EX",
        color: "#f04f4f",
        terminals: ["EDSA Ayala", "Market! Market!"],
        stops: ["EDSA Ayala", "Market! Market!"]
    },
    {
        id: "north-route",
        name: "North Route",
        code: "NR",
        color: "#f59e0b",
        terminals: ["EDSA Ayala", "The Globe Tower"],
        stops: ["EDSA Ayala", "HSBC", "Turf BGC", "Avida 34th", "Uptown Mall", "Uptown Parade", "The Globe Tower"]
    },
    {
        id: "west-route",
        name: "West Route",
        code: "WR",
        color: "#facc15",
        terminals: ["EDSA Ayala", "Fort Victoria"],
        stops: ["EDSA Ayala", "McKinley Parkway", "Arya Residences", "The Finance Centre", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "Fort Victoria"]
    },
    {
        id: "arca-south-express",
        name: "Arca South Express",
        code: "AS",
        color: "#94a3b8",
        terminals: ["Arca South", "Market! Market!"],
        stops: ["Arca South", "Market! Market!", "NutriAsia", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "The Globe Tower", "One Parkade"],
        pathStops: ["Arca South", "Market! Market!", "NutriAsia", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "The Globe Tower", "One Parkade", "Market! Market!"]
    },
    {
        id: "central-route",
        name: "Central Route",
        code: "CR",
        color: "#8b5cf6",
        terminals: ["Market! Market!", "University Parkway"],
        stops: ["Market! Market!", "NutriAsia", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "The Globe Tower", "One Parkade", "University Parkway"]
    },
    {
        id: "night-route",
        name: "Night Route",
        code: "NT",
        color: "#16c6d2",
        terminals: ["EDSA Ayala"],
        stops: ["EDSA Ayala", "McKinley Parkway", "Arya Residences", "The Finance Centre", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "The Globe Tower", "University Parkway", "Market! Market!", "Fort Victoria"],
        pathStops: ["EDSA Ayala", "McKinley Parkway", "Arya Residences", "The Finance Centre", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "The Globe Tower", "University Parkway", "Market! Market!", "The Fort", "One/NEO", "Bonifacio Stopover", "Crescent Park West", "Fort Victoria", "EDSA Ayala"]
    },
    {
        id: "lrt-ayala-route",
        name: "LRT - Ayala Route",
        code: "LRT",
        color: "#ec4899",
        terminals: ["MECC", "One Ayala"],
        stops: ["MECC", "Makati Stock Exchange", "PBCOM", "RCBC", "The Columns", "Chino Roces", "Mayapis", "LRT Buendia", "Leveriza", "Makati Mati", "City Gate", "Security Bank", "SGV", "Glorietta", "One Ayala"],
        pathStops: ["MECC", "Makati Stock Exchange", "PBCOM", "RCBC", "The Columns", "Chino Roces", "Mayapis", "LRT Buendia", "Leveriza", "LRT Buendia", "Mayapis", "Makati Mati", "City Gate", "Security Bank", "SGV", "Glorietta", "One Ayala"]
    }
];

const terminalStopNames = new Set(routeDefinitions.flatMap(route => route.terminals));

routeDefinitions.forEach(route => {
    route.path = (route.pathStops || route.stops).map(point);
});

const allRoutePoints = routeDefinitions.flatMap(route => route.path);
const networkBounds = L.latLngBounds(allRoutePoints);
map.fitBounds(networkBounds, { padding: [25, 25] });

const stopMarkers = new Map();
const stopMarkerIcon = (isTerminal, transferType = "", tripEndpoint = "") => {
    const transferClass = transferType ? ` stop-map-marker--transfer-${transferType}` : "";
    const endpointClass = tripEndpoint ? ` stop-map-marker--trip-${tripEndpoint}` : "";
    const label = transferType === "exit"
        ? "!"
        : (transferType === "boarding" ? "↗" : (tripEndpoint === "start" ? "S" : (tripEndpoint === "destination" ? "D" : (isTerminal ? "T" : "B"))));
    return L.divIcon({
        className: "stop-map-icon-wrap",
        html: `<span class="stop-map-marker${isTerminal ? " stop-map-marker--terminal" : ""}${transferClass}${endpointClass}" aria-hidden="true"><span>${label}</span></span>`,
        iconSize: [42, 42],
        iconAnchor: [21, 21]
    });
};

const stopTooltips = [];
stops.forEach(stop => {
    const isTerminal = terminalStopNames.has(stop.name);
    const marker = L.marker([stop.lat, stop.lng], {
        title: `${isTerminal ? "Terminal" : "Bus stop"}: ${stop.name}`,
        icon: stopMarkerIcon(isTerminal)
    }).addTo(map);
    stopMarkers.set(stop.name, { marker, isTerminal });
    const terminalLabel = isTerminal ? '<span class="stop-popup-terminal">Route terminal</span><br>' : "";
    const photo = isTerminal && stop.photoUrl
        ? `<a class="stop-popup-photo-link" href="${stop.photoSource}" target="_blank" rel="noopener noreferrer"><img class="stop-popup-photo" src="${stop.photoUrl}" alt="${stop.name}" loading="lazy"><span>View image source</span></a>`
        : "";

    marker.bindPopup(`<div class="stop-popup">${photo}<strong>${stop.name}</strong><br>${terminalLabel}${stop.area}<br><small class="stop-popup-note">Pin is near the landmark; the exact bus bay may vary.</small></div>`);
    const tooltip = marker.bindTooltip(stop.name, {
        permanent: false,
        direction: stop.labelDirection || "top",
        offset: stop.labelDirection === "left" ? [-18, 0] : stop.labelDirection === "right" ? [18, 0] : [0, -18],
        className: `stop-map-tooltip${isTerminal ? " stop-map-tooltip--terminal" : ""}`
    });
    stopTooltips.push({ marker, tooltip });
});

function highlightTripStops(startName, destinationName, transferPlan) {
    transferWalkLines.forEach(line => map.removeLayer(line));
    transferWalkLines = [];
    const transferExits = new Set((transferPlan?.transfers || []).map(transfer => transfer.exitStop));
    const transferBoardings = new Set((transferPlan?.transfers || []).map(transfer => transfer.boardingStop));

    stopMarkers.forEach(({ marker, isTerminal }, stopName) => {
        const transferType = transferExits.has(stopName)
            ? "exit"
            : (transferBoardings.has(stopName) ? "boarding" : "");
        const tripEndpoint = stopName === startName
            ? "start"
            : (stopName === destinationName ? "destination" : "");
        marker.setIcon(stopMarkerIcon(isTerminal, transferType, tripEndpoint));
        marker.setZIndexOffset(transferType || tripEndpoint ? 1100 : 0);
        marker.options.title = transferType === "exit"
            ? `Get off the bus here: ${stopName}`
            : (transferType === "boarding"
                ? `Board the next bus here: ${stopName}`
                : (tripEndpoint === "start"
                    ? `Trip starts here: ${stopName}`
                    : (tripEndpoint === "destination" ? `Trip destination: ${stopName}` : `${isTerminal ? "Terminal" : "Bus stop"}: ${stopName}`)));
    });

    (transferPlan?.transfers || []).forEach(transfer => {
        if (transfer.exitStop === transfer.boardingStop) {
            return;
        }
        const exitLocation = stopByName(transfer.exitStop);
        const boardingLocation = stopByName(transfer.boardingStop);
        if (exitLocation && boardingLocation) {
            const walkLine = L.polyline(
                [[exitLocation.lat, exitLocation.lng], [boardingLocation.lat, boardingLocation.lng]],
                { color: "#246bce", dashArray: "6 8", opacity: 0.9, weight: 4 }
            ).addTo(map);
            walkLine.bindTooltip(`About ${transfer.walkingDistanceMeters} m between stops (straight-line estimate)`, { sticky: true });
            transferWalkLines.push(walkLine);
        }
    });
}

function updateStopLabels() {
    const showLabels = map.getZoom() >= 15;
    stopTooltips.forEach(({ marker, tooltip }) => {
        tooltip.options.permanent = showLabels;
        if (showLabels) {
            marker.openTooltip();
        } else {
            marker.closeTooltip();
        }
    });
}

function highlightTransferRoutes(transferPlan) {
    const routes = transferPlan.legs
        .map(leg => routeDefinitions.find(route => route.id === leg.routeId))
        .filter(Boolean);
    highlightRoute(routes[0]);
    routes.slice(1).forEach(route => {
        if (route.line) {
            route.line.setStyle({ weight: 6, opacity: 0.9, dashArray: "" });
            route.lineCasing.bringToFront();
            route.line.bringToFront();
        }
    });
}

function zoomToTransferPlan(transferPlan) {
    const lines = transferPlan.legs
        .map(leg => routeDefinitions.find(route => route.id === leg.routeId)?.line)
        .filter(Boolean);
    if (lines.length > 1) {
        map.fitBounds(L.featureGroup(lines).getBounds(), { padding: [35, 35], maxZoom: 14 });
    }
}

map.on("zoomend", updateStopLabels);
updateStopLabels();

const stopLegend = L.control({ position: "bottomleft" });
stopLegend.onAdd = () => {
    const legend = L.DomUtil.create("div", "stop-map-legend");
    legend.setAttribute("role", "group");
    legend.setAttribute("aria-label", "Map stop marker legend");
    legend.innerHTML = '<span><i class="stop-map-legend-marker">B</i> Bus stop</span><span><i class="stop-map-legend-marker stop-map-legend-marker--terminal">T</i> Terminal</span><span><i class="stop-map-legend-marker stop-map-legend-marker--start">S</i> Start</span><span><i class="stop-map-legend-marker stop-map-legend-marker--destination">D</i> Destination</span><span class="stop-map-legend-hint">Zoom in for stop names</span>';
    L.DomEvent.disableClickPropagation(legend);
    return legend;
};
stopLegend.addTo(map);

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
    route.lineCasing = L.polyline(route.path, {
        color: "#ffffff",
        weight: 10,
        opacity: 0.9,
        interactive: false
    }).addTo(map);
    route.line = L.polyline(route.path, {
        color: route.color,
        weight: 5,
        opacity: 0.9
    }).addTo(map);

    (route.signals || []).forEach(signal => {
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
    try {
        const getRoadPath = async points => {
            const coordinates = points.map(([lat, lng]) => `${lng},${lat}`).join(";");
            const url = `https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`;
            const response = await fetch(url);
            if (!response.ok) {
                return null;
            }
            const result = await response.json();
            const roadPath = result.code === "Ok" ? result.routes?.[0]?.geometry?.coordinates : null;
            return roadPath?.length ? roadPath.map(([lng, lat]) => [lat, lng]) : null;
        };

        let roadPath = await getRoadPath(route.path);
        if (!roadPath) {
            roadPath = [];
            for (let index = 0; index < route.path.length - 1; index += 1) {
                const roadSegment = await getRoadPath([route.path[index], route.path[index + 1]]);
                if (roadSegment) {
                    roadPath.push(...(roadPath.length ? roadSegment.slice(1) : roadSegment));
                } else {
                    roadPath.push(route.path[index + 1]);
                }
            }
        }

        if (roadPath.length) {
            const sampleInterval = Math.max(1, Math.ceil((roadPath.length - 1) / 100));
            const sampledRoadPath = roadPath.filter((_, index) => index % sampleInterval === 0);
            if (sampledRoadPath[sampledRoadPath.length - 1] !== roadPath[roadPath.length - 1]) {
                sampledRoadPath.push(roadPath[roadPath.length - 1]);
            }
            route.path = sampledRoadPath;
            route.lineCasing.setLatLngs(route.path);
            route.line.setLatLngs(route.path);
        }
    } catch (error) {
        // Keep the local route waypoints when the public router is unavailable.
    }
}

const fromLocationInput = document.getElementById("from-location");
const toLocationInput = document.getElementById("to-location");
const fareSummary = document.getElementById("fare-summary");
const stopSuggestions = document.getElementById("stop-suggestions");
const tripStatus = document.getElementById("trip-status");
const routeList = document.getElementById("route-list");
const routeCount = document.getElementById("route-count");
const tripDetails = document.getElementById("trip-details");
const busEta = document.getElementById("bus-eta");
const nearestStop = document.getElementById("nearest-stop");
const passingStopList = document.getElementById("passing-stop-list");
const transferGuidance = document.getElementById("transfer-guidance");
const transferAlternatives = document.getElementById("transfer-alternatives");
const rideActionButton = document.getElementById("ride-action-button");
const findButton = document.getElementById("find-routes-button");
const liveStatusList = document.getElementById("live-status-list");
let selectedRoute = null;
let rideState = "waiting";
let rideArrivalTimer = null;
let routeSearchRunning = false;
let currentTransferPlan = null;
let currentTransferOptions = [];
let activeTransferLeg = 1;
let transferWalkLines = [];
let fleet = [];

[fromLocationInput, toLocationInput].forEach(input => {
    input.addEventListener("input", () => {
        fareSummary.hidden = true;
    });
});

const routeSchedules = {
    "east-express": { eta: 8, hours: "6 AM - 10 PM" },
    "north-route": { eta: 12, hours: "6 AM - 10 PM" },
    "west-route": { eta: 10, hours: "6 AM - 10 PM" },
    "arca-south-express": { eta: 15, hours: "6:40 AM from Arca South · 5 PM from BGC" },
    "central-route": { eta: 7, hours: "6 AM - 10 PM" },
    "night-route": { eta: 15, hours: "10 PM - 6 AM" },
    "lrt-ayala-route": { eta: 6, hours: "6 - 9 AM · 5 - 8 PM" }
};

const routeCatalog = routeDefinitions.map(route => ({
    id: route.id,
    name: route.name,
    liveName: route.name,
    code: route.code,
    from: route.terminals[0],
    to: route.terminals[route.terminals.length - 1],
    stops: route.stops,
    baseEta: routeSchedules[route.id]?.eta || 5,
    eta: `${routeSchedules[route.id]?.eta || 5} min`,
    schedule: routeSchedules[route.id]?.hours || "Service hours not listed",
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

function renderRoutes(routes, startName = null, destinationName = null) {
    routeList.innerHTML = "";
    routeCount.textContent = `${routes.length} found`;
    const recommendedRouteIndex = routes.findIndex(routeItem => routeItem.recommended && routeItem.available !== false);
    const firstAvailableRouteIndex = routes.findIndex(routeItem => routeItem.available !== false);
    const selectedRouteIndex = recommendedRouteIndex >= 0
        ? recommendedRouteIndex
        : (startName && destinationName ? firstAvailableRouteIndex : -1);

    routes.forEach((routeItem, index) => {
        const card = document.createElement("article");
        card.className = `route-card${index === selectedRouteIndex ? " selected" : ""}${routeItem.available === false ? " unavailable" : ""}`;
        card.dataset.routeId = routeItem.id;
        card.style.setProperty("--route-color", routeItem.color);
        const routeNotice = routeItem.warning && routeItem.available !== false
            ? `<div class="route-warning" role="status">Road/event notice affects this route</div>`
            : "";
        const transferNotice = routeItem.transferLeg
            ? `<div class="route-warning route-warning--transfer" role="status">${routeItem.transferStop ? `Transfer after this leg: ${routeItem.transferStop}` : "Final bus leg"}</div>`
            : "";
        const servesTrip = startName && destinationName &&
            routeStopSegment(routeItem, startName, destinationName).length >= 2;
        const displayFrom = routeItem.transferLeg ? routeItem.from : (servesTrip ? startName : routeItem.from);
        const displayTo = routeItem.transferLeg ? routeItem.to : (servesTrip ? destinationName : routeItem.to);
        const fareStart = routeItem.transferLeg ? routeItem.from : (servesTrip ? startName : null);
        const fareDestination = routeItem.transferLeg ? routeItem.to : (servesTrip ? destinationName : null);
        const cardFare = fareStart && fareDestination && routeStopSegment(routeItem, fareStart, fareDestination).length >= 2
            ? fareForDistance(routeDistanceKm(routeItem, fareStart, fareDestination))
            : null;
        card.innerHTML = `
            <div class="route-card-top">
                <strong><i class="status-dot" style="background: ${routeItem.color}"></i> ${routeItem.name} <small>${routeItem.code}</small></strong>
                <span class="route-badge ${routeItem.recommended || routeItem.transferLeg ? "route-badge--recommended" : ""} ${routeItem.available === false ? "delay" : ""}">${routeItem.transferLeg ? `TRANSFER ${routeItem.transferLeg}/${routeItem.transferCount}` : (routeItem.recommended ? "RECOMMENDED" : routeItem.status)}</span>
            </div>
            <div class="route-card-meta"><span>${displayFrom} → ${displayTo}</span><strong>${routeItem.eta}</strong></div>
            <div class="route-card-meta route-card-meta--status">
                <span>ETA ${routeItem.available === false ? "Unavailable" : routeItem.eta}</span>
                <span class="route-line" style="background: ${routeItem.color}"></span>
            </div>
            <div class="route-service-hours">${routeItem.schedule}</div>
            ${cardFare === null ? "" : `<div class="route-card-fare">Estimated fare: ${formatFare(cardFare)}</div>`}
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
            const servesBoth = routeStopSegment(route, startName, destinationName).length >= 2;
            const startPosition = route.stops.indexOf(startName);
            const servesStart = startPosition >= 0;
            let score = 0;

            if (servesBoth) {
                score += 100;
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
        .map((item, index) => ({
            ...item.route,
            recommended: index === 0 && item.route.available && item.servesBoth
        }));
}

function findTransferSuggestions(startName, destinationName) {
    const availableRoutes = routeCatalog.filter(route => route.available);
    const suggestions = new Map();
    const maximumWalkingDistanceMeters = 500;
    const maximumRideLegs = 3;

    function recordSuggestion(legs, transfers) {
        const rideSegments = legs.reduce((total, leg) => {
            const route = availableRoutes.find(candidate => candidate.id === leg.routeId);
            return total + routeStopSegment(route, leg.from, leg.to).length - 1;
        }, 0);
        const walkingDistanceMeters = transfers.reduce((total, transfer) => total + transfer.walkingDistanceMeters, 0);
        const suggestion = {
            legs,
            transfers,
            rideSegments,
            walkingDistanceMeters,
            score: walkingDistanceMeters + rideSegments * 250 + transfers.length * 500
        };
        const key = legs.map(leg => `${leg.routeId}:${leg.from}>${leg.to}`).join("|") +
            "|" + transfers.map(transfer => `${transfer.exitStop}>${transfer.boardingStop}`).join("|");
        suggestions.set(key, suggestion);
    }

    function search(currentStop, legs, transfers, usedRouteIds) {
        if (legs.length >= maximumRideLegs) {
            return;
        }

        availableRoutes.forEach(route => {
            if (usedRouteIds.has(route.id)) {
                return;
            }

            if (routeStopSegment(route, currentStop, destinationName).length >= 2) {
                recordSuggestion([...legs, { routeId: route.id, from: currentStop, to: destinationName }], transfers);
            }

            const reachableStops = route.stops.filter(stopName =>
                activeStopNames.has(stopName) &&
                routeStopSegment(route, currentStop, stopName).length >= 2
            );
            reachableStops.forEach(exitStop => {
                const leg = { routeId: route.id, from: currentStop, to: exitStop };
                const nextLegs = [...legs, leg];
                if (nextLegs.length >= maximumRideLegs) {
                    return;
                }

                const exitLocation = stopByName(exitStop);
                if (!exitLocation) {
                    return;
                }

                availableRoutes.forEach(nextRoute => {
                    if (usedRouteIds.has(nextRoute.id) || nextRoute.id === route.id) {
                        return;
                    }

                    nextRoute.stops.filter(stopName => activeStopNames.has(stopName)).forEach(boardingStop => {
                        const boardingLocation = stopByName(boardingStop);
                        if (!boardingLocation) {
                            return;
                        }

                        const walkingDistanceMeters = Math.round(distanceBetween(exitLocation, boardingLocation) * 1000);
                        if (walkingDistanceMeters > maximumWalkingDistanceMeters) {
                            return;
                        }

                        const transfer = { exitStop, boardingStop, walkingDistanceMeters };
                        const nextTransfers = [...transfers, transfer];
                        search(
                            boardingStop,
                            nextLegs,
                            nextTransfers,
                            new Set([...usedRouteIds, route.id])
                        );
                    });
                });
            });
        });
    }

    search(startName, [], [], new Set());

    return [...suggestions.values()]
        .sort((first, second) =>
            first.transfers.length - second.transfers.length ||
            first.walkingDistanceMeters - second.walkingDistanceMeters ||
            first.rideSegments - second.rideSegments ||
            first.score - second.score
        )
        .slice(0, 3);
}

function transferRouteCards(plan, startName, destinationName) {
    const transferRoutes = plan.legs.map((leg, index) => {
        const route = routeCatalog.find(candidate => candidate.id === leg.routeId);
        const transfer = plan.transfers[index];
        return {
            ...route,
            from: leg.from,
            to: leg.to,
            recommended: false,
            transferLeg: index + 1,
            transferCount: plan.legs.length,
            transferStop: transfer ? `${transfer.exitStop} → ${transfer.boardingStop}` : null
        };
    });
    const legIds = new Set(transferRoutes.map(route => route.id));
    return [...transferRoutes, ...rankRoutes(startName, destinationName).filter(route => !legIds.has(route.id))];
}

function showTransferPlan(plan, startName, destinationName, suggestions) {
    currentTransferPlan = plan;
    currentTransferOptions = suggestions;
    activeTransferLeg = 1;
    selectedRoute = routeDefinitions.find(route => route.id === plan.legs[0].routeId) || null;
    highlightTripStops(startName, destinationName, plan);
    highlightTransferRoutes(plan);
    renderRoutes(transferRouteCards(plan, startName, destinationName));
    updateTrip(startName, destinationName, plan, suggestions);
    zoomToTransferPlan(plan);

    const routeNames = plan.legs.map(leg => routeCatalog.find(route => route.id === leg.routeId).name);
    const changes = plan.transfers.map((transfer, index) => {
        const walkText = transfer.walkingDistanceMeters > 0
            ? `walk about ${transfer.walkingDistanceMeters} m to ${transfer.boardingStop}`
            : `change buses at ${transfer.boardingStop}`;
        return `get off at ${transfer.exitStop}, ${walkText}, then board ${routeNames[index + 1]}`;
    });
    tripStatus.textContent = `No direct route. Take ${routeNames[0]}${changes.length ? `; ${changes.join("; ")}` : ""} toward ${destinationName}.`;
    tripStatus.className = "trip-status is-active";
}

function selectRoute(routeItem, card) {
    if (routeItem.available === false) {
        tripStatus.textContent = "This route is temporarily unavailable. Please choose another route or wait for the operating notice to clear.";
        tripStatus.className = "trip-status is-warning";
        return;
    }

    if (routeItem.transferLeg && currentTransferPlan) {
        routeList.querySelectorAll(".route-card").forEach(item => item.classList.remove("selected"));
        card.classList.add("selected");
        selectedRoute = routeDefinitions.find(route => route.id === routeItem.id) || null;
        highlightTransferRoutes(currentTransferPlan);
        zoomToTransferPlan(currentTransferPlan);
        return;
    }

    const startName = resolveStop(fromLocationInput.value)?.name;
    const destinationName = resolveStop(toLocationInput.value)?.name;
    if (!startName || !destinationName) {
        tripStatus.textContent = "Choose a starting point and destination before selecting a route.";
        tripStatus.className = "trip-status";
        return;
    }

    if (routeStopSegment(routeItem, startName, destinationName).length < 2) {
        tripStatus.textContent = `${routeItem.name} does not serve both selected stops. Your start and destination have not changed.`;
        tripStatus.className = "trip-status is-warning";
        return;
    }

    routeList.querySelectorAll(".route-card").forEach(item => item.classList.remove("selected"));
    card.classList.add("selected");
    currentTransferPlan = null;
    currentTransferOptions = [];
    activeTransferLeg = 1;
    selectedRoute = routeDefinitions.find(route => route.id === routeItem.id) || null;
    highlightRoute(selectedRoute);
    zoomToRoute(selectedRoute);

    highlightTripStops(startName, destinationName, null);
    updateTrip(startName, destinationName);
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
        route.lineCasing.bringToFront();
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
    document.getElementById("fare-summary").hidden = true;
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
            return route.available && routeStopSegment(route, startName, destinationName).length >= 2;
        });
        const transferSuggestions = directRoute ? [] : findTransferSuggestions(startName, destinationName);
        const transferSuggestion = transferSuggestions[0] || null;
        const routes = rankRoutes(startName, destinationName);

        if (transferSuggestion) {
            showTransferPlan(transferSuggestion, startName, destinationName, transferSuggestions);
        } else {
            renderRoutes(routes, startName, destinationName);
            currentTransferPlan = null;
            currentTransferOptions = [];
            activeTransferLeg = 1;
            selectedRoute = directRoute || routeDefinitions.find(route => route.id === routes[0]?.id) || null;
            highlightTripStops(startName, destinationName, null);
            highlightRoute(selectedRoute);
            if (directRoute) {
                updateTrip(startName, destinationName);
                zoomToRoute(selectedRoute);
            } else {
                tripDetails.hidden = true;
                transferGuidance.hidden = true;
                transferAlternatives.hidden = true;
            }
            const recommendedRoute = routes.find(route => route.recommended);
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

fleet = routeBuses
    .filter(bus => Number(bus.is_online) === 1)
    .map(bus => {
        const route = routeDefinitions.find(candidate => candidate.id === bus.route_id);
        if (!route || !routeIsOnline(route)) {
            return null;
        }

        const position = (Number(bus.bus_number) - 1) % route.path.length;
        const marker = L.marker(route.path[position], { icon: busIcon }).addTo(map);
        marker.bindPopup(`<b>🚌 Bus #${Number(bus.bus_number)}</b><br>Plate: ${bus.plate_number}<br>${route.name}`);
        return { route, marker, position, waitTicks: Number(bus.bus_number) % 2 };
    })
    .filter(Boolean);

renderLiveStatus();

routeDefinitions.forEach(loadRoadGeometry);

function isNear(first, second) {
    return Math.abs(first[0] - second[0]) < 0.0008 && Math.abs(first[1] - second[1]) < 0.0008;
}

function moveFleet() {
    fleet.forEach(bus => {
        if (bus.waitTicks > 0) {
            bus.waitTicks -= 1;
            return;
        }

        bus.position = (bus.position + 1) % bus.route.path.length;
        const nextPoint = bus.route.path[bus.position];
        bus.marker.setLatLng(nextPoint);

        const atStop = bus.route.stops.some(stopName => isNear(nextPoint, point(stopName)));
        const atSignal = (bus.route.signals || []).some(signal => isNear(nextPoint, signal));
        if (atStop) {
            bus.waitTicks = 2;
        } else if (atSignal) {
            bus.waitTicks = 1;
        }
    });

    refreshRouteDisplays();
}

function updateTrip(startName = resolveStop(fromLocationInput.value)?.name, destinationName = resolveStop(toLocationInput.value)?.name, transferPlan = null, transferOptions = []) {
    clearTimeout(rideArrivalTimer);
    rideState = "waiting";

    if (!startName || !destinationName) {
        tripStatus.textContent = "Choose a starting point and destination.";
        tripDetails.hidden = true;
        transferGuidance.hidden = true;
        transferAlternatives.hidden = true;
        return;
    }

    if (!activeStopNames.size || !activeStopNames.has(startName) || !activeStopNames.has(destinationName)) {
        tripStatus.textContent = "This stop is temporarily closed. Please choose another stop from the active network.";
        tripStatus.className = "trip-status is-warning";
        tripDetails.hidden = true;
        transferGuidance.hidden = true;
        transferAlternatives.hidden = true;
        return;
    }

    if (startName === destinationName) {
        tripStatus.textContent = "Choose a different destination stop.";
        tripStatus.className = "trip-status";
        tripDetails.hidden = true;
        transferGuidance.hidden = true;
        transferAlternatives.hidden = true;
        return;
    }

    const activeLeg = transferPlan?.legs[activeTransferLeg - 1] || null;
    const legStartName = activeLeg?.from || startName;
    const matchingRoute = transferPlan
        ? routeDefinitions.find(route => route.id === activeLeg?.routeId)
        : (selectedRoute && routeStopSegment(selectedRoute, startName, destinationName).length >= 2
            ? selectedRoute
            : routeDefinitions.find(route => routeStopSegment(route, startName, destinationName).length >= 2)) ||
            routeDefinitions.find(route => route.stops.includes(startName));
    selectedRoute = matchingRoute || selectedRoute;
    const legDestinationName = activeLeg?.to || destinationName;
    const passingStops = routeStopSegment(matchingRoute, legStartName, legDestinationName);
    updateFare(startName, destinationName, transferPlan, matchingRoute);
    const transferRideSegments = transferPlan
        ? transferPlan.legs.reduce((total, leg) => {
            const route = routeCatalog.find(candidate => candidate.id === leg.routeId);
            return total + Math.max(0, routeStopSegment(route, leg.from, leg.to).length - 1);
        }, 0)
        : Math.max(0, passingStops.length - 1);
    const northRouteExtraMinutes = transferPlan
        ? (transferPlan.legs.some(leg => leg.routeId === "north-route") ? 12 : 0)
        : (matchingRoute?.id === "north-route" ? 12 : 0);
    const walkingDistanceMeters = transferPlan
        ? transferPlan.transfers.reduce((total, transfer) => total + transfer.walkingDistanceMeters, 0)
        : 0;
    const walkingMinutes = Math.ceil(walkingDistanceMeters / 67);
    const estimatedMinutes = 4 + transferRideSegments * 3 + walkingMinutes + northRouteExtraMinutes;

    busEta.textContent = `${estimatedMinutes} min`;
    nearestStop.textContent = legStartName;
    passingStopList.replaceChildren();
    passingStops.forEach(stop => {
        const item = document.createElement("li");
        const transferAfterActiveLeg = transferPlan?.transfers[activeTransferLeg - 1];
        const transferBeforeActiveLeg = transferPlan?.transfers[activeTransferLeg - 2];
        if (transferAfterActiveLeg && stop === transferAfterActiveLeg.exitStop) {
            item.className = "passing-stop--exit";
            const exitLabel = document.createElement("span");
            exitLabel.className = "passing-stop-exit-label";
            exitLabel.textContent = "GET OFF";
            item.append(exitLabel, document.createTextNode(stop));
        } else {
            item.textContent = stop;
        }
        if (transferBeforeActiveLeg && stop === transferBeforeActiveLeg.boardingStop) {
            item.classList.add("passing-stop--boarding");
            const boardingLabel = document.createElement("span");
            boardingLabel.className = "passing-stop-endpoint-label";
            boardingLabel.textContent = "BOARD";
            item.prepend(boardingLabel, document.createTextNode(" "));
        } else if (stop === legStartName) {
            item.classList.add("passing-stop--start");
            const startLabel = document.createElement("span");
            startLabel.className = "passing-stop-endpoint-label";
            startLabel.textContent = "START";
            item.prepend(startLabel, document.createTextNode(" "));
        }
        if (stop === destinationName && (!transferPlan || activeTransferLeg === transferPlan.legs.length)) {
            item.classList.add("passing-stop--destination");
            const destinationLabel = document.createElement("span");
            destinationLabel.className = "passing-stop-endpoint-label passing-stop-endpoint-label--destination";
            destinationLabel.textContent = "DESTINATION";
            item.append(" ", destinationLabel);
        }
        passingStopList.appendChild(item);
    });

    transferGuidance.replaceChildren();
    transferGuidance.hidden = !transferPlan;
    transferAlternatives.replaceChildren();
    transferAlternatives.hidden = true;
    if (transferPlan) {
        const heading = document.createElement("strong");
        heading.className = "transfer-guidance-heading";
        heading.textContent = "TRANSFER PLAN";
        const steps = document.createElement("ol");
        steps.className = "transfer-steps";
        const instructions = [];
        transferPlan.legs.forEach((leg, index) => {
            const route = routeCatalog.find(candidate => candidate.id === leg.routeId);
            instructions.push({ text: `Ride ${route.name} toward ${leg.to}.` });
            const transfer = transferPlan.transfers[index];
            if (transfer) {
                const walkingMinutes = Math.max(1, Math.ceil(transfer.walkingDistanceMeters / 67));
                instructions.push({ text: `Get off the bus at ${transfer.exitStop}.`, isExit: true });
                instructions.push({
                    text: transfer.walkingDistanceMeters > 0
                        ? `Walk toward ${transfer.boardingStop} (about ${transfer.walkingDistanceMeters} m straight-line, roughly ${walkingMinutes} min; actual walking route may be longer).`
                        : `Stay at ${transfer.boardingStop} to change buses.`
                });
                const nextRoute = routeCatalog.find(candidate => candidate.id === transferPlan.legs[index + 1].routeId);
                instructions.push({ text: `Board ${nextRoute.name}.` });
            }
        });

        instructions.forEach((instruction, index) => {
            const step = document.createElement("li");
            step.className = instruction.isExit ? "transfer-step--exit" : "";
            const number = document.createElement("span");
            number.className = "transfer-step-number";
            number.textContent = String(index + 1);
            const text = document.createElement("span");
            text.textContent = instruction.text;
            step.append(number, text);
            steps.appendChild(step);
        });

        transferGuidance.append(heading, steps);

        if (activeTransferLeg === 1 && transferOptions.length > 1) {
            transferAlternatives.hidden = false;
            const alternativesHeading = document.createElement("strong");
            alternativesHeading.className = "transfer-alternatives-heading";
            alternativesHeading.textContent = "NEARBY TRANSFER OPTIONS";
            transferAlternatives.appendChild(alternativesHeading);
            transferOptions.forEach((option, index) => {
                const button = document.createElement("button");
                button.className = "transfer-alternative";
                button.type = "button";
                const isSelected = JSON.stringify(option.legs) === JSON.stringify(transferPlan.legs) &&
                    JSON.stringify(option.transfers) === JSON.stringify(transferPlan.transfers);
                if (isSelected) {
                    button.classList.add("is-selected");
                }
                button.setAttribute("aria-pressed", String(isSelected));
                const routeNames = option.legs.map(leg =>
                    routeCatalog.find(route => route.id === leg.routeId).name
                );
                const transferLabel = option.transfers.map(transfer =>
                    `${transfer.exitStop} → ${transfer.boardingStop} (${transfer.walkingDistanceMeters} m)`
                ).join("; ");
                button.textContent = `Option ${index + 1}: ${routeNames.join(" → ")} · ${transferLabel}`;
                button.addEventListener("click", () => {
                    showTransferPlan(option, startName, destinationName, transferOptions);
                });
                transferAlternatives.appendChild(button);
            });
        }
    }

    tripDetails.hidden = false;
    rideActionButton.disabled = false;
    const activeRoute = transferPlan
        ? routeCatalog.find(route => route.id === activeLeg.routeId)
        : null;
    const previousTransfer = transferPlan?.transfers[activeTransferLeg - 2];
    rideActionButton.textContent = previousTransfer
        ? `Board ${activeRoute.name}`
        : "Bus is here!";
    tripStatus.textContent = previousTransfer
        ? `Walk toward ${activeLeg.from}${previousTransfer.walkingDistanceMeters > 0 ? ` (about ${previousTransfer.walkingDistanceMeters} m straight-line)` : ""}, then board ${activeRoute.name} to continue to ${destinationName}.`
        : `Ready to travel from ${startName} to ${destinationName}.`;
    tripStatus.className = "trip-status";
}

function handleRideAction() {
    if (rideState === "waiting") {
        rideState = "riding";
        rideActionButton.disabled = true;
        const activeRoute = currentTransferPlan
            ? routeCatalog.find(route => route.id === currentTransferPlan.legs[activeTransferLeg - 1].routeId)
            : selectedRoute;
        rideActionButton.textContent = activeTransferLeg > 1 && currentTransferPlan
            ? `Riding ${activeRoute.name}`
            : "You are riding the bus";
        const nextTransfer = currentTransferPlan?.transfers[activeTransferLeg - 1];
        tripStatus.textContent = nextTransfer
            ? `Riding ${activeRoute.name}. We will notify you when you reach ${nextTransfer.exitStop}.`
            : "You are on the bus. We will notify you near your destination.";
        tripStatus.className = "trip-status is-active";

        // Short demo timer represents the bus approaching the destination.
        rideArrivalTimer = setTimeout(() => {
            const reachedTransferStop = Boolean(nextTransfer);
            rideState = reachedTransferStop ? "transfer-arrived" : "arrived";
            rideActionButton.disabled = false;
            rideActionButton.textContent = reachedTransferStop
                ? `Proceed to ${routeCatalog.find(route => route.id === currentTransferPlan.legs[activeTransferLeg].routeId).name}`
                : "I'm at my destination";
            tripStatus.textContent = reachedTransferStop
                ? `You have reached ${nextTransfer.exitStop}. Get off here, then walk to ${nextTransfer.boardingStop} for your next route.`
                : "You are near your destination.";
            tripStatus.className = "trip-status is-active";
        }, 8000);
        return;
    }

    if (rideState === "transfer-arrived" && currentTransferPlan) {
        activeTransferLeg += 1;
        const activeLeg = currentTransferPlan.legs[activeTransferLeg - 1];
        selectedRoute = routeDefinitions.find(route => route.id === activeLeg.routeId) || null;
        highlightTransferRoutes(currentTransferPlan);
        highlightTripStops(
            resolveStop(fromLocationInput.value)?.name,
            resolveStop(toLocationInput.value)?.name,
            currentTransferPlan
        );
        updateTrip(
            resolveStop(fromLocationInput.value)?.name,
            resolveStop(toLocationInput.value)?.name,
            currentTransferPlan,
            currentTransferOptions
        );
        routeList.querySelectorAll(".route-card").forEach(card => {
            card.classList.toggle("selected", card.dataset.routeId === activeLeg.routeId);
        });
        zoomToTransferPlan(currentTransferPlan);
        tripStatus.className = "trip-status is-active";
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

setInterval(moveFleet, 1000);