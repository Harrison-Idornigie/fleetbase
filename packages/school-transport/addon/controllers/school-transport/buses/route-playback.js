import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class SchoolTransportBusesRoutePlaybackController extends Controller {
  @service notifications;
  @service store;

  @tracked isLoading = false;
  @tracked selectedDate = '';
  @tracked routeData = null;
  @tracked playbackSpeed = 1;
  @tracked isPlaying = false;
  @tracked currentPosition = 0;
  @tracked showStudentDetails = true;

  get bus() {
    return this.model.bus;
  }

  get hasRouteData() {
    return this.routeData && this.routeData.positions && this.routeData.positions.length > 0;
  }

  get playbackSpeeds() {
    return [
      { value: 0.5, label: '0.5x' },
      { value: 1, label: '1x' },
      { value: 2, label: '2x' },
      { value: 4, label: '4x' },
      { value: 8, label: '8x' }
    ];
  }

  @action
  updateSelectedDate(event) {
    this.selectedDate = event.target.value;
  }

  @action
  async loadRouteData() {
    if (!this.selectedDate) {
      this.notifications.error('Please select a date');
      return;
    }

    try {
      this.isLoading = true;
      this.routeData = null;
      this.currentPosition = 0;
      this.isPlaying = false;

      // Call the route playback API endpoint
      const response = await fetch(`/api/school-transport/buses/${this.bus.id}/route-playback?date=${this.selectedDate}`);
      
      if (!response.ok) {
        throw new Error('Failed to fetch route data');
      }

      const data = await response.json();
      this.routeData = data;

      if (!this.hasRouteData) {
        this.notifications.info('No route data found for the selected date');
      } else {
        this.notifications.success(`Loaded ${data.positions.length} position points with ${data.student_events.length} student events`);
      }
    } catch (error) {
      console.error('Error loading route data:', error);
      this.notifications.error('Failed to load route data');
    } finally {
      this.isLoading = false;
    }
  }

  @action
  updatePlaybackSpeed(event) {
    this.playbackSpeed = parseFloat(event.target.value);
  }

  @action
  togglePlayback() {
    if (!this.hasRouteData) {
      this.notifications.error('No route data to play');
      return;
    }

    this.isPlaying = !this.isPlaying;

    if (this.isPlaying) {
      this.startPlayback();
    } else {
      this.stopPlayback();
    }
  }

  @action
  resetPlayback() {
    this.isPlaying = false;
    this.currentPosition = 0;
    this.stopPlayback();
  }

  @action
  seekToPosition(position) {
    this.currentPosition = Math.max(0, Math.min(position, this.routeData.positions.length - 1));
  }

  @action
  toggleStudentDetails() {
    this.showStudentDetails = !this.showStudentDetails;
  }

  startPlayback() {
    if (this.playbackInterval) {
      clearInterval(this.playbackInterval);
    }

    const interval = 1000 / this.playbackSpeed; // Base interval of 1 second

    this.playbackInterval = setInterval(() => {
      if (this.currentPosition >= this.routeData.positions.length - 1) {
        this.isPlaying = false;
        clearInterval(this.playbackInterval);
        this.notifications.info('Route playback completed');
        return;
      }

      this.currentPosition++;
    }, interval);
  }

  stopPlayback() {
    if (this.playbackInterval) {
      clearInterval(this.playbackInterval);
      this.playbackInterval = null;
    }
  }

  get currentPositionData() {
    if (!this.hasRouteData || this.currentPosition >= this.routeData.positions.length) {
      return null;
    }
    return this.routeData.positions[this.currentPosition];
  }

  get currentStudentEvents() {
    if (!this.hasRouteData || !this.currentPositionData) {
      return [];
    }

    // Find student events that occurred within a few minutes of current position
    const currentTime = new Date(this.currentPositionData.created_at);
    const tolerance = 5 * 60 * 1000; // 5 minutes in milliseconds

    return this.routeData.student_events.filter(event => {
      const eventTime = new Date(event.created_at);
      return Math.abs(eventTime.getTime() - currentTime.getTime()) <= tolerance;
    });
  }

  get progressPercentage() {
    if (!this.hasRouteData) {
      return 0;
    }
    return (this.currentPosition / (this.routeData.positions.length - 1)) * 100;
  }
}