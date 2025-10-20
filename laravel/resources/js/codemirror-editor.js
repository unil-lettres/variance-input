import { EditorState, Compartment, StateField, StateEffect } from "@codemirror/state";
import { EditorView, drawSelection, Decoration, WidgetType, ViewPlugin } from "@codemirror/view";
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

// Widget to replace <br> tags with line breaks
class LineBreakWidget extends WidgetType {
  constructor(tag) {
    super();
    this.tag = tag;
  }

  toDOM() {
    const br = document.createElement("br");
    return br;
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
    return false; // Start with tags VISIBLE for performance reasons
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
        const tag = match[0].toLowerCase();

        // Handle <br> tags - replace with line break widget
        if (tag === '<br>' || tag === '<br/>' || tag === '<br />') {
          // Check if there's a <lb> tag immediately before this <br>
          const beforeText = text.substring(Math.max(0, from - 7), from);
          const hasLbBefore = /<lb\s*\/?>/.test(beforeText);

          if (hasLbBefore) {
            // Ignore the <br> if it follows a <lb>
            widgets.push(
              Decoration.replace({
                widget: new InvisibleTagWidget(match[0]),
                inclusive: true
              }).range(from, to)
            );
          } else {
            // Show as line break widget
            widgets.push(
              Decoration.replace({
                widget: new LineBreakWidget(match[0]),
                inclusive: true
              }).range(from, to)
            );
          }
        } else {
          // Handle other tags - make them invisible
          widgets.push(
            Decoration.replace({
              widget: new InvisibleTagWidget(match[0]),
              inclusive: true
            }).range(from, to)
          );
        }
      }

      return Decoration.set(widgets);
    };
  })
});

// Helper functions to manage page number
function parsePageNumbers(view, getCacheFunction) {
  const cache = getCacheFunction(view);
  
  // Convert cache data to the format expected by decorations
  const pageNumbers = [];
  cache.pageNumberPositions.forEach((data, imageName) => {
    pageNumbers.push({
      imageName: imageName,
      content: data.content,
      start: data.start,
      end: data.end
    });
  });
  
  return pageNumbers;
}

function createPageNumberDecorations(view, getIsReadOnly, onPageNumbersChanged, getCacheFunction) {
  const decorations = [];
  const pageNumbers = parsePageNumbers(view, getCacheFunction);
  const isReadOnly = getIsReadOnly();

  if (onPageNumbersChanged) {
    onPageNumbersChanged(pageNumbers);
  }

  for (const pageNumber of pageNumbers) {
    decorations.push(
      Decoration.mark({
        class: isReadOnly ? "cm-page-number-mark" : "cm-page-number-mark readonly"
      }).range(pageNumber.start, pageNumber.end)
    );
  }

  return Decoration.set(decorations);
}

