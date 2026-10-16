import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import app from 'flarum/forum/app';
import CreateTicketModal from './CreateTicketModal';
import TicketList from './TicketList';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const OPEN_STATUSES = ['new', 'answered', 'pending'];

export default class SupportPage extends Page {
  loading = true;
  filter = 'all';
  error: string | null = null;
  savingPrefs = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    // The browser tab must say "Support", not the previous page's title.
    app.setTitle(extractText(T('meta_title_text')));

    // Guests are welcome to land here, but they cannot read tickets: show a
    // call to action that invites them to sign in or register instead.
    if (!app.session.user) {
      this.loading = false;
      return;
    }

    app.store
      .find('tickets', { sort: '-createdAt', include: 'category,assignee,user,replies' })
      .then(() => {
        this.loading = false;
        m.redraw();
      })
      .catch((e: any) => {
        this.loading = false;
        this.error = e?.response?.errors?.[0]?.detail || e?.message || T('error_load');
        m.redraw();
      });
  }

  view() {
    const isGuest = !app.session.user;

    return (
      <PageStructure className="SupportPage" hero={() => this.hero(isGuest)}>
        {isGuest ? this.guest() : this.loading ? (
          <div className="SupportLoading">
            <LoadingIndicator />
          </div>
        ) : (
          this.content()
        )}
      </PageStructure>
    );
  }

  hero(isGuest: boolean) {
    return (
      <div className="container">
        <div className="SupportHero">
          <div className="SupportHero-main">
            <h2 className="SupportHero-title">
              <i className="icon fas fa-headset" /> {T('title')}
            </h2>
            <p className="SupportHero-subtitle">{T('subtitle')}</p>
          </div>
          {!isGuest && (
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => app.modal.show(CreateTicketModal)}>
              {T('new_ticket')}
            </Button>
          )}
        </div>
      </div>
    );
  }

  guest() {
    return (
      <div className="SupportGuest">
        <div className="SupportGuest-icon">
          <i className="icon fas fa-lock" />
        </div>
        <h3 className="SupportGuest-title">{T('guest_title')}</h3>
        <p className="SupportGuest-text">{T('guest_text')}</p>
        <div className="SupportGuest-actions">
          <Button
            className="Button Button--primary"
            icon="fas fa-right-to-bracket"
            onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}
          >
            {T('guest_login')}
          </Button>
          <Button className="Button" icon="fas fa-user-plus" onclick={() => app.modal.show(() => import('flarum/forum/components/SignUpModal'))}>
            {T('guest_register')}
          </Button>
        </div>
      </div>
    );
  }

  /**
   * A single switch for all ticket notifications (alert, email and push).
   *
   * The individual per-method checkboxes still live on the forum settings
   * page; this is the quick "stop emailing me about tickets" control right
   * where support tickets are managed. Preference keys are discovered from
   * the user's stored preferences so it keeps working if Flarum or FoF PWA
   * add another notification method.
   */
  notificationToggle() {
    const user = app.session.user;
    if (!user) return null;

    const preferences: Record<string, any> = user.preferences() || {};
    const keys = Object.keys(preferences).filter((key) => /^notify_(newTicket|ticketReply)_/.test(key));

    // No keys means no notification type is registered for the actor's
    // environment; showing a dead switch would only confuse people.
    if (!keys.length) return null;

    const enabled = keys.some((key) => !!preferences[key]);

    return (
      <div className={'SupportNotifToggle' + (this.savingPrefs ? ' is-loading' : '')}>
        <Switch
          state={enabled}
          loading={this.savingPrefs}
          disabled={this.savingPrefs}
          onchange={() => this.toggleNotifications(keys, !enabled)}
        >
          {T('notifications.label')}
        </Switch>
        <div className="SupportNotifToggle-help">
          {enabled ? T('notifications.on') : T('notifications.off')}
        </div>
        <div className="SupportNotifToggle-note">{T('notifications.help')}</div>
      </div>
    );
  }

  async toggleNotifications(keys: string[], enabled: boolean) {
    const user = app.session.user;
    if (!user || this.savingPrefs) return;

    const preferences: Record<string, any> = user.preferences() || {};
    keys.forEach((key) => (preferences[key] = enabled));

    this.savingPrefs = true;
    m.redraw();

    try {
      await user.save({ preferences });
    } catch (e: any) {
      app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || e?.message || T('error_preferences'));
    } finally {
      this.savingPrefs = false;
      m.redraw();
    }
  }

  content() {
    const isStaff = !!app.forum.attribute('canSupportViewAll') || !!app.forum.attribute('canSupportManage');
    const tickets = app.store.all('tickets') as any[];

    const total = tickets.length;
    const open = tickets.filter((t) => OPEN_STATUSES.indexOf(t.status()) >= 0).length;
    const closed = total - open;

    const tabs: [string, string, number][] = [
      ['all', T('tab_all'), total],
      ['open', T('tab_open'), open],
      ['closed', T('tab_closed'), closed],
    ];

    return (
      <div>
        {this.notificationToggle()}

        <div className="SupportStats">
          <div className="SupportStat">
            <div className="SupportStat-value">{total}</div>
            <div className="SupportStat-label">{T('stat_total')}</div>
          </div>
          <div className="SupportStat">
            <div className="SupportStat-value">{open}</div>
            <div className="SupportStat-label">{T('stat_open')}</div>
          </div>
          <div className="SupportStat">
            <div className="SupportStat-value">{closed}</div>
            <div className="SupportStat-label">{T('stat_closed')}</div>
          </div>
        </div>

        <div className="SupportTabs">
          {tabs.map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              className={'SupportTab' + (this.filter === key ? ' active' : '')}
              onclick={() => {
                this.filter = key;
                m.redraw();
              }}
            >
              {label} <span className="SupportTab-count">{count}</span>
            </button>
          ))}
        </div>

        {this.error ? <div className="Alert Alert--error">{this.error}</div> : <TicketList filter={this.filter} isStaff={isStaff} />}
      </div>
    );
  }
}
