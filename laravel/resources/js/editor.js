import { EditorState, Compartment } from "@codemirror/state";
import { EditorView, lineNumbers, drawSelection, highlightActiveLine } from "@codemirror/view";
import { foldGutter } from "@codemirror/language";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";

window.initEditor = (initialXml, comparisonId) => {
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
    const response = await fetch(`/comparison/${comparisonId}/editor`, {
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
