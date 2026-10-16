import Model from 'flarum/common/Model';

/**
 * A support department: a named group of staff used to route tickets.
 *
 * Categories link to a department; tickets opened in those categories are
 * assigned to and notified about department members first.
 */
export default class SupportDepartment extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  /** Existing user ids, only sent to administrators. */
  userIds = Model.attribute<number[]>('userIds');
  isActive = Model.attribute<boolean>('isActive');
  memberCount = Model.attribute<number>('memberCount');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  updatedAt = Model.attribute('updatedAt', Model.transformDate);
}
