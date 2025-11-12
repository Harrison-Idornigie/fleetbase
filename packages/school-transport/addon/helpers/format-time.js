import { helper } from '@ember/component/helper';

export default helper(function formatTime([time]) {
  if (!time) return '';
  
  // If time is a Date object
  if (time instanceof Date) {
    return time.toLocaleTimeString('en-US', { 
      hour: 'numeric', 
      minute: '2-digit',
      hour12: true 
    });
  }
  
  // If time is a string in HH:MM or HH:MM:SS format
  if (typeof time === 'string') {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours, 10);
    const minute = parseInt(minutes, 10);
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    
    return `${displayHour}:${minute.toString().padStart(2, '0')} ${period}`;
  }
  
  return time;
});
