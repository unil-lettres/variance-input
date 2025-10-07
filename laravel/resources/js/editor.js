import { EditorState, Compartment } from "@codemirror/state";
import { EditorView, lineNumbers, drawSelection, highlightActiveLine } from "@codemirror/view";
import { foldGutter } from "@codemirror/language";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";

window.initEditor = (initialXml, versionId) => {
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
      highlightActiveLine(),
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
          backgroundColor: "#3d5975 !important" 
        },
        ".cm-activeLine": {
          backgroundColor: "#2c313c !important"
        }
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
    
    removeTag(tagName) {
      const content = view.state.doc.toString();
      const tagPos = content.indexOf(tagName);

      if (tagPos !== -1) {
        // Find the line containing the tag
        const line = view.state.doc.lineAt(tagPos);
        const lineStart = line.from;
        const lineEnd = line.to;
        
        this.scrollToTag(tagName);
        
        // Wait 1 second, then remove the line
        setTimeout(() => {
          // Check if there's a newline after this line
          const hasNextLine = lineEnd < view.state.doc.length;
          const deleteEnd = hasNextLine ? lineEnd + 1 : lineEnd;
          
          view.dispatch({
            changes: { from: lineStart, to: deleteEnd, insert: '' }
          });
        }, 500);
        
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
