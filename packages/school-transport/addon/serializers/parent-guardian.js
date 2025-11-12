import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class ParentGuardianSerializer extends JSONAPISerializer {
  attrs = {
    user: { serialize: false }
  };

  normalize(modelClass, resourceHash) {
    if (resourceHash.attributes && resourceHash.attributes.meta) {
      resourceHash.attributes.meta = this.normalizeMetaData(resourceHash.attributes.meta);
    }
    
    // Parse notification preferences if it's a string
    if (resourceHash.attributes && typeof resourceHash.attributes.notification_preferences === 'string') {
      try {
        resourceHash.attributes.notification_preferences = JSON.parse(resourceHash.attributes.notification_preferences);
      } catch (e) {
        // Keep as is if parsing fails
      }
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

  serialize() {
    const json = super.serialize(...arguments);
    
    // Ensure notification_preferences is serialized as JSON
    if (json.data && json.data.attributes && json.data.attributes.notification_preferences) {
      if (typeof json.data.attributes.notification_preferences === 'object') {
        json.data.attributes.notification_preferences = JSON.stringify(json.data.attributes.notification_preferences);
      }
    }
    
    return json;
  }
}
