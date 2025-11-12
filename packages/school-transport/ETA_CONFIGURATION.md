# ETA Calculation and Real-Time Tracking Configuration

This file describes the configuration options for the real-time ETA calculation system.

## Environment Variables

Add these to your `.env` file to configure the ETA system:

### Google Maps API Configuration
```env
GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
```

### Mapbox Configuration (Alternative)
```env
MAPBOX_ACCESS_TOKEN=your_mapbox_access_token_here
```

### ETA Monitoring Settings
```env
# Enable/disable ETA monitoring globally
ETA_MONITORING_ENABLED=true

# Default ETA calculation provider (google|mapbox)
ETA_DEFAULT_PROVIDER=google

# ETA update interval in seconds (default: 30)
ETA_UPDATE_INTERVAL=30

# Proximity threshold in kilometers (default: 0.5)
ETA_PROXIMITY_THRESHOLD=0.5

# ETA notification threshold in minutes (default: 10)
ETA_NOTIFICATION_THRESHOLD=10

# Background job queue for ETA monitoring
ETA_MONITORING_QUEUE=default
```

### School Transport Settings

These settings can be configured per company in the settings system or as defaults:

```env
# Enable SMS notifications for ETAs
SCHOOL_TRANSPORT_SMS_NOTIFICATIONS=true

# Enable email notifications for ETAs
SCHOOL_TRANSPORT_EMAIL_NOTIFICATIONS=true

# Parent portal URL for tracking links
SCHOOL_TRANSPORT_PORTAL_URL=https://your-domain.com/parent-portal
```

## Company-Level Settings

The following settings can be configured per company through the Settings API:

### ETA Monitoring
- `school_transport.eta_monitoring_enabled` (boolean, default: true)
- `school_transport.eta_provider` (string, default: 'google')
- `school_transport.eta_notification_threshold` (integer, default: 10 minutes)
- `school_transport.proximity_threshold_km` (float, default: 0.5)

### Notifications
- `school_transport.enable_sms_notifications` (boolean, default: true)
- `school_transport.enable_email_notifications` (boolean, default: true)
- `school_transport.parent_portal_url` (string, optional)

### Advanced Settings
- `school_transport.eta_update_interval` (integer, default: 30 seconds)
- `school_transport.eta_cache_duration` (integer, default: 300 seconds)
- `school_transport.max_eta_calculation_retries` (integer, default: 3)

## API Endpoints

### Calculate ETA
```
POST /school-transport/tracking/calculate-eta
```
Body:
```json
{
    "bus_id": "uuid",
    "destination_lat": 40.7128,
    "destination_lng": -74.0060,
    "provider": "google" // optional
}
```

### Get Route ETAs
```
GET /school-transport/tracking/routes/{tripId}/etas?provider=google
```

### Get Stop ETA
```
GET /school-transport/tracking/routes/{routeId}/stops/{stopId}/eta?provider=google
```

### Check Proximity
```
POST /school-transport/tracking/check-proximity
```
Body:
```json
{
    "bus_id": "uuid",
    "stop_lat": 40.7128,
    "stop_lng": -74.0060,
    "threshold_km": 0.5 // optional
}
```

## Console Commands

### Start ETA Monitoring Scheduler
```bash
php artisan school-transport:schedule-eta-monitoring --interval=30 --queue=default
```

### Run One-Time ETA Monitoring
```bash
php artisan school-transport:monitor-eta --sync
```

### Run for Specific Trip
```bash
php artisan school-transport:monitor-eta --trip=trip-uuid-here --sync
```

## Mobile App Integration

### React Native Hook Usage

```typescript
import useETA from '../hooks/use-eta';

const MyComponent = () => {
    const {
        etas,
        loading,
        startMonitoring,
        stopMonitoring,
        formatETA,
        getArrivalTime
    } = useETA();

    useEffect(() => {
        startMonitoring('trip-uuid-here');
        
        return () => {
            stopMonitoring('trip-uuid-here');
        };
    }, []);

    // Rest of component...
};
```

### ETA Display Component

```tsx
import ETADisplay from '../components/ETADisplay';

<ETADisplay 
    tripId="trip-uuid"
    autoRefresh={true}
    refreshInterval={30}
    onETAUpdate={(etas) => console.log('ETAs updated:', etas)}
/>
```

## Queue Configuration

Make sure to configure your queue worker to process ETA monitoring jobs:

```bash
# Start queue worker
php artisan queue:work --queue=default

# Or with supervisor for production
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --queue=default --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
```

## Troubleshooting

### Common Issues

1. **"Google Maps API key not configured"**
   - Add `GOOGLE_MAPS_API_KEY` to your `.env` file
   - Ensure the API key has the following services enabled:
     - Distance Matrix API
     - Geocoding API
     - Maps JavaScript API

2. **"No tracking data available"**
   - Ensure buses are sending location updates
   - Check the `school_transport_tracking_logs` table for recent entries
   - Verify GPS tracking is enabled in the mobile app

3. **ETAs not updating**
   - Check if the queue worker is running
   - Verify ETA monitoring is enabled in settings
   - Check application logs for errors

4. **High API usage costs**
   - Reduce `ETA_UPDATE_INTERVAL` to update less frequently
   - Implement caching (already included in the service)
   - Consider switching to Mapbox for potentially lower costs

### Debugging

Enable debug logging for ETA calculations:

```php
// In your .env file
LOG_LEVEL=debug

// Check logs
tail -f storage/logs/laravel.log | grep "ETA"
```

### Performance Optimization

1. **Use appropriate update intervals**
   - 30-60 seconds for active monitoring
   - 2-5 minutes for low-priority routes

2. **Enable caching**
   - ETAs are cached for 5 minutes by default
   - Configure Redis for better cache performance

3. **Use database indexes**
   - Run the provided migration to add ETA-related indexes
   - Monitor query performance on the tracking_logs table

## Production Deployment

1. **Environment Setup**
   ```bash
   # Set production environment variables
   cp .env.example .env.production
   # Edit .env.production with your API keys and settings
   ```

2. **Database Migration**
   ```bash
   php artisan migrate --force
   ```

3. **Queue Configuration**
   ```bash
   # Configure supervisor for queue workers
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-worker:*
   ```

4. **Cron Job Setup**
   ```bash
   # Add to crontab for Laravel scheduler
   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
   ```

5. **Monitoring**
   - Set up log monitoring for ETA calculation errors
   - Monitor API usage and costs
   - Track notification delivery rates
   - Monitor queue job success/failure rates