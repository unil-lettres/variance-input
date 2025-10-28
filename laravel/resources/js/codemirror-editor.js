import { EditorState, Compartment, StateField, StateEffect } from "@codemirror/state";
import { EditorView, drawSelection, Decoration, WidgetType, ViewPlugin, MatchDecorator, keymap } from "@codemirror/view";
import { standardKeymap } from "@codemirror/commands";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";
import { search, openSearchPanel, closeSearchPanel, searchPanelOpen as getSearchPanelState } from "@codemirror/search";

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

// Widget to style italic tags
class ItalicTagWidget extends WidgetType {
  constructor(isOpening, view) {
    super();
    this.isOpening = isOpening;
    this.view = view;
    this.tooltip = null;
  }

  toDOM() {
    const span = document.createElement("span");
    span.className = "cm-italic-tag";
    span.textContent = this.isOpening ? "⟨i⟩" : "⟨/i⟩";
    span.title = `Cliquez pour supprimer`;
    span.style.cursor = 'pointer';
    
    this.tooltip = new bootstrap.Tooltip(span, {
        trigger: 'hover',
        offset: [0, 10],
    });

    span.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      try {
        const pos = this.view.posAtDOM(span);
        if (pos === null) return;

        const tag = this.isOpening ? '<em>' : '</em>';

        this.view.dispatch({
          changes: { from: pos, to: pos + tag.length, insert: '' }
        });
        this.view.focus();
      } catch (err) {
        console.error('Error deleting italic tag:', err);
      }
    });

    return span;
  }

  destroy(dom) {
    if (this.tooltip) {
      this.tooltip.dispose();
      this.tooltip = null;
    }
  }

  ignoreEvent(event) {
    return false;
  }
}

// Widget to display and edit page numbers
class PageNumberWidget extends WidgetType {
  constructor(pageNumber, imageName, view, getCacheFunction, getClickedCallback) {
    super();
    this.pageNumber = pageNumber;
    this.imageName = imageName;
    this.view = view;
    this.getCacheFunction = getCacheFunction;
    this.getClickedCallback = getClickedCallback;
    this.tooltip = null;
  }

