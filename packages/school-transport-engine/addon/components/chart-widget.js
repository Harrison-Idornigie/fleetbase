import Component from '@glimmer/component';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

// Attempt to lazy load Chart.js if available
let Chart = null;

export default class ChartWidgetComponent extends Component {
  @tracked canvasId = `chart-${Math.random().toString(36).substr(2, 9)}`;
  chartInstance = null;

  constructor() {
    super(...arguments);
    this.loadChartLib();
  }

  async loadChartLib() {
    try {
      if (!Chart) {
        // dynamic import of chart.js (will work if dependency exists)
        const mod = await import('chart.js/auto');
        Chart = mod.default || mod;
      }
      this.renderChart();
    } catch (err) {
      // If Chart.js not installed, we just render basic placeholder
      console.warn('Chart.js not available:', err);
    }
  }

  @action
  renderChart() {
    if (!Chart) return;

    const ctx = document.getElementById(this.canvasId);
    if (!ctx) return;

    const data = this.args.data || {};
    const type = this.args.type || 'bar';

    if (this.chartInstance) {
      this.chartInstance.destroy();
    }

    this.chartInstance = new Chart(ctx, {
      type,
      data,
      options: this.args.options || {}
    });
  }

  willDestroy() {
    super.willDestroy(...arguments);
    if (this.chartInstance) {
      this.chartInstance.destroy();
    }
  }
}
