import { EditorState } from "@codemirror/state";
import { EditorView, keymap } from "@codemirror/view";
import { standardKeymap } from "@codemirror/commands";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";
import { search, openSearchPanel, closeSearchPanel, searchPanelOpen as getSearchPanelState } from "@codemirror/search";

export default function (container, initialXml) {

  let onContentChangedCallback = null;
  let onSearchPanelStateChangedCallback = null;
  let searchPanelOpen = false;

  const startState = EditorState.create({
    doc: initialXml,
    extensions: [
      xml(),
      search(),
      oneDark,
      EditorView.lineWrapping,
      keymap.of(standardKeymap),
      EditorView.updateListener.of((update) => {
        // Track search panel state changes
        const newSearchPanelState = getSearchPanelState(update.state);
        if (newSearchPanelState !== searchPanelOpen) {
          searchPanelOpen = newSearchPanelState;
          if (onSearchPanelStateChangedCallback) {
            onSearchPanelStateChangedCallback(searchPanelOpen);
          }
        }

        // Track content changes
        if (update.docChanged && onContentChangedCallback) {
          onContentChangedCallback();
        }
      }),
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  return {
    get view() {
      return view;
    },

    onSearchPanelStateChanged(callback) {
      onSearchPanelStateChangedCallback = callback;
    },

    onContentChanged(callback) {
      onContentChangedCallback = callback;
    },

    toggleSearch() {
      if (searchPanelOpen) {
        closeSearchPanel(view);
      } else {
        openSearchPanel(view);
        view.focus();
      }
    },
  };
};
