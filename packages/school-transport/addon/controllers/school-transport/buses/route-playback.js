import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class SchoolTransportBusesRoutePlaybackController extends Controller {
  @service notifications;
  @service store;

  @tracked isLoading = false;
  @tracked selectedDate = '';
  @tracked startDate = '';
  @tracked endDate = '';
  @tracked useDateRange = false;
  @tracked selectedStudent = null;
  @tracked selectedTrip = null;
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
      { value: 5, label: '5x' },
      { value: 10, label: '10x' },
      { value: 20, label: '20x' },
      { value: 30, label: '30x' },
      { value: 50, label: '50x' },
      { value: 100, label: '100x' },
      { value: 200, label: '200x' }
    ];
  }

  @action
  updateSelectedDate(event) {
    this.selectedDate = event.target.value;
  }

  @action
  updateStartDate(event) {
    this.startDate = event.target.value;
  }

  @action
  updateEndDate(event) {
    this.endDate = event.target.value;
  }

  @action
  toggleDateRange() {
    this.useDateRange = !this.useDateRange;
    if (!this.useDateRange) {
      this.startDate = '';
      this.endDate = '';
    }
  }

  @action
  selectStudent(student) {
    this.selectedStudent = student;
  }

  @action
  selectTrip(trip) {
    this.selectedTrip = trip;
  }

  @action
  clearFilters() {
    this.selectedStudent = null;
    this.selectedTrip = null;
  }

  @action
  async loadRouteData() {
    if (!this.useDateRange && !this.selectedDate) {
      this.notifications.error('Please select a date');
      return;
    }

    if (this.useDateRange && (!this.startDate || !this.endDate)) {
      this.notifications.error('Please select both start and end dates');
      return;
    }

    try {
      this.isLoading = true;
      this.routeData = null;
      this.currentPosition = 0;
      this.isPlaying = false;

      // Call the route playback API endpoint with filters
      let url = `/api/school-transport/buses/${this.bus.id}/route-playback`;
      
      if (this.useDateRange) {
        url += `?start_date=${this.startDate}&end_date=${this.endDate}`;
      } else {
        url += `?date=${this.selectedDate}`;
      }
      
      if (this.selectedStudent) {
        url += `&student_uuid=${this.selectedStudent.id}`;
      }
      
      if (this.selectedTrip) {
        url += `&trip_uuid=${this.selectedTrip.id}`;
      }
      
      const response = await fetch(url);
      
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
      this.pausePlaybackInterval();
    }
  }

  @action
  resetPlayback() {
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

  @action
  stepForward() {
    if (!this.hasRouteData) {
      this.notifications.error('No route data available');
      return;
    }

    if (this.isPlaying) {
      this.stopPlayback();
      this.isPlaying = false;
    }

    if (this.currentPosition < this.routeData.positions.length - 1) {
      this.currentPosition++;
    } else {
      this.notifications.info('Already at the end of route');
    }
  }

  @action
  stepBackward() {
    if (!this.hasRouteData) {
      this.notifications.error('No route data available');
      return;
    }

    if (this.isPlaying) {
      this.stopPlayback();
      this.isPlaying = false;
    }

    if (this.currentPosition > 0) {
      this.currentPosition--;
    } else {
      this.notifications.info('Already at the beginning of route');
    }
  }

  @action
  stopPlayback() {
    this.isPlaying = false;
    this.currentPosition = 0;
    if (this.playbackInterval) {
      clearInterval(this.playbackInterval);
      this.playbackInterval = null;
    }
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

  pausePlaybackInterval() {
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