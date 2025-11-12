import JSONAPISerializer from '@ember-data/serializer/json-api';

export default class CommunicationSerializer extends JSONAPISerializer {
  /**
   * Serialize communication attributes
   */
  attrs = {
    publicId: 'public_id',
    messageType: 'message_type',
    recipientType: 'recipient_type',
    deliveryStatus: 'delivery_status',
    scheduledTime: 'scheduled_time',
    sentAt: 'sent_at',
    createdBy: 'created_by',
    totalRecipients: 'total_recipients',
    deliveredCount: 'delivered_count',
    failedCount: 'failed_count'
  };

  normalize(modelClass, resourceHash, prop) {
    if (resourceHash.attributes) {
      Object.keys(this.attrs).forEach(camelKey => {
        const snakeKey = this.attrs[camelKey];
        if (resourceHash.attributes[snakeKey] !== undefined) {
          resourceHash.attributes[camelKey] = resourceHash.attributes[snakeKey];
          delete resourceHash.attributes[snakeKey];
        }
      });
    }

    return super.normalize(modelClass, resourceHash, prop);
  }

  serialize(snapshot, options) {
    const json = super.serialize(snapshot, options);

    if (json.data && json.data.attributes) {
      Object.keys(this.attrs).forEach(camelKey => {
        const snakeKey = this.attrs[camelKey];
        if (json.data.attributes[camelKey] !== undefined) {
          json.data.attributes[snakeKey] = json.data.attributes[camelKey];
          delete json.data.attributes[camelKey];
        }
      });
    }

    return json;
  }
}