  toDOM() {
    const span = document.createElement("span");
    span.className = 'cm-page-number-mark';
    span.textContent = this.pageNumber;
    span.style.cursor = 'pointer';

    const i = document.createElement("i");
    i.className = 'bi bi-file-earmark mr-1';
    span.prepend(i);
    
    this.tooltip = new bootstrap.Tooltip(span, {
      title: () => this.pageNumber === '?' ? 'Cliquez pour numéroter la page' : 'Cliquez pour modifier le numéro de page',
      trigger: 'hover',
      offset: [0, 10],
    });

    span.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      try {
        // Get current position from cache
        const cache = this.getCacheFunction(this.view);
        const pageNumberData = cache.pageNumberPositions.get(this.imageName);
        
        if (!pageNumberData) return;

        const newPageNumber = prompt("Entrez le nouveau numéro de page:", pageNumberData.content === '?' ? '' : pageNumberData.content);

        if (newPageNumber === null) return;

        if (newPageNumber.length > 6) {
          alert("Le numéro de page ne peut pas faire plus de 6 caractères.");
          return;
        }

        if (newPageNumber !== pageNumberData.content) {
          if (newPageNumber.trim() === '') {
            alert("Le numéro de page ne peut pas être vide. Pour supprimer un numéro de page, cliquez à deux reprises sur le bouton d'insertion qui lui est associé.");
            return;
          }
          this.view.dispatch({
            changes: { from: pageNumberData.start, to: pageNumberData.end, insert: newPageNumber }
          });

          const callback = this.getClickedCallback();
          if (callback) callback();
        }
      } catch (err) {
        console.error('Error editing page number:', err);
      }
    });
    
    return span;
  }

  destroy(dom) {
    if (this.tooltip) {
      this.tooltip.dispose();
      this.tooltip = null;
    }
  }

  ignoreEvent(event) {
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

        // Skip italic tags - they have their own decoration plugin
        const originalTag = match[0];
        if (originalTag.match(/<em\s*>/i) || originalTag.match(/<\/em\s*>/i)) {
          continue;
        }

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

// Helper function to parse page numbers from cache
function parsePageNumbers(view, getCacheFunction) {
  const cache = getCacheFunction(view);
  
  // Convert cache data to the format expected by widgets
  const pageNumbers = [];
  cache.markerPositions.forEach((markerData, imageName) => {
    const pageNumber = cache.pageNumbers.get(imageName);
    const tag = markerData.tag;
    const pos = markerData.pos;
    
    pageNumbers.push({
      imageName: imageName,
      content: pageNumber,
      start: pos,
      end: pos + tag.length
    });
  });
  
  return pageNumbers;
}

// ViewPlugin to decorate italic tags
const createItalicTagPlugin = () => ViewPlugin.fromClass(class {
  constructor(view) {
    this.view = view;

    this.italicOpenMatcher = new MatchDecorator({
      regexp: /(<em\s*>|<\/em\s*>)/gi,
      decoration: (match) => Decoration.replace({
        widget: new ItalicTagWidget(match[1].startsWith("<em"), view),
      })
    });

    this.placeholders = this.italicOpenMatcher.createDeco(view)
  }

  update(update) {
    this.placeholders = this.italicOpenMatcher.updateDeco(update, this.placeholders)
  }

}, {
  decorations: instance => instance.placeholders,
  provide: plugin => EditorView.atomicRanges.of(view => {
    return view.plugin(plugin)?.placeholders || Decoration.none
  })
});

// ViewPlugin to manage page number widgets
const createPageNumberPlugin = (getClickedCallback, getCacheFunction) => ViewPlugin.fromClass(class {
  constructor(view) {
    this.view = view;
    this.getClickedCallback = getClickedCallback;
    this.getCacheFunction = getCacheFunction;
    this.decorations = this.buildDecorations(view);
  }

  update(update) {
    if (update.docChanged) {
      this.decorations = this.buildDecorations(update.view);
    }
  }

  buildDecorations(view) {
    const pageNumbers = parsePageNumbers(view, this.getCacheFunction);
    const widgets = [];

    for (const pageNumber of pageNumbers) {
      widgets.push(
        Decoration.replace({
          widget: new PageNumberWidget(
            pageNumber.content,
            pageNumber.imageName,
            view,
            this.getCacheFunction,
            this.getClickedCallback
          ),
          block: false
        }).range(pageNumber.start, pageNumber.end)
      );
    }

    return Decoration.set(widgets);
  }
}, {
  decorations: instance => instance.decorations,
  provide: plugin => EditorView.atomicRanges.of(view => {
    return view.plugin(plugin)?.decorations || Decoration.none
  })
});

export default function (container, initialXml) {

  // Create compartments for dynamic reconfiguration
  const readOnlyCompartment = new Compartment();
  const editableCompartment = new Compartment();

  let onPageNumberClickedCallback = null;
  let onEditorReadyCallback = null;
  let onSearchPanelStateChangedCallback = null;
  let onContentChangedCallback = null;
  let isReadOnly = true;
  let editorReady = false;
  let searchPanelOpen = false;

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
      search(),
      oneDark,
      EditorView.lineWrapping,
      drawSelection(),
      hideTagsField,
      createItalicTagPlugin(),
      createPageNumberPlugin(
        () => onPageNumberClickedCallback,
        getCache,
      ),
      keymap.of(standardKeymap),
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
      readOnlyCompartment.of(EditorState.readOnly.of(true)),
      editableCompartment.of(EditorView.editable.of(false)),
      EditorView.theme({
        ".cm-cursor": {
          borderLeftColor: "#d9ff00ff !important",
          borderLeftWidth: "2px !important",
          display: "block !important",
          visibility: "visible !important",
        },
        ".cm-cursorLayer": {
          animationIterationCount: "infinite",
        },
        ".cm-selectionBackground": {
          backgroundColor: "#2e4862ff !important"
        },
      }),
    ]
  });

  const view = new EditorView({ state: startState, parent: container });

  // Initialize readonly class on container
  if (isReadOnly) {
    container.classList.add('cm-readonly');
  }

  return {
    get view() {
      return view;
    },

    toggleReadOnly() {
      this.setReadOnly(!isReadOnly);
      view.focus();
      return isReadOnly;
    },

    setReadOnly(value) {
      isReadOnly = value;
      view.dispatch({
        effects: [
          readOnlyCompartment.reconfigure(EditorState.readOnly.of(isReadOnly)),
          editableCompartment.reconfigure(EditorView.editable.of(!isReadOnly))
        ],
        selection: { anchor: view.state.selection.main.head }
      });
      container.classList.toggle('cm-readonly', isReadOnly);
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

    insertPageMarker(imageName, pageNumber = '?') {
      const { head } = view.state.selection.main;

      // Build the page marker tag
      const pageMarkerTag = `<span class="page-marker" data-image-name="${imageName}"><span class="page-number">${pageNumber}</span><img src="/img/settings/page_right.svg" /></span>`;

      if (!this.canInsertAtPosition(head)) {
        alert("Impossible de placer le marqueur de page dans une balise XML.");
        return false;
      }

      // Insert at cursor position
      view.dispatch({
        changes: { from: head, insert: pageMarkerTag },
        selection: { anchor: head + pageMarkerTag.length }
      });

      invalidateCache();

      view.focus();
      return true;
    },

    canInsertAtPosition(pos) {
      const content = view.state.doc.toString();
      const beforePosition = content.substring(0, pos);
      
      // Check if position is inside a tag (between < and >)
      const lastOpenBracket = beforePosition.lastIndexOf('<');
      const lastCloseBracket = beforePosition.lastIndexOf('>');
      
      return lastOpenBracket <= lastCloseBracket;
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

    onEditorReady(callback) {
      onEditorReadyCallback = callback;
    },

    onSearchPanelStateChanged(callback) {
      onSearchPanelStateChangedCallback = callback;
    },

    onContentChanged(callback) {
      onContentChangedCallback = callback;
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

    toggleSearch() {
      if (searchPanelOpen) {
        closeSearchPanel(view);
      } else {
        openSearchPanel(view);
        view.focus();
      }
    },

    insertItalicOpenTag() {
      const { head } = view.state.selection.main;
      const openingTag = '<em>';
      
      view.dispatch({
        changes: { from: head, insert: openingTag },
        selection: { anchor: head + openingTag.length }
      });
      
      invalidateCache();
      view.focus();
    },

    insertItalicCloseTag() {
      const { head } = view.state.selection.main;
      const closingTag = '</em>';
      
      view.dispatch({
        changes: { from: head, insert: closingTag },
        selection: { anchor: head + closingTag.length }
      });
      
      invalidateCache();
      view.focus();
    },

    validateItalicTags() {
      const content = view.state.doc.toString();
      const errors = [];
      const processedPositions = new Set(); // Track positions that already have an error

      // Find all italic opening and all closing tags
      const openTagRegex = /<em\s*>/gi;
      const closeTagRegex = /<\/em\s*>/gi;
      
      const openTags = [];
      const closeTags = [];
      
      let match;
      
      openTagRegex.lastIndex = 0;
      while ((match = openTagRegex.exec(content)) !== null) {
        openTags.push({
          pos: match.index,
          end: match.index + match[0].length,
          tag: match[0]
        });
      }
      
      closeTagRegex.lastIndex = 0;
      while ((match = closeTagRegex.exec(content)) !== null) {
        closeTags.push({
          pos: match.index,
          end: match.index + match[0].length,
          tag: match[0]
        });
      }

      // Helper function to add error only if position not already processed
      const addError = (pos, type, message) => {
        if (!processedPositions.has(pos)) {
          processedPositions.add(pos);
          errors.push({
            type: type,
            message: message,
            pos: pos,
            lineNumber: view.state.doc.lineAt(pos).number
          });
          return true;
        }
        return false;
      };

      // Process each opening tag with priority checks
      for (let i = 0; i < openTags.length; i++) {
        const openTag = openTags[i];
        
        // Check 1 (priority): Tag inside XML tag
        const beforeTag = content.substring(0, openTag.pos);
        const lastOpenBracket = beforeTag.lastIndexOf('<');
        const lastCloseBracket = beforeTag.lastIndexOf('>');
        
        if (lastOpenBracket > lastCloseBracket) {
          if (addError(openTag.pos, 'inside_tag', 'Balise italique d\'ouverture à l\'intérieur d\'une balise XML')) {
            continue; // Skip other checks for this tag
          }
        }
        
        // Check 2: Nested tags
        if (i < openTags.length - 1) {
          const nextOpen = openTags[i + 1];
          const currentClose = closeTags[i];
          
          if (currentClose && nextOpen.pos < currentClose.pos) {
            if (addError(nextOpen.pos, 'nested', 'Balises italiques imbriquées détectées')) {
              continue;
            }
          }
        }
        
        // Check 3: Missing closing tag
        if (!closeTags[i]) {
          if (addError(openTag.pos, 'missing_close', 'Balise italique d\'ouverture sans balise de fermeture correspondante')) {
            continue;
          }
        }
        
        // If we have a closing tag, check content
        if (closeTags[i]) {
          const closeTag = closeTags[i];
          const betweenTags = content.substring(openTag.end, closeTag.pos);
          
          // Check 4: Contains other tags
          if (/<[^>]+>/g.test(betweenTags)) {
            if (addError(openTag.pos, 'contains_tags', 'Balise italique contient d\'autres balises (seul du texte est autorisé)')) {
              continue;
            }
          }
          
          // Check 5: Empty tag
          const textOnly = betweenTags.replace(/<[^>]+>/g, '').trim();
          if (textOnly.length === 0) {
            addError(openTag.pos, 'empty', 'Balise italique vide (pas de texte entre ouverture et fermeture)');
          }
        }
      }

      // Process orphan closing tags
      if (closeTags.length > openTags.length) {
        for (let i = openTags.length; i < closeTags.length; i++) {
          const closeTag = closeTags[i];
          
          // Check if inside XML tag
          const beforeTag = content.substring(0, closeTag.pos);
          const lastOpenBracket = beforeTag.lastIndexOf('<');
          const lastCloseBracket = beforeTag.lastIndexOf('>');
          
          if (lastOpenBracket > lastCloseBracket) {
            addError(closeTag.pos, 'inside_tag', 'Balise italique de fermeture à l\'intérieur d\'une balise XML');
          } else {
            addError(closeTag.pos, 'missing_open', 'Balise italique de fermeture sans balise d\'ouverture correspondante');
          }
        }
      }

      // Sort errors by position
      errors.sort((a, b) => a.pos - b.pos);

      return errors;
    },

    scrollToPosition(pos) {
      view.dispatch({
        selection: { anchor: pos, head: pos },
        effects: EditorView.scrollIntoView(pos, { y: "center" })
      });
      view.focus();
    },
  };
};
