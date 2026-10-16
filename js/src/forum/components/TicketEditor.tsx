import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import TextEditor from 'flarum/common/components/TextEditor';

/**
 * The composer's editor without its built-in submit control.
 *
 * Both writing areas keep their own submit button (with its own loading and
 * disabled handling), so the editor's one is removed.
 */
class TicketTextEditor extends TextEditor {
  controlItems() {
    const items = super.controlItems();
    items.remove('submit');
    return items;
  }
}

interface IAttrs extends ComponentAttrs {
  value?: string;
  placeholder?: string;
  disabled?: boolean;
  onchange: (value: string) => void;
  onsubmit: () => void;
  /** Paste handler attached to the editor's textarea (image paste-to-attach). */
  onpaste?: (event: Event) => void;
  /** Called with the editor driver once it has been built. */
  onready?: (editor: any) => void;
}

/**
 * A rich text editor for the ticket forms.
 *
 * This renders the exact component the forum composer uses, which is the point:
 * every formatting extension (markdown, BBCode, emoji, ...) extends that
 * component, so the ticket form automatically gets the same toolbar, keyboard
 * shortcuts and emoji autocomplete as writing a post — on any forum, with
 * whatever formatting extensions are enabled.
 */
export default class TicketEditor extends Component<IAttrs> {
  /**
   * The composer object the editor expects. The core TextEditor writes the
   * built editor driver into `editor`; the toolbar extensions read it from
   * there when a button is clicked.
   */
  protected composer: any = {
    // A harmless stub so lifecycle calls before the driver is built (for
    // example a disabled toggle while loading) can never throw.
    editor: {
      disabled: () => {},
      focus: () => {},
      destroy: () => {},
    },
  };

  view() {
    return (
      <div className="TicketEditor">
        <TicketTextEditor
          composer={this.composer}
          value={this.attrs.value || ''}
          placeholder={this.attrs.placeholder || ''}
          disabled={!!this.attrs.disabled}
          onchange={(value: string) => this.attrs.onchange(value)}
          onsubmit={() => this.attrs.onsubmit()}
          onTextEditorBuilt={() => {
            const editor = this.composer.editor;
            const el = editor && editor.el;

            // The driver owns the textarea, so paste-to-attach has to be
            // wired up once it exists. Guarded so a rebuild never doubles it.
            if (el && this.attrs.onpaste && !el.__ticketPasteAttached) {
              el.__ticketPasteAttached = true;
              el.addEventListener('paste', this.attrs.onpaste);
            }

            if (this.attrs.onready) this.attrs.onready(editor);
          }}
        />
      </div>
    );
  }
}
