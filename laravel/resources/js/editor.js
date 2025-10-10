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
    insertAtCursor(text) {
      const { head } = view.state.selection.main;
      const line = view.state.doc.lineAt(head);
      const lineStart = line.from;
      const lineText = line.text;

      // Extract indentation from current line
      const indentMatch = lineText.match(/^(\s*)/);
      const indent = indentMatch ? indentMatch[1] : '';

      // Insert indented text + new line before current line
      const insertText = indent + text + '\n';

      view.dispatch({
        changes: { from: lineStart, insert: insertText },
        selection: { anchor: lineStart }
      });
      view.focus();
    },

    findTagPosition(tagName) {
      const content = view.state.doc.toString();
      return content.indexOf(tagName);
    },

    scrollToTag(tagName) {
      const pos = this.findTagPosition(tagName);
      if (pos !== -1) {
        view.dispatch({
          selection: { anchor: pos, head: pos + tagName.length },
          effects: EditorView.scrollIntoView(pos, { y: "center" })
        });
        view.focus();
      }
    },

    isTagInserted(tagName) {
      return this.findTagPosition(tagName) !== -1;
    },

    countTagOccurrences(tagName) {
      const content = view.state.doc.toString();
      let count = 0;
      let pos = 0;
      while ((pos = content.indexOf(tagName, pos)) !== -1) {
        count++;
        pos += tagName.length;
      }
      return count;
    },

    removeTag(tagName) {
      return new Promise((resolve) => {
        const content = view.state.doc.toString();
        const tagPos = content.indexOf(tagName);

        if (tagPos !== -1) {
          // Find the line containing the tag
          const line = view.state.doc.lineAt(tagPos);
          const lineStart = line.from;
          const lineEnd = line.to;
          const lineText = line.text;

          // Check if the tag is alone on the line (only whitespace around it)
          const lineWithoutTag = lineText.replace(tagName, '');
          const isTagAlone = lineWithoutTag.trim() === '';

          this.scrollToTag(tagName);

          // Wait before removing the line to allow user to see the line being targeted
          setTimeout(() => {
            if (isTagAlone) {
              // Remove the entire line including the newline character
              const hasNextLine = lineEnd < view.state.doc.length;
              const deleteEnd = hasNextLine ? lineEnd + 1 : lineEnd;

              view.dispatch({
                changes: { from: lineStart, to: deleteEnd, insert: '' }
              });
            } else {
              // Remove only the tag
              const tagEnd = tagPos + tagName.length;
              view.dispatch({
                changes: { from: tagPos, to: tagEnd, insert: '' }
              });
            }
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
