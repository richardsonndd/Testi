/**
 * Enhanced Floor Plan with Resizable Widgets
 * Replaces simple dragging with GridStack.js for professional widget management
 */

let gridstackInstance = null;
const WIDGET_CONFIGS = {}; // Store widget size preferences

// ============================================
// Station Widget Management
// ============================================

function formatCurrency(value, symbol = '€') {
  return symbol + Number(value || 0).toFixed(2);
}

function formatElapsedText(startMs, pausedMs, pauseStartMs) {
  if (!startMs) return '--:--:--';
  pausedMs = Number(pausedMs || 0);
  if (pauseStartMs) {
    pausedMs += Math.max(0, Date.now() - Number(pauseStartMs));
  }
  const elapsedSec = Math.max(0, Math.floor((Date.now() - startMs - pausedMs) / 1000));
  const h = Math.floor(elapsedSec / 3600);
  const m = Math.floor((elapsedSec % 3600) / 60);
  const s = elapsedSec % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function tickTimers() {
  document.querySelectorAll('.live-timer[data-start]').forEach(function (el) {
    const startMs = parseInt(el.getAttribute('data-start'), 10);
    if (!startMs) return;
    const pausedMs = parseInt(el.getAttribute('data-paused-duration') || '0', 10);
    const pauseStartMs = parseInt(el.getAttribute('data-pause-start') || '0', 10);
    el.textContent = formatElapsedText(startMs, pausedMs, pauseStartMs);
  });
}

function getStationStatus(station) {
  const isActive = station.status !== 'FREE';
  const isPaused = station.status === 'PAUSED';
  return { isActive, isPaused };
}

function createStationWidget(station, settings = {}) {
  const { isActive, isPaused } = getStationStatus(station);
  const widgetSize = WIDGET_CONFIGS[station.id] || { w: 3, h: 4 };
  
  const container = document.createElement('div');
  container.className = 'grid-stack-item';
  container.setAttribute('gs-x', station.pos_x || 0);
  container.setAttribute('gs-y', station.pos_y || 0);
  container.setAttribute('gs-w', widgetSize.w);
  container.setAttribute('gs-h', widgetSize.h);
  container.setAttribute('gs-id', station.id);
  container.setAttribute('data-station-id', station.id);
  
  const statusClass = isPaused ? 'status-paused' : (isActive ? 'status-busy' : 'status-free');
  const statusText = isPaused ? '⏸ PAUSED' : (isActive ? '🔴 BUSY' : '🟢 FREE');
  
  // Calculate budget if active
  let budget = 0;
  let budgetDisplay = '—';
  const currencySymbol = settings.currency_symbol || '€';
  
  if (isActive && station.session_start) {
    const nowMs = Date.now();
    const startMs = parseInt(station.session_start);
    const pausedMs = parseInt(station.paused_duration_ms || 0);
    const elapsedMin = Math.floor(Math.max(0, nowMs - startMs - pausedMs) / 60000);
    const rate = parseFloat(station.current_rate_per_hour || 0);
    const ordersTotal = parseFloat(station.orders_total || 0);
    const sessionFee = parseFloat(station.current_session_fee || 0);
    budget = (rate * (elapsedMin / 60)) + ordersTotal + sessionFee;
    budgetDisplay = formatCurrency(budget, currencySymbol);
  }
  
  container.innerHTML = `
    <div class="station-widget ${statusClass}">
      <div class="widget-header">
        <div class="widget-title">
          <span class="widget-icon">🎮</span>
          <span class="widget-name">${station.name}</span>
        </div>
        <div class="widget-status-badge">${statusText}</div>
      </div>
      
      <div class="widget-content">
        <div class="info-row">
          <span class="info-label">👤 Customer:</span>
          <span class="info-value">${station.customer_name || '—'}</span>
        </div>
        
        <div class="info-row">
          <span class="info-label">⏱ Time:</span>
          <span class="info-value live-timer" data-start="${station.session_start || ''}" data-paused-duration="${station.paused_duration_ms || '0'}">00:00:00</span>
        </div>
        
        <div class="info-row">
          <span class="info-label">💶 Total:</span>
          <span class="info-value budget-display">${budgetDisplay}</span>
        </div>
        
        <div class="info-row">
          <span class="info-label">🎮 Game:</span>
          <span class="info-value">${station.console_type || '—'}</span>
        </div>
      </div>
      
      <div class="widget-actions">
        <button class="widget-btn widget-btn-play" title="Start">▶</button>
        <button class="widget-btn widget-btn-pause" title="Pause">⏸</button>
        <button class="widget-btn widget-btn-stop" title="Stop">■</button>
        <button class="widget-btn widget-btn-food" title="Order">🍔</button>
        <button class="widget-btn widget-btn-bill" title="Bill">🧾</button>
      </div>
      
      <div class="widget-resize-handle">◢</div>
    </div>
  `;
  
  attachWidgetEventListeners(container, station);
  return container;
}

function attachWidgetEventListeners(container, station) {
  const stationId = station.id;
  
  // Action buttons
  container.querySelector('.widget-btn-play').addEventListener('click', (e) => {
    e.stopPropagation();
    startSession(stationId);
  });
  
  container.querySelector('.widget-btn-pause').addEventListener('click', (e) => {
    e.stopPropagation();
    pauseSession(stationId);
  });
  
  container.querySelector('.widget-btn-stop').addEventListener('click', (e) => {
    e.stopPropagation();
    if (confirm('End this session?')) {
      endSession(stationId);
    }
  });
  
  container.querySelector('.widget-btn-food').addEventListener('click', (e) => {
    e.stopPropagation();
    openOrderModal(stationId);
  });
  
  container.querySelector('.widget-btn-bill').addEventListener('click', (e) => {
    e.stopPropagation();
    openBillModal(stationId);
  });
  
  // Main widget click opens station details
  container.querySelector('.station-widget').addEventListener('click', (e) => {
    if (!e.target.closest('.widget-btn')) {
      openStationSessionPage(stationId);
    }
  });
}

function startSession(stationId) {
  // Placeholder - implement actual functionality
  alert('Start session for station ' + stationId);
}

function pauseSession(stationId) {
  // Placeholder - implement actual functionality
  alert('Pause session for station ' + stationId);
}

function endSession(stationId) {
  // Placeholder - implement actual functionality
  alert('End session for station ' + stationId);
}

function openOrderModal(stationId) {
  // Placeholder - implement actual functionality
  alert('Open order modal for station ' + stationId);
}

function openBillModal(stationId) {
  // Placeholder - implement actual functionality
  alert('Open bill modal for station ' + stationId);
}

function openStationSessionPage(stationId, event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  window.location.href = 'station_session.php?station_id=' + stationId;
}

// ============================================
// GridStack Initialization & Lifecycle
// ============================================

function initializeGridStack() {
  const canvas = document.getElementById('floorCanvas');
  if (!canvas) return;
  
  // Clear existing content
  canvas.innerHTML = '';
  
  // Create grid wrapper
  const grid = document.createElement('div');
  grid.className = 'grid-stack';
  grid.id = 'gridStackContainer';
  
  // Get stations data from page
  const stationElements = document.querySelectorAll('[data-station-id]');
  const settings = {
    currency_symbol: document.documentElement.getAttribute('data-currency-symbol') || '€'
  };
  
  // Reconstruct stations array from data attributes
  const stations = [];
  document.querySelectorAll('.station-widget-data').forEach(el => {
    const data = JSON.parse(el.textContent);
    stations.push(data);
  });
  
  // Create widgets
  stations.forEach(station => {
    const widget = createStationWidget(station, settings);
    grid.appendChild(widget);
  });
  
  canvas.appendChild(grid);
  
  // Initialize GridStack
  if (window.GridStack) {
    gridstackInstance = window.GridStack.init({
      column: 6,
      float: true,
      animate: true
    }, grid);
    
    gridstackInstance.on('change', (event, items) => {
      saveGridLayout();
    });
  }
  
  // Start timers
  tickTimers();
  setInterval(tickTimers, 1000);
  setInterval(refreshFloorState, 8000);
}

function saveGridLayout() {
  if (!gridstackInstance) return;
  
  const layout = gridstackInstance.save();
  const zoneId = document.getElementById('floorCanvas').getAttribute('data-zone-id');
  
  const body = new URLSearchParams();
  body.set('action', 'save_layout');
  body.set('zone_id', zoneId);
  body.set('layout', JSON.stringify(layout));
  
  fetch('station_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  }).catch(err => console.error('Layout save failed:', err));
}

function updateFloorState(data) {
  const stations = data.stations || [];
  
  stations.forEach(function (station) {
    const widgetEl = document.querySelector(`[data-station-id="${station.id}"] .station-widget`);
    if (!widgetEl) return;
    
    const { isActive, isPaused } = getStationStatus(station);
    const statusClass = isPaused ? 'status-paused' : (isActive ? 'status-busy' : 'status-free');
    const statusText = isPaused ? '⏸ PAUSED' : (isActive ? '🔴 BUSY' : '🟢 FREE');
    
    // Update status
    widgetEl.className = `station-widget ${statusClass}`;
    widgetEl.querySelector('.widget-status-badge').textContent = statusText;
    
    // Update time
    const timer = widgetEl.querySelector('.live-timer');
    if (timer) {
      timer.setAttribute('data-start', station.session_start || '');
      timer.setAttribute('data-paused-duration', station.paused_duration_ms || '0');
    }
    
    // Update budget
    const budgetDisplay = widgetEl.querySelector('.budget-display');
    if (budgetDisplay && isActive) {
      const currencySymbol = document.documentElement.getAttribute('data-currency-symbol') || '€';
      budgetDisplay.textContent = formatCurrency(station.current_budget || 0, currencySymbol);
    }
  });
}

function refreshFloorState() {
  const canvas = document.getElementById('floorCanvas');
  if (!canvas) return;
  
  const zoneId = canvas.getAttribute('data-zone-id') || '';
  
  fetch('floor_state.php?zone=' + encodeURIComponent(zoneId), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(response => {
      if (!response.ok) throw new Error('Network response was not ok');
      return response.json();
    })
    .then(data => {
      updateFloorState(data);
      tickTimers();
    })
    .catch(err => console.error('Floor state refresh failed:', err));
}

// ============================================
// Edit Mode Toggle
// ============================================

function toggleEditMode() {
  const button = document.getElementById('toggleEditModeBtn');
  const gridContainer = document.getElementById('gridStackContainer');
  
  if (!gridContainer) return;
  
  gridContainer.classList.toggle('edit-mode');
  const isEditing = gridContainer.classList.contains('edit-mode');
  
  button.textContent = isEditing ? '✓ Done Editing' : '✎ Edit Layout';
  button.classList.toggle('editing', isEditing);
}

// ============================================
// Initialize on DOM ready
// ============================================

document.addEventListener('DOMContentLoaded', function () {
  // Load GridStack library
  const script = document.createElement('script');
  script.src = 'assets/js/gridstack.min.js';
  script.onload = function () {
    initializeGridStack();
  };
  document.head.appendChild(script);
  
  // Setup edit mode button
  const editButton = document.getElementById('toggleEditModeBtn');
  if (editButton) {
    editButton.addEventListener('click', toggleEditMode);
  }
});