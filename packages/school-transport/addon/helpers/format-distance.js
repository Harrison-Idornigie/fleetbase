import { helper } from '@ember/component/helper';

export default helper(function formatDistance([distance, unit = 'km']) {
  if (distance === null || distance === undefined) return '';
  
  if (unit === 'km') {
    if (distance < 1) {
      return `${Math.round(distance * 1000)} m`;
    }
    return `${distance.toFixed(2)} km`;
  } else if (unit === 'mi') {
    if (distance < 0.5) {
      return `${Math.round(distance * 5280)} ft`;
    }
    return `${distance.toFixed(2)} mi`;
  }
  
  return `${distance.toFixed(2)} ${unit}`;
});
