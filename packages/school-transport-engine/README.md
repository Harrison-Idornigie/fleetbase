# School Transport Engine (Fleetbase)

This package adds school transportation features to Fleetbase including:

- Student management and assignments
- Route planning and optimization
- Real-time vehicle tracking and ETA notifications
- Parent portal with live tracking, alerts, and notification settings
- Communication composer for multi-channel messaging
- Dashboard widgets and charts for analytics

## Components

- `StudentForm` — complex student profile form
- `RouteForm` — route creation with stops and assignments
- `AssignmentForm` — assign students to routes with pickup/dropoff
- `BusTracker` — live bus map view and vehicle list (uses `LiveMap`)
- `LiveMap` — Leaflet-based map wrapper for markers, polylines and map controls
- `RouteMap` — renders a specific route as polyline with stop markers
- `ParentDashboard` — parent's view with student list, bus-tracker, alerts, and preferences
- `NotificationPreferences` — set preferred channels and event types
- `NotificationCenter` — in-app notification list with read/unread actions
- `ChartWidget` — generic Chart.js wrapper
- `DashboardCard` — single-value KPI card

## Services

- `school-transport-api` — REST API client for all entities and features
- `real-time-tracking` — WebSocket-based live tracking service
- `validation` — Form and entity validators

## Usage

- Embed the `ParentDashboard` component wherever you want parents to access the portal:
  ```hbs
  <ParentDashboard />
  ```

- Use `LiveMap` for any live mapping needs, `RouteMap` for route visualization, and `BusTracker` for live vehicle tracking.

- The `real-time-tracking` service emits the following events:
  - `location-update` — when a vehicle updates location
  - `progress-update` — when route progress changes
  - `eta-update` — ETA updates for stops
  - `alert` — system/route alerts

## Map dependency

Leaflet is used for map rendering. Make sure Leaflet is included in your app (e.g., via npm/yarn) or fallbacks will be used by the live map component.

## APIs & Back-end

Ensure your Fleetbase backend exposes the following endpoints (or adapt the service methods accordingly):
- `/parents/me/dashboard` — returns parent, students, assignments, and metrics
- `/vehicles` — list of vehicles
- `/notifications` — in-app notification CRUD
- `/students/:id/etas` — ETAs for student stops
- Tracking & ETA endpoints for real-time features

## Developer Notes
- Components follow Ember.js modern patterns using Glimmer components and `@tracked` properties.
- LiveMap uses DOM-based CustomEvents to allow map interactions across components without bridging instances.
- The `school-transport-api` service sets default `parentId` to `me` if not provided for convenience.

## Testing
- Unit tests and integration tests are encouraged for each component. Use the pattern existing in the repository to create a consistent testing approach.

## Next Steps
- Add e2e tests for real-time flows (arrival alerts, notifications)
- Polish UI visuals and responsive behavior
- Add accessibility improvements (ARIA tags & keyboard navigation)

---

If you'd like, I can now:
- Add unit tests for key components
- Add feature tests for parent portal flows (unit/integration)
- Wire up Mapbox support as an alternative to Leaflet
- Add more advanced analytics charts and filters
