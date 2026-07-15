// --- Station session navigation ---
function openStationSessionPage(stationId, event) {
  var canvas = document.getElementById('floorCanvas');
  if (canvas && canvas.classList.contains('edit-mode')) {
    if (event) event.stopPropagation();
    return;
  }
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  window.location.href = 'station_session.php?station_id=' + stationId;
}

function toggleStationModal(stationId, event) {
  openStationSessionPage(stationId, event);
}

function closeStationModal(stationId) {
  var modal = document.getElementById('modal-' + stationId);
  if (modal) modal.style.display = 'none';
}

function closeAllStationModals() {
  document.querySelectorAll('.station-modal-backdrop').forEach(function (modal) {
    modal.style.display = 'none';
  });
}

document.addEventListener('click', function (event) {
  var openModal = document.querySelector('.station-modal-backdrop[style*="display: flex"]');
  if (!openModal) return;
  if (!openModal.querySelector('.station-modal').contains(event.target)) {
    closeAllStationModals();
  }
});

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') {
    closeAllStationModals();
  }
});

function formatCurrency(value) {
  return '$' + Number(value || 0).toFixed(2);
}

function formatElapsedText(startMs, pausedMs, pauseStartMs) {
  if (!startMs) return '';
  pausedMs = Number(pausedMs || 0);
  if (pauseStartMs) {
    pausedMs += Math.max(0, Date.now() - Number(pauseStartMs));
  }
  var elapsedSec = Math.max(0, Math.floor((Date.now() - startMs - pausedMs) / 1000));
  var h = Math.floor(elapsedSec / 3600);
  var m = Math.floor((elapsedSec % 3600) / 60);
  var s = elapsedSec % 60;
  var parts = [];
  if (h) parts.push(h + 'h');
  parts.push(m + 'm');
  parts.push(s + 's');
  return parts.join(' ');
}

function tickTimers() {
  document.querySelectorAll('.live-timer[data-start]').forEach(function (el) {
    var startMs = parseInt(el.getAttribute('data-start'), 10);
    if (!startMs) return;
    var pausedMs = parseInt(el.getAttribute('data-paused-duration') || '0', 10);
    var pauseStartMs = parseInt(el.getAttribute('data-pause-start') || '0', 10);
    el.textContent = formatElapsedText(startMs, pausedMs, pauseStartMs);
  });
}

function updateFloorState(data) {
  var stations = data.stations || [];
  stations.forEach(function (station) {
    var stationEl = document.querySelector('.station[data-station-id="' + station.id + '"]');
    var modal = document.getElementById('modal-' + station.id);
    if (!stationEl && !modal) return;

    var isActive = station.status !== 'FREE';
    var isPaused = station.status === 'PAUSED';
    stationEl && stationEl.classList.toggle('station-busy', station.status === 'BUSY');
    stationEl && stationEl.classList.toggle('station-paused', isPaused);
    stationEl && stationEl.classList.toggle('station-free', !isActive);

    var badge = stationEl ? stationEl.querySelector('[data-status-badge]') : null;
    if (badge) {
      badge.textContent = isActive ? station.elapsed_label : 'Free';
    }

    var budgetEl = stationEl ? stationEl.querySelector('[data-budget-value]') : null;
    if (budgetEl) {
      budgetEl.textContent = isActive ? formatCurrency(station.current_budget) : '';
      budgetEl.style.display = isActive ? 'block' : 'none';
    }

    var alertBadge = stationEl ? stationEl.querySelector('.station-alert-badge') : null;
    if (stationEl) {
      if (station.warning_label) {
        if (!alertBadge) {
          alertBadge = document.createElement('div');
          alertBadge.className = 'station-alert-badge';
          stationEl.appendChild(alertBadge);
        }
        alertBadge.textContent = station.warning_label;
      } else if (alertBadge) {
        alertBadge.remove();
      }
    }

    if (modal) {
      var statusPill = modal.querySelector('.station-status-pill');
      if (statusPill) {
        statusPill.textContent = station.status === 'PAUSED' ? 'Paused' : isActive ? 'Busy' : 'Free';
        statusPill.classList.toggle('busy', station.status === 'BUSY');
        statusPill.classList.toggle('free', !isActive);
      }

      var timer = modal.querySelector('.live-timer');
      if (timer) {
        timer.setAttribute('data-start', station.session_start || '');
        timer.setAttribute('data-paused-duration', station.paused_duration_ms || '0');
        timer.setAttribute('data-pause-start', station.pause_started_at || '');
        timer.textContent = isActive ? formatElapsedText(parseInt(station.session_start, 10) || 0, parseInt(station.paused_duration_ms || '0', 10), parseInt(station.pause_started_at || '0', 10)) : '';
      }

      var budgetValue = modal.querySelector('.station-budget-value');
      if (budgetValue) {
        budgetValue.textContent = isActive ? formatCurrency(station.current_budget) : '$0.00';
      }
    }
  });
}

