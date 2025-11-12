import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class DriverSerializer extends JSONAPISerializer {
  attrs = {
    user: { serialize: false }
  };

  normalize(modelClass, resourceHash) {
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
