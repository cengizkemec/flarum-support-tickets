import Model from 'flarum/common/Model';
import SupportDepartment from './SupportDepartment';

export default class TicketCategory extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  /** Id of the department that answers tickets in this category. */
  departmentId = Model.attribute<number | null>('departmentId');
  position = Model.attribute<number>('position');
  isActive = Model.attribute<boolean>('isActive');

  department = Model.hasOne<SupportDepartment>('department');
}
