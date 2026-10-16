import Model from 'flarum/common/Model';
import TicketCategory from './TicketCategory';

/**
 * An admin-defined extra field shown on the "new ticket" form.
 */
export default class TicketFormField extends Model {
  label = Model.attribute<string>('label');
  name = Model.attribute<string>('name');
  type = Model.attribute<string>('type');
  options = Model.attribute<string[]>('options');
  isRequired = Model.attribute<boolean>('isRequired');
  isActive = Model.attribute<boolean>('isActive');
  position = Model.attribute<number>('position');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  updatedAt = Model.attribute('updatedAt', Model.transformDate);

  category = Model.hasOne<TicketCategory>('category');
}
