import { EditorState } from "@codemirror/state";
import { EditorView, basicSetup } from "codemirror"; // <- from new "codemirror" meta-package
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";

const buildUrl = (path) => {
  if (typeof path !== 'string') return path;
  if (typeof window.withBasePath === 'function') {
    return window.withBasePath(path);
  }
  return path.startsWith('/') ? path : `/${path}`;
};

window.initEditor = (initialXml, versionId) => {
  const container = document.getElementById('editor-container');
  const saveBtn = document.getElementById('save-xml');

  const startState = EditorState.create({
    doc: initialXml,
    extensions: [
      basicSetup,
      xml(),
      oneDark,
      EditorView.lineWrapping
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  saveBtn.addEventListener('click', async () => {
    const updatedXml = view.state.doc.toString();
    const response = await fetch(buildUrl(`/versions/${versionId}/editor`), {
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
