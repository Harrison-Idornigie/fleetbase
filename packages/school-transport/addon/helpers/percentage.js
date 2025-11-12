import { helper } from '@ember/component/helper';

export default helper(function percentage([value, total, decimals = 0]) {
  if (value === null || value === undefined || total === null || total === undefined || total === 0) {
    return '0%';
  }
  
  const percent = (value / total) * 100;
  return `${percent.toFixed(decimals)}%`;
});
