/**
 * GridStack Widget Manager
 * Converts stations into resizable widgets with grid snapping
 */

let gridInstance = null;

function initializeGridStack() {
  const canvas = document.getElementById('floorCanvas');
  if (!canvas) return;

  // Initialize GridStack
  gridInstance = GridStack.init({
    container: '#floorCanvas',
    float: true,
    disableDrag: false,
    disableResize: false,
    cellHeight: 'auto',
    minW: 3,
    minH: 3,
    margin: 12,
    columnInvariant: false,
    animate: true,
  });

  // Load widget positions from data attributes
  loadWidgetPositions();

  // Listen for changes
  gridInstance.on('change', function(event, items) {
    handleGridChange(items);
  });

  gridInstance.on('added', function(event, items) {
    saveAllWidgetPositions();
  });

  gridInstance.on('removed', function(event, items) {
    saveAllWidgetPositions();
  });
}

function loadWidgetPositions() {
  if (!gridInstance) return;

  const stations = document.querySelectorAll('.station[data-station-id]');
  stations.forEach((stationEl) => {
    const stationId = stationEl.getAttribute('data-station-id');
    const gsX = parseInt(stationEl.getAttribute('data-gs-x') || '0', 10);
    const gsY = parseInt(stationEl.getAttribute('data-gs-y') || '0', 10);
    const gsW = parseInt(stationEl.getAttribute('data-gs-w') || '3', 10);
    const gsH = parseInt(stationEl.getAttribute('data-gs-h') || '4', 10);

    gridInstance.makeWidget(stationEl);
    gridInstance.update(stationEl, gsX, gsY, gsW, gsH);
  });
}

function handleGridChange(items) {
  // Debounce saves
  clearTimeout(window.gridSaveTimeout);
  window.gridSaveTimeout = setTimeout(() => {
    saveAllWidgetPositions();
  }, 1500);
}

function saveAllWidgetPositions() {
  if (!gridInstance) return;

  const items = gridInstance.engine.nodes;
  items.forEach((node) => {
    if (node.el && node.el.dataset.stationId) {
      const stationId = node.el.dataset.stationId;
      saveWidgetPosition(stationId, node.x, node.y, node.w, node.h);
    }
  });
}

function saveWidgetPosition(stationId, x, y, w, h) {
  const canvas = document.getElementById('floorCanvas');
  const zoneId = canvas.getAttribute('data-zone-id');

  const body = new URLSearchParams();
  body.set('station_id', stationId);
  body.set('action', 'position');
  body.set('gs_x', x);
  body.set('gs_y', y);
  body.set('gs_w', w);
  body.set('gs_h', h);
  body.set('return_zone', zoneId);

  fetch('station_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  }).catch(function() {
    console.error('Failed to save widget position for station ' + stationId);
  });
}

function toggleEditMode() {
  const canvas = document.getElementById('floorCanvas');
  const editButton = document.getElementById('toggleEditModeBtn');

  if (!canvas) return;

  const isEditing = canvas.classList.toggle('edit-mode');

  if (isEditing) {
    enableEditMode();
    editButton.textContent = 'Done Editing';
  } else {
    disableEditMode();
    editButton.textContent = 'Edit Floor';
  }

  closeAllStationModals();
}

function enableEditMode() {
  if (!gridInstance) return;

  const canvas = document.getElementById('floorCanvas');
  canvas.classList.add('edit-mode');

  // Enable dragging and resizing
  gridInstance.opts.disableDrag = false;
  gridInstance.opts.disableResize = false;

  // Update station elements
  document.querySelectorAll('.station[data-station-id]').forEach((el) => {
    el.classList.add('widget-editing');
  });
}

function disableEditMode() {
  if (!gridInstance) return;

  const canvas = document.getElementById('floorCanvas');
  canvas.classList.remove('edit-mode');

  // Disable dragging and resizing
  gridInstance.opts.disableDrag = true;
  gridInstance.opts.disableResize = true;

  // Update station elements
  document.querySelectorAll('.station[data-station-id]').forEach((el) => {
    el.classList.remove('widget-editing');
  });

  // Save final positions
  saveAllWidgetPositions();
}

function maximizeWidget(stationId) {
  if (!gridInstance) return;

  const stationEl = document.querySelector(`.station[data-station-id="${stationId}"]`);
  if (!stationEl) return;

  const isMaximized = stationEl.classList.toggle('widget-maximized');

  if (isMaximized) {
    // Store original position
    stationEl.dataset.originalX = gridInstance.getGridItem(stationEl).x;
    stationEl.dataset.originalY = gridInstance.getGridItem(stationEl).y;
    stationEl.dataset.originalW = gridInstance.getGridItem(stationEl).w;
    stationEl.dataset.originalH = gridInstance.getGridItem(stationEl).h;

    // Maximize
    gridInstance.update(stationEl, 0, 0, 12, 12);
  } else {
    // Restore original position
    const x = parseInt(stationEl.dataset.originalX, 10) || 0;
    const y = parseInt(stationEl.dataset.originalY, 10) || 0;
    const w = parseInt(stationEl.dataset.originalW, 10) || 3;
    const h = parseInt(stationEl.dataset.originalH, 10) || 4;

    gridInstance.update(stationEl, x, y, w, h);
  }

  saveWidgetPosition(stationId, gridInstance.getGridItem(stationEl).x, gridInstance.getGridItem(stationEl).y, gridInstance.getGridItem(stationEl).w, gridInstance.getGridItem(stationEl).h);
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
  // Wait for GridStack library to be available
  if (typeof GridStack !== 'undefined') {
    initializeGridStack();
  } else {
    console.warn('GridStack library not loaded');
  }

  // Setup edit mode button
  const editButton = document.getElementById('toggleEditModeBtn');
  if (editButton) {
    editButton.addEventListener('click', toggleEditMode);
  }

  // Setup double-click maximize
  document.addEventListener('dblclick', function(event) {
    const station = event.target.closest('.station[data-station-id]');
    if (station && document.getElementById('floorCanvas').classList.contains('edit-mode')) {
      const stationId = station.getAttribute('data-station-id');
      maximizeWidget(stationId);
    }
  });
});
