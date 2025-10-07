import { EditorState } from "@codemirror/state";
import { EditorView, lineNumbers, drawSelection, highlightActiveLine } from "@codemirror/view";
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
      drawSelection(),
      highlightActiveLine(),
      EditorState.readOnly.of(true),
      EditorView.editable.of(false),
      EditorView.theme({
        ".cm-cursor": { 
          borderLeftColor: "#528bff !important",
          borderLeftWidth: "2px !important",
          display: "block !important",
          visibility: "visible !important"
        },
        ".cm-selectionBackground": { 
          backgroundColor: "#3d5975 !important" 
        },
        ".cm-activeLine": {
          backgroundColor: "#2c313c !important"
        }
      }),
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  window.editor = {
    insertAtCursor(text) {
      const { head } = view.state.selection.main;
      
      view.dispatch({
        changes: { from: head, insert: text },
        selection: { anchor: head + text.length }
      });
      view.focus();
    },
    
    findTagPosition(tagName) {
      const content = view.state.doc.toString();
      const tagPattern = new RegExp(`<${tagName}[\\s>]`, 'i');
      const match = content.match(tagPattern);
      if (match) {
        return content.indexOf(match[0]);
      }
      return -1;
    },
    
    scrollToTag(tagName) {
      const pos = this.findTagPosition(tagName);
      if (pos !== -1) {
        view.dispatch({
          selection: { anchor: pos, head: pos + tagName.length + 2 },
          scrollIntoView: true
        });
        view.focus();
      }
    },

    isTagInserted(tagName) {
      return this.findTagPosition(tagName) !== -1;
    },
    
    removeTag(tagName) {
      const content = view.state.doc.toString();
      const tagPos = content.indexOf(tagName);

      if (tagPos !== -1) {
        // Find the line containing the tag
        const line = view.state.doc.lineAt(tagPos);
        const lineStart = line.from;
        const lineEnd = line.to;
        
        // Remove the entire line including the newline character
        // Check if there's a newline after this line
        const hasNextLine = lineEnd < view.state.doc.length;
        const deleteEnd = hasNextLine ? lineEnd + 1 : lineEnd;
        
        view.dispatch({
          changes: { from: lineStart, to: deleteEnd, insert: '' }
        });
        return true;
      }
      return false;
    },
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
