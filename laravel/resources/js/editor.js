import { EditorState, Compartment, StateField, StateEffect } from "@codemirror/state";
import { EditorView, lineNumbers, drawSelection, highlightActiveLine, Decoration, WidgetType } from "@codemirror/view";
import { foldGutter } from "@codemirror/language";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";

// Widget to replace tags with invisible content
class InvisibleTagWidget extends WidgetType {
  constructor(tag) {
    super();
    this.tag = tag;
  }

  toDOM() {
    const span = document.createElement("span");
    span.style.display = "none";
    span.textContent = this.tag;
    return span;
  }

  ignoreEvent() {
    return false;
  }
}

// Effect to toggle tag visibility
const toggleTagVisibility = StateEffect.define();

// State field to track whether tags should be hidden
const hideTagsField = StateField.define({
  create() {
    return true; // Start with tags hidden
  },
  update(value, tr) {
    for (let effect of tr.effects) {
      if (effect.is(toggleTagVisibility)) {
        value = effect.value;
      }
    }
    return value;
  },
  provide: f => EditorView.decorations.from(f, hideTags => {
    if (!hideTags) return Decoration.none;

    return view => {
      const widgets = [];
      const doc = view.state.doc;
      const text = doc.toString();

      // Regex to match XML tags: <tag...> or </tag> or <tag/>
      const tagRegex = /<\/?[a-zA-Z][^>]*\/?>/g;
      let match;

      while ((match = tagRegex.exec(text)) !== null) {
        const from = match.index;
        const to = from + match[0].length;

        widgets.push(
          Decoration.replace({
            widget: new InvisibleTagWidget(match[0]),
            inclusive: true,
            block: false
          }).range(from, to)
        );
      }

      return Decoration.set(widgets);
    };
  })
});

window.initEditor = (initialXml, comparisonId, fileType = 'source') => {
  const container = document.getElementById('editor-container');
  const saveBtn = document.getElementById('save-xml');

  // Create compartments for dynamic reconfiguration
  const readOnlyCompartment = new Compartment();
  const editableCompartment = new Compartment();

  const startState = EditorState.create({
    doc: initialXml,
    extensions: [
      xml(),
      lineNumbers(),
      foldGutter(),
      oneDark,
      EditorView.lineWrapping,
      drawSelection(),
      hideTagsField, // Add the tag hiding field
      readOnlyCompartment.of(EditorState.readOnly.of(true)),
      editableCompartment.of(EditorView.editable.of(false)),
      EditorView.theme({
        ".cm-cursor": {
          borderLeftColor: "#528bff !important",
          borderLeftWidth: "2px !important",
          display: "block !important",
          visibility: "visible !important"
        },
        ".cm-selectionBackground": {
          backgroundColor: "#2e4862ff !important"
        },
      }),
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  let isReadOnly = true;

  window.editor = {
    toggleReadOnly() {
      isReadOnly = !isReadOnly;
      view.dispatch({
        effects: [
          readOnlyCompartment.reconfigure(EditorState.readOnly.of(isReadOnly)),
          editableCompartment.reconfigure(EditorView.editable.of(!isReadOnly))
        ]
      });
      view.focus();
      return isReadOnly;
    },

    setReadOnly(value) {
      isReadOnly = value;
      view.dispatch({
        effects: [
          readOnlyCompartment.reconfigure(EditorState.readOnly.of(isReadOnly)),
          editableCompartment.reconfigure(EditorView.editable.of(!isReadOnly))
        ]
      });
    },

    toggleTagVisibility() {
      const currentState = view.state.field(hideTagsField);
      view.dispatch({
        effects: toggleTagVisibility.of(!currentState)
      });
      return !currentState;
    },

    insertPageMarker(imageName, pageNumber = '001') {
      const { head } = view.state.selection.main;

      // Build the page marker tag
      const pageMarkerTag = `<span class="page-marker" data-image-name="${imageName}"><span class="page-number">${pageNumber}</span><img src="/img/settings/page_right.svg" /></span>`;

      // Insert at cursor position
      view.dispatch({
        changes: { from: head, insert: pageMarkerTag },
        selection: { anchor: head + pageMarkerTag.length }
      });
      view.focus();
    },

    getPageMarkerTag(imageName) {
      const content = view.state.doc.toString();
      const regex = new RegExp(
        `<span class="page-marker" data-image-name="${imageName}"><span class="page-number">.*?</span><img[^>]*></span>`,
        'i'
      );
      const match = content.match(regex);
      return match ? { tag: match[0], pos: content.indexOf(match[0]) } : null;
    },

    scrollToPageMarker(imageName) {
      const result = this.getPageMarkerTag(imageName);
      if (result) {
        view.dispatch({
          selection: { anchor: result.pos, head: result.pos + result.tag.length },
          effects: EditorView.scrollIntoView(result.pos, { y: "center" })
        });
        view.focus();
      }
    },

    isPageMarkerInserted(imageName) {
      return this.getPageMarkerTag(imageName) !== null;
    },

    countPageMarkerOccurrences(imageName) {
      const content = view.state.doc.toString();
      const regex = new RegExp(`data-image-name="${imageName}"`, 'g');
      const matches = content.match(regex);
      return matches ? matches.length : 0;
    },

    removePageMarker(imageName) {
      return new Promise((resolve) => {
        const result = this.getPageMarkerTag(imageName);

        if (result) {
          const tagPos = result.pos;
          const tagEnd = tagPos + result.tag.length;

          this.scrollToPageMarker(imageName);

          // Wait before removing to allow user to see the tag being targeted
          setTimeout(() => {
            view.dispatch({
              changes: { from: tagPos, to: tagEnd, insert: '' }
            });
            resolve(true);
          }, 200);
        } else {
          resolve(false);
        }
      });
    },
  };

  saveBtn.addEventListener('click', async () => {
    const updatedXml = view.state.doc.toString();
    const response = await fetch(`/comparison/${comparisonId}/editor?type=${fileType}`, {
      method: 'PUT',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Content-Type': 'application/xml',
        'Accept': 'application/json'
      },
      body: updatedXml
    });

    const data = await response.json();

    if (response.status === 403) {
      alert(data.error || 'Modification non autorisée : cette comparaison est publiée.');
    } else if (response.ok) {
      alert(data.message || 'Fichier sauvegardé avec succès !');
    } else {
      alert(data.error || 'Une erreur est survenue lors de la sauvegarde.');
    }
  });
};