function setupPageNumberClickHandler(view, onUpdate, getIsReadOnly, getCacheFunction) {
  const handleClick = (e) => {
    if (!getIsReadOnly()) {
      return;
    }
    
    const pos = view.posAtDOM(e.target);
    if (pos === null) return;
    
    const pageNumbers = parsePageNumbers(view, getCacheFunction);

    for (const pageNumber of pageNumbers) {
      // Check if click position is within this page number
      if (pos >= pageNumber.start && pos <= pageNumber.end) {
        e.preventDefault();
        e.stopPropagation();
        
        const newPageNumber = prompt("Entrez le nouveau numéro de page:", pageNumber.content);

        if (newPageNumber === null) return;

        if (newPageNumber.length > 6) {
            alert("Le numéro de page ne peut pas faire plus de 6 caractères.");
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
const createPageNumberPlugin = (getClickedCallback, getIsReadOnly, getPageNumbersChangedCallback, getCacheFunction) => ViewPlugin.fromClass(class {
  constructor(view) {
    this.view = view;
    this.getIsReadOnly = getIsReadOnly;
    this.getPageNumbersChangedCallback = getPageNumbersChangedCallback;
    this.getCacheFunction = getCacheFunction;
    this.decorations = createPageNumberDecorations(view, getIsReadOnly, (pageNumbers) => {
      const cb = getPageNumbersChangedCallback();
      if (cb) cb(pageNumbers);
    }, getCacheFunction);
    this.lastReadOnlyState = getIsReadOnly();
    
    // Setup click handler once
    this.clickHandlerSetup = false;
    if (!this.clickHandlerSetup) {
      setupPageNumberClickHandler(view, () => {
        const callback = getClickedCallback();
        if (callback) callback();
      }, getIsReadOnly, getCacheFunction);
      this.clickHandlerSetup = true;
    }
  }

  update(update) {
    const currentReadOnlyState = this.getIsReadOnly();
    const readOnlyChanged = currentReadOnlyState !== this.lastReadOnlyState;
    
    if (update.docChanged || readOnlyChanged) {
      this.decorations = createPageNumberDecorations(update.view, this.getIsReadOnly, (pageNumbers) => {
        const cb = this.getPageNumbersChangedCallback();
        if (cb) cb(pageNumbers);
      }, this.getCacheFunction);
      this.lastReadOnlyState = currentReadOnlyState;
    }
  }
}, {
  decorations: v => v.decorations
});

export default function (container, initialXml) {

  // Create compartments for dynamic reconfiguration
  const readOnlyCompartment = new Compartment();
  const editableCompartment = new Compartment();

  let onPageNumberClickedCallback = null;
  let onPageNumbersChangedCallback = null;
  let onEditorReadyCallback = null;
  let isReadOnly = true;
  let editorReady = false;

  let markerCache = {
    content: null,
    insertedMarkers: new Set(),
    markerCounts: new Map(),
    markerPositions: new Map(),
    pageNumbers: new Map(),
    pageNumberPositions: new Map(),
  };

  const invalidateCache = () => {
    markerCache.content = null;
  };

  const ensureCacheUpdated = (viewInstance) => {
    if (!viewInstance) return;
    
    const content = viewInstance.state.doc.toString();

    if (markerCache.content === content) {
      return;
    }

    markerCache.content = content;
    markerCache.insertedMarkers.clear();
    markerCache.markerCounts.clear();
    markerCache.markerPositions.clear();
    markerCache.pageNumbers.clear();
    markerCache.pageNumberPositions.clear();

    const regex = /<span class="page-marker" data-image-name="([^"]+)"><span class="page-number">([^<]*)<\/span><img[^>]*><\/span>/g;
    let match;

    while ((match = regex.exec(content)) !== null) {
      const fullMatch = match[0];
      const imageName = String(parseInt(match[1], 10));
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
        
        const pageNumberTagStart = fullMatch.indexOf('<span class="page-number">');
        const contentStart = pos + pageNumberTagStart + '<span class="page-number">'.length;
        const contentEnd = contentStart + pageNumber.length;
        
        if (pageNumber.length > 0 && contentStart < contentEnd && contentEnd <= content.length) {
          markerCache.pageNumberPositions.set(imageName, {
            content: pageNumber,
            start: contentStart,
            end: contentEnd
          });
        }
      }
    }
  };
  
  const getCache = (viewInstance) => {
    ensureCacheUpdated(viewInstance);
    return markerCache;
  };

  const startState = EditorState.create({
    doc: initialXml,
    extensions: [
      xml(),
      foldGutter(),
      oneDark,
      EditorView.lineWrapping,
      drawSelection(),
      hideTagsField,
      createPageNumberPlugin(
        () => onPageNumberClickedCallback,
        () => isReadOnly,
        () => onPageNumbersChangedCallback,
        getCache,
      ),
      EditorView.updateListener.of((update) => {
        // Fire the ready callback only once, after the first update
        if (!editorReady && update.view.state.doc.length > 0) {
          editorReady = true;
          // Use requestAnimationFrame to ensure the DOM is fully rendered
          requestAnimationFrame(() => {
            if (onEditorReadyCallback) {
              onEditorReadyCallback();
            }
          });
        }
      }),
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
      ensureCacheUpdated(view);
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
      ensureCacheUpdated(view);
      return markerCache.insertedMarkers.has(imageName);
    },

    countPageMarkerOccurrences(imageName) {
      ensureCacheUpdated(view);
      return markerCache.markerCounts.get(imageName) || 0;
    },

    getAllMarkers() {
      ensureCacheUpdated(view);
      return {
        insertedMarkers: new Set(markerCache.insertedMarkers),
        markerCounts: new Map(markerCache.markerCounts)
      };
    },

    getPageNumber(imageName) {
      ensureCacheUpdated(view);
      return markerCache.pageNumbers.get(imageName) || null;
    },

    onPageNumberClicked(callback) {
      onPageNumberClickedCallback = callback;
    },

    onPageNumbersChanged(callback) {
      onPageNumbersChangedCallback = callback;
    },

    onEditorReady(callback) {
      onEditorReadyCallback = callback;
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
