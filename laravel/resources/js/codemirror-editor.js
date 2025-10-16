import { EditorState, Compartment, StateField, StateEffect } from "@codemirror/state";
import { EditorView, lineNumbers, drawSelection, Decoration, WidgetType, ViewPlugin } from "@codemirror/view";
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

// Helper functions to manage page number
// Parse all page numbers in the document and return their positions
function parsePageNumbers(text) {
  const pageNumbers = [];
  const pageNumberRegex = /<span class="page-marker" data-image-name="[^"]+"><span class="page-number">([^<]*)<\/span><img[^>]*><\/span>/g;
  let match;

  while ((match = pageNumberRegex.exec(text)) !== null) {
    const fullMatch = match[0];
    const pageNumberContent = match[1];
    const matchStart = match.index;
    const pageNumberTagStart = fullMatch.indexOf('<span class="page-number">');
    const contentStart = matchStart + pageNumberTagStart + '<span class="page-number">'.length;
    const contentEnd = contentStart + pageNumberContent.length;

    if (pageNumberContent.length > 0 && contentStart < contentEnd && contentEnd <= text.length) {
      pageNumbers.push({
        content: pageNumberContent,
        start: contentStart,
        end: contentEnd
      });
    }
  }

  return pageNumbers;
}

function createPageNumberDecorations(view) {
  const decorations = [];
  const text = view.state.doc.toString();
  const pageNumbers = parsePageNumbers(text);

  for (const pageNumber of pageNumbers) {
    decorations.push(
      Decoration.mark({
        class: "cm-page-number-mark"
      }).range(pageNumber.start, pageNumber.end)
    );
  }

  return Decoration.set(decorations);
}

function setupPageNumberClickHandler(view, onUpdate) {
  const handleClick = (e) => {
    const pos = view.posAtDOM(e.target);
    if (pos === null) return;
    
    const text = view.state.doc.toString();
    const pageNumbers = parsePageNumbers(text);

    for (const pageNumber of pageNumbers) {
      // Check if click position is within this page number
      if (pos >= pageNumber.start && pos <= pageNumber.end) {
        e.preventDefault();
        e.stopPropagation();
        
        const newPageNumber = prompt("Entrez le nouveau numéro de page:", pageNumber.content);

        if (newPageNumber === null) return;

        if (isNaN(newPageNumber) || newPageNumber < 0 || newPageNumber.length > 4) {
            alert("Le numéro de page doit être un nombre supérieur à 0 et de 4 chiffres maximum.");
            return;
        }

        if (newPageNumber !== pageNumber.content) {
          view.dispatch({
            changes: { from: pageNumber.start, to: pageNumber.end, insert: newPageNumber }
          });
          
          if (onUpdate) {
            onUpdate();
          }
        }
        return;
      }
    }
  };

  view.dom.addEventListener('click', handleClick);
}

// ViewPlugin to manage page number decorations
const createPageNumberPlugin = (getCallback) => ViewPlugin.fromClass(class {
  constructor(view) {
    this.view = view;
    this.decorations = createPageNumberDecorations(view);
    
    // Setup click handler once
    this.clickHandlerSetup = false;
    if (!this.clickHandlerSetup) {
      setupPageNumberClickHandler(view, () => {
        const callback = getCallback();
        if (callback) callback();
      });
      this.clickHandlerSetup = true;
    }
  }

  update(update) {
    if (update.docChanged) {
      this.decorations = createPageNumberDecorations(update.view);
    }
  }
}, {
  decorations: v => v.decorations
});

export default function (container, initialXml) {

  // Create compartments for dynamic reconfiguration
  const readOnlyCompartment = new Compartment();
  const editableCompartment = new Compartment();

  let onPageNumberUpdateCallback = null;

  let markerCache = {
    content: null,
    insertedMarkers: new Set(),
    markerCounts: new Map(),
    markerPositions: new Map(),
    pageNumbers: new Map() // Store page numbers for each marker
  };

  const invalidateCache = () => {
    markerCache.content = null;
  };

  const ensureCacheUpdated = () => {
    const content = view.state.doc.toString();

    if (markerCache.content === content) {
      return;
    }

    markerCache.content = content;
    markerCache.insertedMarkers.clear();
    markerCache.markerCounts.clear();
    markerCache.markerPositions.clear();
    markerCache.pageNumbers.clear();

    const regex = /<span class="page-marker" data-image-name="([^"]+)"><span class="page-number">([^<]*)<\/span><img[^>]*><\/span>/g;
    let match;

    while ((match = regex.exec(content)) !== null) {
      const imageName = match[1];
      const pageNumber = match[2];
      const tag = match[0];
      const pos = match.index;

      markerCache.insertedMarkers.add(imageName);
      markerCache.markerCounts.set(
        imageName,
        (markerCache.markerCounts.get(imageName) || 0) + 1
      );

      if (!markerCache.markerPositions.has(imageName)) {
        markerCache.markerPositions.set(imageName, { tag, pos });
        markerCache.pageNumbers.set(imageName, pageNumber);
      }
    }
  };

  const startState = EditorState.create({
    doc: initialXml,
    extensions: [
      xml(),
      lineNumbers(),
      foldGutter(),
      oneDark,
      EditorView.lineWrapping,
      drawSelection(),
      hideTagsField,
      createPageNumberPlugin(() => onPageNumberUpdateCallback),
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
        ".cm-page-number": {
          backgroundColor: "#ff6b35 !important",
          color: "#ffffff !important",
          fontWeight: "bold !important",
          padding: "2px 6px !important",
          borderRadius: "3px !important",
          border: "2px solid #ff4500 !important",
          display: "inline-block !important",
          fontSize: "1.1em !important"
        },
      }),
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  let isReadOnly = true;

  return {
    get view() {
      return view;
    },

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

    getTagVisibility() {
      return view.state.field(hideTagsField);
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

      invalidateCache();

      view.focus();
    },

    getPageMarkerTag(imageName) {
      ensureCacheUpdated();
      return markerCache.markerPositions.get(imageName) || null;
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
      ensureCacheUpdated();
      return markerCache.insertedMarkers.has(imageName);
    },

    countPageMarkerOccurrences(imageName) {
      ensureCacheUpdated();
      return markerCache.markerCounts.get(imageName) || 0;
    },

    getAllMarkers() {
      ensureCacheUpdated();
      return {
        insertedMarkers: new Set(markerCache.insertedMarkers),
        markerCounts: new Map(markerCache.markerCounts)
      };
    },

    getPageNumber(imageName) {
      ensureCacheUpdated();
      return markerCache.pageNumbers.get(imageName) || null;
    },

    onPageNumberUpdate(callback) {
      onPageNumberUpdateCallback = callback;
    },

    removePageMarker(imageName) {
      const result = this.getPageMarkerTag(imageName);

      if (result) {
        const tagPos = result.pos;
        const tagEnd = tagPos + result.tag.length;

        view.dispatch({
          changes: { from: tagPos, to: tagEnd, insert: '' }
        });

        invalidateCache();
      }
    },
  };
};
