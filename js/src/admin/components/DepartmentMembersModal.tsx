import app from 'flarum/admin/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import type User from 'flarum/common/models/User';
import type SupportDepartment from '../../common/models/SupportDepartment';

const T = (key: string, params?: any) =>
  app.translator.trans('cengizkemec-support.admin.departments.' + key, params) as any;

interface IAttrs extends IInternalModalAttrs {
  department: SupportDepartment;
  /** Called after the member list was saved, so the list can refresh. */
  onsave?: () => void;
}

/**
 * Pick the people that belong to a department.
 *
 * The selection is kept as ids; the user records are only loaded so the chips
 * can show names and avatars. Saving writes the id list back to the
 * department.
 */
export default class DepartmentMembersModal extends Modal<IAttrs> {
  /** Selected member ids, as strings for easy comparison with model ids. */
  memberIds: string[] = [];
  /** Loaded user records for the selected ids, keyed by id. */
  members: Record<string, User> = {};
  loadingMembers = true;

  query = '';
  results: User[] = [];
  searching = false;
  searchError = false;
  protected searchTimer: ReturnType<typeof setTimeout> | null = null;

  saving = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    this.memberIds = (this.attrs.department.userIds() || []).map((id: any) => String(id));
    this.loadMembers();
  }

  className() {
    return 'DepartmentMembersModal Modal--small';
  }

  title() {
    return T('members_title', { name: this.attrs.department.name() });
  }

  async loadMembers() {
    this.loadingMembers = true;
    m.redraw();

    try {
      const users = await Promise.all(
        this.memberIds.map((id) => app.store.find('users', id).catch(() => null))
      );

      users.forEach((user: any) => {
        if (user) this.members[String(user.id())] = user;
      });

      // Ids that do not resolve to a user anymore (deleted accounts) are
      // dropped, so saving cleans the department up.
      this.memberIds = this.memberIds.filter((id) => !!this.members[id]);
    } finally {
      this.loadingMembers = false;
      m.redraw();
    }
  }

  onSearchInput(value: string) {
    this.query = value;

    if (this.searchTimer) clearTimeout(this.searchTimer);

    const query = value.trim();

    if (!query) {
      this.results = [];
      this.searching = false;
      m.redraw();
      return;
    }

    this.searching = true;
    m.redraw();

    this.searchTimer = setTimeout(() => this.search(query), 300);
  }

  async search(query: string) {
    this.searchError = false;

    try {
      const results = await app.store.find<User[]>('users', {
        filter: { q: query },
        page: { limit: 10 },
      });

      // Ignore a stale response: the user kept typing since this request went
      // out.
      if (this.query.trim() !== query) return;

      this.results = (results as User[]).filter(Boolean);
    } catch (e) {
      this.searchError = true;
      this.results = [];
    } finally {
      this.searching = false;
      m.redraw();
    }
  }

  isMember(user: User): boolean {
    return this.memberIds.indexOf(String(user.id())) >= 0;
  }

  addMember(user: User) {
    const id = String(user.id());
    if (this.memberIds.indexOf(id) >= 0) return;

    this.members[id] = user;
    this.memberIds = this.memberIds.concat([id]);
    m.redraw();
  }

  removeMember(id: string) {
    this.memberIds = this.memberIds.filter((memberId) => memberId !== id);
    m.redraw();
  }

  memberChips() {
    if (this.loadingMembers) {
      return (
        <div className="DepartmentMembers-loading">
          <LoadingIndicator />
        </div>
      );
    }

    if (!this.memberIds.length) {
      return <p className="helpText">{T('members_empty')}</p>;
    }

    return (
      <ul className="DepartmentMembers-chips">
        {this.memberIds.map((id) => {
          const user = this.members[id];

          return (
            <li className="DepartmentMembers-chip" key={id}>
              {user && <Avatar user={user} />}
              <span className="DepartmentMembers-chipName">{user ? user.displayName() : '#' + id}</span>
              <button
                type="button"
                className="DepartmentMembers-chipRemove"
                title={T('member_remove')}
                onclick={() => this.removeMember(id)}
              >
                <i className="icon fas fa-times" />
              </button>
            </li>
          );
        })}
      </ul>
    );
  }

  resultsList() {
    if (!this.query.trim()) return null;

    if (this.searching) {
      return (
        <div className="DepartmentMembers-loading">
          <LoadingIndicator />
        </div>
      );
    }

    if (this.searchError) {
      return (
        <div className="Alert Alert--error">
          <span className="Alert-body">{T('search_error')}</span>
        </div>
      );
    }

    if (!this.results.length) {
      return <p className="helpText">{T('members_search_empty')}</p>;
    }

    return (
      <ul className="DepartmentMembers-results">
        {this.results.map((user) => {
          const member = this.isMember(user);

          return (
            <li className="DepartmentMembers-result" key={String(user.id())}>
              <Avatar user={user} />
              <span className="DepartmentMembers-resultName">{user.displayName()}</span>
              <Button
                className={'Button Button--small ' + (member ? '' : 'Button--primary')}
                icon={member ? 'fas fa-check' : 'fas fa-plus'}
                disabled={member}
                onclick={() => this.addMember(user)}
              >
                {member ? T('member_added') : T('member_add')}
              </Button>
            </li>
          );
        })}
      </ul>
    );
  }

  content() {
    return (
      <div className="Modal-body">
        <p className="helpText">{T('members_help')}</p>

        <div className="Form-group">
          <input
            type="text"
            className="FormControl"
            value={this.query}
            placeholder={T('members_search_placeholder')}
            oninput={(event: Event) => this.onSearchInput((event.target as HTMLInputElement).value)}
          />
        </div>

        {this.resultsList()}

        <div className="Form-group">
          <label>{T('members_label')}</label>
          {this.memberChips()}
        </div>

        <div className="Form-group Form-controls">
          <Button
            className="Button Button--primary"
            icon="fas fa-save"
            loading={this.saving}
            disabled={this.saving || this.loadingMembers}
            onclick={() => this.save()}
          >
            {T('members_save')}
          </Button>

          <Button className="Button" disabled={this.saving} onclick={() => this.hide()}>
            {T('members_cancel')}
          </Button>
        </div>
      </div>
    );
  }

  async save() {
    this.saving = true;
    m.redraw();

    try {
      await this.attrs.department.save({
        // Only send resolvable members, so deleted accounts disappear.
        userIds: this.memberIds.filter((id) => !!this.members[id]).map((id) => Number(id)),
      });

      app.alerts.show({ type: 'success' }, T('members_saved'));

      if (this.attrs.onsave) this.attrs.onsave();

      this.hide();
    } catch (e: any) {
      const detail =
        e && e.response && Array.isArray(e.response.errors) && e.response.errors[0] && e.response.errors[0].detail;

      app.alerts.show({ type: 'error' }, detail || T('members_save_error'));
    } finally {
      this.saving = false;
      m.redraw();
    }
  }
}
