import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class TripSerializer extends JSONAPISerializer {
  attrs = {
    route: { serialize: false },
    bus: { serialize: false },
    driver: { serialize: false }
  };

  normalize(modelClass, resourceHash) {
    if (resourceHash.attributes && resourceHash.attributes.meta) {
      resourceHash.attributes.meta = this.normalizeMetaData(resourceHash.attributes.meta);
    }
    
    // Parse location data if it's a string
    if (resourceHash.attributes) {
      ['start_location', 'end_location', 'current_location'].forEach(field => {
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
