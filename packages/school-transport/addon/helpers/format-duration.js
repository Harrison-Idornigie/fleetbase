import { helper } from '@ember/component/helper';

export default helper(function formatDuration([minutes]) {
  if (minutes === null || minutes === undefined) return '';
  
  if (minutes < 1) {
    return 'Less than a minute';
  } else if (minutes === 1) {
    return '1 minute';
  } else if (minutes < 60) {
    return `${Math.round(minutes)} minutes`;
  } else {
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    
    if (mins === 0) {
      return hours === 1 ? '1 hour' : `${hours} hours`;
    }
    
    return `${hours}h ${mins}m`;
  }
});
