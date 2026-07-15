# Resizable Widget System Implementation

## Overview
This system replaces the simple draggable stations with professional resizable widgets powered by GridStack.js.

## Files Added

### 1. **assets/js/gridstack.min.js**
- GridStack.js library (minified, ~3KB)
- Handles grid layout, drag, drop, and resize functionality
- Professional grid snapping and collision detection

### 2. **assets/css/gridstack.min.css**
- Base GridStack styles
- Grid container and item styling

### 3. **assets/js/floorplan-widgets.js**
- Main widget system implementation
- Station widget creation and management
- Live timer updates
- Budget calculations
- Edit mode toggle
- Auto-save layout functionality

### 4. **assets/css/station-widgets.css**
- Professional widget styling
- Status-based color schemes (Free/Busy/Paused)
- Responsive design (desktop, tablet, mobile)
- Action button styling
- Animations and transitions

### 5. **includes/widget-helpers.php**
- PHP helper functions for integration
- Data preparation functions
- Asset enqueueing

## Widget Features

### Visual Design
```
┌─────────────────────────────┐
│ 🎮 PS5 #1      🟢 FREE      │  ← Header with icon, name, status
├─────────────────────────────┤
│ 👤 Customer: Ahmed           │  ← Dynamic info rows
│ ⏱ Time: 00:01:24            │
│ 💶 Total: €4.50             │
│ 🎮 FC26                      │
├─────────────────────────────┤
│ ▶ ⏸ ■ 🍔 🧾                 │  ← Action buttons
└─────────────────────────────┘
                    ◢ Resize handle
```

### Key Features
- **Drag & Drop**: Move stations around the floor with mouse/touch
- **Resize**: Drag the bottom-right corner to resize widgets
- **Grid Snapping**: Automatic alignment to grid
- **Live Updates**: Real-time status, timer, and budget updates
- **Edit Mode**: Toggle between viewing and editing layouts
- **Status Indicators**: Color-coded (Green=Free, Red=Busy, Orange=Paused)
- **Animations**: Smooth transitions and hover effects
- **Responsive**: Works on desktop, tablet, and mobile
- **Auto-save**: Layout changes saved automatically

## Integration Steps

### 1. Include Widget Helpers in dashboard.php
```php
require_once __DIR__ . '/includes/widget-helpers.php';

// In your dashboard header
enqueue_widget_assets();

// Prepare widget data
$widget_data = prepare_station_widget_data($conn, $stations, $settings);

// Output widget data in HTML
output_station_widget_data($widget_data);
```

### 2. Update Dashboard HTML
Remove old station rendering and replace `floorCanvas` div with:
```html
<div class="floor-canvas" id="floorCanvas"
     data-zone-id="<?= (int) $activeZoneId ?>"
     data-currency-symbol="<?= h($settings['currency_symbol'] ?? '€') ?>">
</div>
```

### 3. Database Schema Updates (Optional)
To persist widget layouts, add to your database:
```sql
ALTER TABLE zones ADD COLUMN widget_layouts JSON DEFAULT NULL;
```

## JavaScript API

### Public Functions

#### `initializeGridStack()`
Initializes the widget system. Called automatically on DOM ready.

#### `toggleEditMode()`
Toggles between viewing and editing modes.

#### `saveGridLayout()`
Saves current layout to server.

#### `updateFloorState(data)`
Updates widget states with live data.

#### `refreshFloorState()`
Fetches latest floor state from `floor_state.php`.

#### Widget Button Functions (Customizable)
- `startSession(stationId)`
- `pauseSession(stationId)`
- `endSession(stationId)`
- `openOrderModal(stationId)`
- `openBillModal(stationId)`

## Styling Customization

### Color Schemes
Edit `assets/css/station-widgets.css` to customize:
- Status colors (Free: #4CAF50, Busy: #FF6B6B, Paused: #FFA500)
- Button colors and hover states
- Widget sizing and spacing

### Responsive Breakpoints
- Desktop (>1200px): Full-size widgets
- Tablet (768-1200px): Medium widgets
- Mobile (<768px): Stacked layout

## Browser Support
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Android)

## Performance Notes
- GridStack.js is lightweight (~3KB minified)
- Widget CSS is optimized for performance
- Live updates use RAF and throttling
- No jQuery required (pure vanilla JS)

## Troubleshooting

### Widgets not appearing
1. Check browser console for errors
2. Verify asset paths in HTML
3. Ensure `floorCanvas` div exists

### Drag/Drop not working
1. Check if GridStack.js loaded properly
2. Verify CSS files are loaded
3. Check z-index conflicts

### Layout not saving
1. Verify `station_action.php` accepts layout saves
2. Check network tab for POST errors
3. Review server-side validation

## Future Enhancements
- [ ] Widget grouping/zones
- [ ] Custom widget sizes
- [ ] Theme selector
- [ ] Export layout as image
- [ ] Predefined layout templates
- [ ] Widget-specific settings modal