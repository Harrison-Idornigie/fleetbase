import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class TrackingLogSerializer extends JSONAPISerializer {
  attrs = {
    trip: { serialize: false },
    bus: { serialize: false },
    driver: { serialize: false }
  };

  normalize(modelClass, resourceHash) {
    if (resourceHash.attributes && resourceHash.attributes.meta) {
      resourceHash.attributes.meta = this.normalizeMetaData(resourceHash.attributes.meta);
    }
    
    // Parse location and telemetry data if strings
    if (resourceHash.attributes) {
      ['location', 'telemetry'].forEach(field => {
        if (typeof resourceHash.attributes[field] === 'string') {
          try {
            resourceHash.attributes[field] = JSON.parse(resourceHash.attributes[field]);
          } catch (e) {
            // Keep as is if parsing fails
          }
        }
      });
    }
    
    return super.normalize(...arguments);
  }

  normalizeMetaData(meta) {
    if (!meta || typeof meta !== 'object') return meta;
    const normalized = {};
    Object.keys(meta).forEach(key => {
      const camelKey = key.replace(/_([a-z])/g, (g) => g[1].toUpperCase());
      normalized[camelKey] = meta[key];
    });
    return normalized;
  }
}
