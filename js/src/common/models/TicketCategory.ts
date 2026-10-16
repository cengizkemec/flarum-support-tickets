import Model from 'flarum/common/Model';

export default class TicketCategory extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  position = Model.attribute<number>('position');
  isActive = Model.attribute<boolean>('isActive');
}
