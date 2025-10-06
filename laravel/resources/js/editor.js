import { EditorState } from "@codemirror/state";
import { EditorView, lineNumbers } from "@codemirror/view";
import { foldGutter } from "@codemirror/language";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";

window.initEditor = (initialXml, versionId) => {
  const container = document.getElementById('editor-container');
  const saveBtn = document.getElementById('save-xml');

  const startState = EditorState.create({
    doc: initialXml,
    extensions: [
      xml(),
      lineNumbers(),
      foldGutter(),
      oneDark,
      EditorView.lineWrapping,
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  window.editor = {
    insertAtCursor: (text) => {
      const { from, to } = view.state.selection.main;
      view.dispatch({
        changes: { from, to, insert: text },
        selection: { anchor: from + text.length }
      });
      view.focus();
    }
  };

  saveBtn.addEventListener('click', async () => {
    const updatedXml = view.state.doc.toString();
    const response = await fetch(`/versions/${versionId}/editor`, {
      method: 'PUT',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Content-Type': 'application/xml',
        'Accept': 'application/json'
      },
      body: updatedXml
    });

    const data = await response.json();
    alert(data.message || 'XML saved successfully!');
  });
};
