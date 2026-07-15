/**
 * Floorplan Widgets System
 * Main widget initialization, updates, and interactions
 */

(function() {
  'use strict';

  const FloorplanWidgets = {
    gridstack: null,
    editMode: false,
    updateInterval: null,

    /**
     * Initialize the widget system
     */
    init: function() {
      const canvas = document.getElementById('floorCanvas');
      if (!canvas) return;

      this.setupEditModeToggle();
      this.renderWidgets();
      this.startLiveUpdates();
    },

    /**
     * Render widgets from WIDGET_DATA
     */
    renderWidgets: function() {
      const canvas = document.getElementById('floorCanvas');
      if (!canvas || !window.WIDGET_DATA) return;

      canvas.innerHTML = '';

      window.WIDGET_DATA.forEach(widget => {
        const element = this.createWidgetElement(widget);
        canvas.appendChild(element);
      });
    },

    /**
     * Create a single widget element
     */
    createWidgetElement: function(widget) {
      const div = document.createElement('div');
      div.className = `widget-station ${widget.status.toLowerCase()}`;
      div.id = widget.id;
      div.dataset.stationId = widget.stationId;
      div.style.left = widget.posX + 'px';
      div.style.top = widget.posY + 'px';
      div.style.width = widget.width + 'px';
      div.style.height = widget.height + 'px';

      const status = widget.isPaused ? 'paused' : (widget.isActive ? 'busy' : 'free');

      let content = `
        <div class="widget-header">
          <div class="widget-title">
            <span class="widget-icon">🎮</span>
            <span>${widget.name}</span>
          </div>
          <div class="widget-status-badge ${status}">${status.toUpperCase()}</div>
        </div>
        <div class="widget-info">
      `;

      if (widget.customerName) {
        content += `<div class="widget-info-row"><span class="widget-info-label">👤</span> <span class="widget-info-value">${widget.customerName}</span></div>`;
      }

      if (widget.isActive) {
        content += `<div class="widget-info-row"><span class="widget-info-label">⏱</span> <span class="widget-info-value" data-elapsed>${this.formatTime(widget.elapsedMin)}</span></div>`;
      }

      if (widget.gameTitle) {
        content += `<div class="widget-info-row"><span class="widget-info-label">🎮</span> <span class="widget-info-value">${widget.gameTitle}</span></div>`;
      }

      if (widget.budget > 0) {
        content += `<div class="widget-budget">💶 ${widget.currencySymbol}${widget.budget.toFixed(2)}</div>`;
      }

      content += `
        </div>
        <div class="widget-actions">
          <button class="widget-btn primary" onclick="FloorplanWidgets.startSession(${widget.stationId})">▶ Start</button>
          <button class="widget-btn" onclick="FloorplanWidgets.pauseSession(${widget.stationId})">⏸ Pause</button>
          <button class="widget-btn danger" onclick="FloorplanWidgets.endSession(${widget.stationId})">■ End</button>
        </div>
        <div class="widget-resize-handle"></div>
      `;

      div.innerHTML = content;
      return div;
    },

    /**
     * Format elapsed minutes to HH:MM:SS
     */
    formatTime: function(minutes) {
      const hours = Math.floor(minutes / 60);
      const mins = minutes % 60;
      return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:00`;
    },

    /**
     * Setup edit mode toggle button
     */
    setupEditModeToggle: function() {
      const btn = document.getElementById('toggleEditModeBtn');
      if (btn) {
        btn.addEventListener('click', () => this.toggleEditMode());
      }
    },

    /**
     * Toggle edit mode
     */
    toggleEditMode: function() {
      this.editMode = !this.editMode;
      const canvas = document.getElementById('floorCanvas');
      const btn = document.getElementById('toggleEditModeBtn');

      if (this.editMode) {
        canvas.classList.add('edit-mode');
        if (btn) btn.textContent = 'Save Layout';
      } else {
        canvas.classList.remove('edit-mode');
        this.saveLayout();
        if (btn) btn.textContent = 'Edit Floor';
      }
    },

    /**
     * Save layout via AJAX
     */
    saveLayout: function() {
      const canvas = document.getElementById('floorCanvas');
      const zoneId = canvas.dataset.zoneId;
      const layout = [];

      document.querySelectorAll('.widget-station').forEach(widget => {
        layout.push({
          stationId: parseInt(widget.dataset.stationId),
          posX: parseInt(widget.style.left) || 0,
          posY: parseInt(widget.style.top) || 0,
          width: parseInt(widget.style.width) || 250,
          height: parseInt(widget.style.height) || 150
        });
      });

      fetch('station_action.php?action=save_layout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ zone_id: zoneId, layout: layout })
      }).catch(err => console.error('Layout save error:', err));
    },

    /**
     * Start session
     */
    startSession: function(stationId) {
      this.performAction(stationId, 'start');
    },

    /**
     * Pause session
     */
    pauseSession: function(stationId) {
      this.performAction(stationId, 'pause');
    },

    /**
     * End session
     */
    endSession: function(stationId) {
      if (confirm('End this session?')) {
        this.performAction(stationId, 'end');
      }
    },

    /**
     * Perform action via AJAX
     */
    performAction: function(stationId, action) {
      const formData = new FormData();
      formData.append('station_id', stationId);
      formData.append('action', action);

      fetch('station_action.php', {
        method: 'POST',
        body: formData
      })
      .then(() => this.refreshFloorState())
      .catch(err => console.error('Action error:', err));
    },

    /**
     * Refresh floor state from server
     */
    refreshFloorState: function() {
      fetch('floor_state.php')
        .then(r => r.json())
        .then(data => {
          window.WIDGET_DATA = data.widgets;
          this.renderWidgets();
        })
        .catch(err => console.error('State refresh error:', err));
    },

    /**
     * Start live updates
     */
    startLiveUpdates: function() {
      this.updateInterval = setInterval(() => {
        this.refreshFloorState();
      }, 5000);
    },

    /**
     * Stop live updates
     */
    stopLiveUpdates: function() {
      if (this.updateInterval) {
        clearInterval(this.updateInterval);
      }
    }
  };

  window.FloorplanWidgets = FloorplanWidgets;

  /**
   * Global action functions for inline onclick handlers
   */
  window.startSession = (id) => FloorplanWidgets.startSession(id);
  window.pauseSession = (id) => FloorplanWidgets.pauseSession(id);
  window.endSession = (id) => FloorplanWidgets.endSession(id);

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FloorplanWidgets.init());
  } else {
    FloorplanWidgets.init();
  }
})();
