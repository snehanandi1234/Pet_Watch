(function () {
  'use strict';

  const state = {
    search: '',
    page: 1,
  };

  const petwatchMapState = {
    initialized: false,
    map: null,
    markersLayer: null,
    // "petsForMap" is seeded from PHP-embedded JSON and also refreshed via AJAX.
    petsForMap: {},
    userLocationSet: false,
    userMarker: null,
  };

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (c) {
      switch (c) {
        case '&':
          return '&amp;';
        case '<':
          return '&lt;';
        case '>':
          return '&gt;';
        case '"':
          return '&quot;';
        case "'":
          return '&#039;';
        default:
          return c;
      }
    });
  }

  function statusBadgeHtml(statusRaw) {
    const status = String(statusRaw || '').toLowerCase();
    if (status === 'found') return '<span class="badge bg-success">Found</span>';
    if (status === 'lost') return '<span class="badge bg-danger">Lost</span>';
    return '<span class="badge bg-secondary">Unknown</span>';
  }

  function buildMarkerPopupHtml(marker) {
    const petsForMap = petwatchMapState.petsForMap || {};
    const pet = petsForMap[String(marker.pet_id)] || {};

    const petName = escapeHtml(pet.name || 'Pet');
    const petSpecies = escapeHtml(pet.species || '');
    const statusHtml = statusBadgeHtml(pet.status);

    const photoUrl = pet.photo_url ? escapeHtml(pet.photo_url) : '';
    const photoHtml = photoUrl
      ? `<img src="${photoUrl}" alt="${petName}" class="petwatch-popup-photo" loading="lazy" />`
      : '';

    const timestamp = escapeHtml(marker.timestamp || '');
    const tsHtml = timestamp ? `<div class="small text-muted mt-1">Reported: ${timestamp}</div>` : '';

    const comment = marker.comment ? escapeHtml(marker.comment).replace(/\n/g, '<br>') : '';
    const commentHtml = comment ? `<div class="small mt-1">${comment}</div>` : '';

    return `
      <div class="petwatch-popup">
        ${photoHtml}
        <div class="fw-semibold">${petName}</div>
        ${petSpecies ? `<div class="text-muted small">${petSpecies}</div>` : ''}
        <div class="mt-1">${statusHtml}</div>
        ${tsHtml}
        ${commentHtml}
      </div>
    `;
  }

  function renderPetwatchMarkers(markers) {
    if (!petwatchMapState.initialized || !petwatchMapState.markersLayer) return;

    petwatchMapState.markersLayer.clearLayers();

    const list = Array.isArray(markers) ? markers : [];
    const boundsPoints = [];

    list.forEach((m) => {
      const lat = m && m.lat;
      const lon = m && m.lon;
      const latNum = typeof lat === 'number' ? lat : parseFloat(lat);
      const lonNum = typeof lon === 'number' ? lon : parseFloat(lon);
      if (!Number.isFinite(latNum) || !Number.isFinite(lonNum)) return;

      boundsPoints.push([latNum, lonNum]);

      const marker = L.marker([latNum, lonNum]);
      marker.bindPopup(buildMarkerPopupHtml(m), { maxWidth: 340 });
      petwatchMapState.markersLayer.addLayer(marker);
    });

    if (!petwatchMapState.userLocationSet && boundsPoints.length > 0) {
      const bounds = L.latLngBounds(boundsPoints);
      petwatchMapState.map.fitBounds(bounds, { padding: [20, 20] });
    }
  }

  function initPetwatchMap() {
    const mapEl = document.getElementById('petwatch-map');
    if (!mapEl) return;
    if (typeof L === 'undefined') return;
    if (petwatchMapState.initialized) return;

    // Fix default marker icon URLs when Leaflet is loaded from a CDN.
    if (L.Icon && L.Icon.Default) {
      // eslint-disable-next-line no-underscore-dangle
      delete L.Icon.Default.prototype._getIconUrl;
      L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
      });
    }

    // Seed state from PHP-embedded values.
    const petsForMap = (window.PETWATCH && window.PETWATCH.petsForMap) ? window.PETWATCH.petsForMap : {};
    const markers = (window.PETWATCH && window.PETWATCH.mapMarkers) ? window.PETWATCH.mapMarkers : [];
    petwatchMapState.petsForMap = petsForMap || {};

    petwatchMapState.map = L.map('petwatch-map', { scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(petwatchMapState.map);

    petwatchMapState.markersLayer = L.layerGroup().addTo(petwatchMapState.map);
    petwatchMapState.initialized = true;

    renderPetwatchMarkers(markers);

    const statusEl = document.getElementById('petwatch-map-status');
    if (statusEl) statusEl.classList.remove('d-none');
    if (statusEl) statusEl.textContent = 'Locating you…';

    if (navigator.geolocation && navigator.geolocation.getCurrentPosition) {
      navigator.geolocation.getCurrentPosition(
        function onSuccess(position) {
          const userLat = position && position.coords ? position.coords.latitude : null;
          const userLon = position && position.coords ? position.coords.longitude : null;

          if (!Number.isFinite(userLat) || !Number.isFinite(userLon)) {
            if (statusEl) statusEl.textContent = '';
            return;
          }

          petwatchMapState.userLocationSet = true;
          if (petwatchMapState.map) {
            petwatchMapState.map.setView([userLat, userLon], 13);
          }

          // Add/update a dedicated "You are here" marker.
          if (petwatchMapState.map) {
            if (petwatchMapState.userMarker) {
              petwatchMapState.userMarker.setLatLng([userLat, userLon]);
            } else {
              petwatchMapState.userMarker = L.marker([userLat, userLon], {
                title: 'You are here',
              }).addTo(petwatchMapState.map);
            }
            if (petwatchMapState.userMarker && petwatchMapState.userMarker.getPopup) {
              petwatchMapState.userMarker.bindPopup('You are here', { maxWidth: 160 });
            }
          }

          if (statusEl) {
            statusEl.textContent = '';
            statusEl.classList.add('d-none');
          }
        },
        function onError() {
          petwatchMapState.userLocationSet = false;
          if (statusEl) {
            statusEl.textContent = '';
            statusEl.classList.add('d-none');
          }
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
      );
    } else if (statusEl) {
      statusEl.textContent = '';
      statusEl.classList.add('d-none');
    }
  }

  function getCsrfToken() {
    return (window.PETWATCH && window.PETWATCH.csrfToken) ? window.PETWATCH.csrfToken : '';
  }

  function setAlert(el, messages) {
    if (!el) return;
    const msg = Array.isArray(messages) ? messages.join('<br>') : String(messages || '');
    el.innerHTML = msg;
    el.classList.remove('d-none');
  }

  function hideAlert(el) {
    if (!el) return;
    el.classList.add('d-none');
    el.textContent = '';
  }

  async function apiGetJson(url) {
    const csrfToken = getCsrfToken();
    const res = await fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken,
      },
      credentials: 'same-origin',
    });
    return res.json();
  }

  async function apiPostFormJson(url, formData) {
    const csrfToken = getCsrfToken();
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken,
      },
      body: formData,
      credentials: 'same-origin',
    });
    return res.json();
  }

  function updateUrlParams(nextPage, nextSearch) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(nextPage));
    if (nextSearch && nextSearch.trim() !== '') {
      url.searchParams.set('search', nextSearch);
    } else {
      url.searchParams.delete('search');
    }
    window.history.pushState({}, '', url.toString());
  }

  async function loadPets(nextPage, nextSearch) {
    const listContainer = document.getElementById('pet-list-container');
    const paginationContainer = document.getElementById('pet-pagination-container');
    if (!listContainer || !paginationContainer) return;

    const params = new URLSearchParams();
    params.set('action', 'list');
    params.set('page', String(nextPage));
    if (nextSearch && nextSearch.trim() !== '') params.set('search', nextSearch);

    const url = `Controllers/apiPet.php?${params.toString()}`;
    const data = await apiGetJson(url);
    if (!data.ok) {
      setAlert(document.getElementById('pet-add-error') || listContainer, data.errors || ['Failed to load pets.']);
      return;
    }

    listContainer.innerHTML = data.petsHtml || '';
    paginationContainer.innerHTML = data.paginationHtml || '';

    // Update map markers when the map is present.
    if (window.PETWATCH) {
      window.PETWATCH.petsForMap = data.petsForMap || window.PETWATCH.petsForMap || {};
      window.PETWATCH.mapMarkers = data.mapMarkers || window.PETWATCH.mapMarkers || [];
    }
    if (petwatchMapState.initialized) {
      petwatchMapState.petsForMap = (window.PETWATCH && window.PETWATCH.petsForMap) ? window.PETWATCH.petsForMap : {};
      renderPetwatchMarkers(data.mapMarkers || []);
    }
  }

  async function loadSightingsBlock(petId) {
    const wrapper = document.getElementById(`sightings-${petId}`);
    if (!wrapper) return;

    const params = new URLSearchParams();
    params.set('action', 'list');
    params.set('pet_id', String(petId));
    const url = `Controllers/apiSighting.php?${params.toString()}`;
    const data = await apiGetJson(url);
    if (!data.ok) return;

    const temp = document.createElement('div');
    temp.innerHTML = data.html || '';
    const newEl = temp.firstElementChild;
    if (newEl && newEl.id === wrapper.id) {
      wrapper.replaceWith(newEl);
    } else {
      wrapper.outerHTML = data.html || wrapper.outerHTML;
    }
  }

  function initFromUrl() {
    const url = new URL(window.location.href);
    const page = parseInt(url.searchParams.get('page') || '1', 10);
    const search = (url.searchParams.get('search') || '').toString();
    state.page = Number.isFinite(page) && page > 0 ? page : 1;
    state.search = search;
  }

  document.addEventListener('DOMContentLoaded', function () {
    initFromUrl();

    initPetwatchMap();

    const petSearchForm = document.getElementById('pet-search-form');
    const petAddForm = document.getElementById('pet-add-form');
    const petEditForm = document.getElementById('pet-edit-form');

    // Search
    if (petSearchForm) {
      petSearchForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const searchInput = petSearchForm.querySelector('input[name="search"]');
        const search = searchInput ? searchInput.value : '';
        state.search = search;
        state.page = 1;
        updateUrlParams(state.page, state.search);
        loadPets(state.page, state.search);
      });
    }

    // Pet Add (AJAX)
    if (petAddForm) {
      petAddForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const errBox = document.getElementById('pet-add-error');
        hideAlert(errBox);

        const fd = new FormData(petAddForm);
        const data = await apiPostFormJson('Controllers/apiPet.php?action=add', fd);
        if (!data.ok) {
          setAlert(errBox, data.errors || ['Failed to add pet.']);
          return;
        }

        // Refresh list to reflect changes.
        loadPets(state.page, state.search);
        petAddForm.reset();
      });
    }

    // Pet Edit (AJAX)
    if (petEditForm) {
      petEditForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const errBox = document.getElementById('pet-edit-error');
        hideAlert(errBox);

        const fd = new FormData(petEditForm);
        const data = await apiPostFormJson('Controllers/apiPet.php?action=update', fd);
        if (!data.ok) {
          setAlert(errBox, data.errors || ['Failed to update pet.']);
          return;
        }

        window.location.href = 'index.php';
      });
    }

    // Pagination (AJAX)
    document.addEventListener('click', function (e) {
      const target = e.target;
      if (!target) return;
      const a = target.closest && target.closest('.pagination a.page-link');
      if (!a) return;

      const href = a.getAttribute('href') || '';
      if (!href || href.indexOf('index.php') === -1) return;

      e.preventDefault();
      const url = new URL(href, window.location.origin);
      const nextPage = parseInt(url.searchParams.get('page') || '1', 10);
      const nextSearch = (url.searchParams.get('search') || '').toString();
      state.page = Number.isFinite(nextPage) && nextPage > 0 ? nextPage : 1;
      state.search = nextSearch;

      updateUrlParams(state.page, state.search);
      loadPets(state.page, state.search);
    });

    // Pet Delete (AJAX)
    document.addEventListener('click', function (e) {
      const btn = e.target && e.target.closest && e.target.closest('.ajax-pet-delete');
      if (!btn) return;
      e.preventDefault();

      const petId = btn.getAttribute('data-pet-id');
      if (!petId) return;
      if (!window.confirm('Delete this pet?')) return;

      const fd = new FormData();
      fd.append('id', String(petId));

      apiPostFormJson('Controllers/apiPet.php?action=delete', fd).then(function (data) {
        if (!data.ok) {
          // Best-effort: show error in add error box if present.
          const errBox = document.getElementById('pet-add-error');
          setAlert(errBox, data.errors || ['Failed to delete pet.']);
          return;
        }

        loadPets(state.page, state.search);
      });
    });

    // Sightings Add (AJAX)
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!form || !form.matches || !form.matches('.ajax-sighting-add-form')) return;
      e.preventDefault();

      const petId = form.getAttribute('data-pet-id');
      if (!petId) return;

      const errBox = document.getElementById(`sighting-error-${petId}`);
      hideAlert(errBox);

      const fd = new FormData(form);
      apiPostFormJson('Controllers/apiSighting.php?action=add', fd).then(function (data) {
        if (!data.ok) {
          setAlert(errBox, data.errors || ['Failed to add sighting.']);
          return;
        }

        // When the map is enabled, refresh the full page markers so the new sighting appears.
        if (petwatchMapState.initialized) {
          loadPets(state.page, state.search);
        } else {
          // Replace only the sightings wrapper.
          loadSightingsBlock(petId);
        }
        form.reset();
      });
    });
  });
})();