function refreshFloorState() {
  var canvas = document.getElementById('floorCanvas');
  if (!canvas) return;
  var zoneId = canvas.getAttribute('data-zone-id') || '';
  fetch('floor_state.php?zone=' + encodeURIComponent(zoneId), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(function (response) {
      if (!response.ok) throw new Error('Network response was not ok');
      return response.json();
    })
    .then(function (data) {
      updateFloorState(data);
      tickTimers();
    })
    .catch(function () {
      // keep the page responsive even if polling briefly fails
    });
}

(function () {
  var canvas = document.getElementById('floorCanvas');
  if (!canvas) return;

  var editButton = document.getElementById('toggleEditModeBtn');
  if (editButton) {
    editButton.addEventListener('click', function () {
      var isEditing = canvas.classList.toggle('edit-mode');
      editButton.textContent = isEditing ? 'Done Editing' : 'Edit Floor';
      closeAllStationModals();
    });
  }

  tickTimers();
  setInterval(tickTimers, 1000);
  setInterval(refreshFloorState, 8000);
  refreshFloorState();

  var dragEl = null;
  var offsetX = 0;
  var offsetY = 0;

  canvas.querySelectorAll('.station.draggable').forEach(function (stationEl) {
    stationEl.addEventListener('pointerdown', function (e) {
      if (!canvas.classList.contains('edit-mode')) return;
      dragEl = stationEl;
      var rect = canvas.getBoundingClientRect();
      offsetX = e.clientX - rect.left - stationEl.offsetLeft;
      offsetY = e.clientY - rect.top - stationEl.offsetTop;
      stationEl.setPointerCapture(e.pointerId);
    });
  });

  canvas.addEventListener('pointermove', function (e) {
    if (!dragEl || !canvas.classList.contains('edit-mode')) return;
    dragEl.classList.add('dragging');
    var rect = canvas.getBoundingClientRect();
    var x = e.clientX - rect.left - offsetX;
    var y = e.clientY - rect.top - offsetY;
    x = Math.max(0, Math.min(canvas.clientWidth - dragEl.offsetWidth, x));
    y = Math.max(0, Math.min(canvas.clientHeight - dragEl.offsetHeight, y));
    dragEl.style.left = x + 'px';
    dragEl.style.top = y + 'px';
  });

  function endDrag() {
    if (!dragEl || !canvas.classList.contains('edit-mode')) return;
    var wasDragging = dragEl.classList.contains('dragging');
    dragEl.classList.remove('dragging');
    var stationId = dragEl.getAttribute('data-station-id');
    var x = parseInt(dragEl.style.left, 10) || 0;
    var y = parseInt(dragEl.style.top, 10) || 0;
    dragEl = null;

    if (!wasDragging) return;

    var zoneId = canvas.getAttribute('data-zone-id') || '';
    var body = new URLSearchParams();
    body.set('station_id', stationId);
    body.set('action', 'position');
    body.set('pos_x', x);
    body.set('pos_y', y);
    body.set('return_zone', zoneId);

    fetch('station_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).catch(function () {
      refreshFloorState();
    });
  }

  canvas.addEventListener('pointerup', endDrag);
  canvas.addEventListener('pointercancel', endDrag);
})();
