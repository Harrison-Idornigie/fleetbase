import { helper } from '@ember/component/helper';

export default helper(function formatDate([date, format = 'short']) {
  if (!date) return '';
  
  const dateObj = date instanceof Date ? date : new Date(date);
  
  const formats = {
    short: { month: 'short', day: 'numeric', year: 'numeric' },
    long: { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' },
    medium: { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' },
    time: { hour: 'numeric', minute: '2-digit', hour12: true },
    weekday: { weekday: 'short' }
  };
  
  return dateObj.toLocaleDateString('en-US', formats[format] || formats.short);
});
