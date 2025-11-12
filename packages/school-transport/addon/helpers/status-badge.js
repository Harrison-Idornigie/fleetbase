import { helper } from '@ember/component/helper';
import { htmlSafe } from '@ember/template';

export default helper(function statusBadge([status]) {
  if (!status) return '';
  
  const statusMap = {
    active: { label: 'Active', class: 'bg-green-100 text-green-800' },
    inactive: { label: 'Inactive', class: 'bg-gray-100 text-gray-800' },
    pending: { label: 'Pending', class: 'bg-yellow-100 text-yellow-800' },
    completed: { label: 'Completed', class: 'bg-blue-100 text-blue-800' },
    cancelled: { label: 'Cancelled', class: 'bg-red-100 text-red-800' },
    scheduled: { label: 'Scheduled', class: 'bg-purple-100 text-purple-800' },
    'in-progress': { label: 'In Progress', class: 'bg-indigo-100 text-indigo-800' },
    delayed: { label: 'Delayed', class: 'bg-orange-100 text-orange-800' }
  };
  
  const config = statusMap[status.toLowerCase()] || { 
    label: status, 
    class: 'bg-gray-100 text-gray-800' 
  };
  
  return htmlSafe(
    `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.class}">
      ${config.label}
    </span>`
  );
});
