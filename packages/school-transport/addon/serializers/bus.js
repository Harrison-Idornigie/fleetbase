import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class BusSerializer extends JSONAPISerializer {
  attrs = {
    school: { serialize: false }
  };

  normalize(modelClass, resourceHash) {
    // Convert snake_case to camelCase for meta fields
    if (resourceHash.attributes && resourceHash.attributes.meta) {
      resourceHash.attributes.meta = this.normalizeMetaData(resourceHash.attributes.meta);
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
