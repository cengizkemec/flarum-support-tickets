import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class Ticket extends Model {
  subject = Model.attribute<string>('subject');
  message = Model.attribute<string>('message');
  status = Model.attribute<string>('status');
  priority = Model.attribute<string>('priority');
  attachment = Model.attribute<string | null>('attachment');
  /** Slug derived from the subject, used to build /support/{id}/{slug} links. */
  slug = Model.attribute<string>('slug');
  /** Raw answers to the admin-defined form fields, keyed by field name. */
  customFields = Model.attribute<Record<string, unknown>>('customFields');
  /** Ready to display [{ label, value, type }] pairs. */
  customFieldValues = Model.attribute<{ label: string; value: string; type: string }[]>('customFieldValues');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  updatedAt = Model.attribute('updatedAt', Model.transformDate);
  closedAt = Model.attribute('closedAt', Model.transformDate);

  user = Model.hasOne<User>('user');
  category = Model.hasOne('category');
  assignee = Model.hasOne<User>('assignee');
  replies = Model.hasMany('replies');
}
