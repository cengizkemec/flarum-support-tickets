import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class TicketReply extends Model {
  message = Model.attribute<string>('message');
  /** The message rendered as HTML by the server-side formatter. */
  messageHtml = Model.attribute<string>('messageHtml');
  attachment = Model.attribute<string | null>('attachment');
  isStaff = Model.attribute<boolean>('isStaff');
  createdAt = Model.attribute('createdAt', Model.transformDate);

  user = Model.hasOne<User>('user');
  ticket = Model.hasOne('ticket');
}
