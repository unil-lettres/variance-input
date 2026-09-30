import { EditorState, Compartment, StateField, StateEffect } from "@codemirror/state";
import { EditorView, lineNumbers, drawSelection, Decoration, WidgetType, ViewPlugin, MatchDecorator, keymap } from "@codemirror/view";
import { standardKeymap } from "@codemirror/commands";
import { xml } from "@codemirror/lang-xml";
import { oneDark } from "@codemirror/theme-one-dark";
import { search, openSearchPanel, closeSearchPanel, searchPanelOpen as getSearchPanelState } from "@codemirror/search";

const areVersionEditorTooltipsEnabled = () => window.areVersionEditorTooltipsEnabled?.() === true;

const INLINE_TAG_CONFIGS = [
  {
    type: 'italic',
    className: 'cm-italic-tag',
    open: /^<(?:emph|em)\s*>$/i,
    close: /^<\/(?:emph|em)\s*>$/i,
    regexp: /(<emph\s*>|<\/emph\s*>|<em\s*>|<\/em\s*>)/gi,
    openLabel: "⟨i⟩",
    closeLabel: "⟨/i⟩",
    openName: 'Balise italique d\'ouverture',
    closeName: 'Balise italique de fermeture',
    nestedMessage: 'Balises italiques imbriquées détectées',
    containsMessage: 'Balise italique contient des balises non textuelles',
    emptyMessage: 'Balise italique vide (pas de texte entre ouverture et fermeture)',
  },
  {
    type: 'superscript',
    className: 'cm-superscript-tag',
    open: /^<sup\s*>$/i,
    close: /^<\/sup\s*>$/i,
    regexp: /(<sup\s*>|<\/sup\s*>)/gi,
    openLabel: "⟨sup⟩",
    closeLabel: "⟨/sup⟩",
    openName: 'Balise exposant d\'ouverture',
    closeName: 'Balise exposant de fermeture',
    nestedMessage: 'Balises exposant imbriquées détectées',
    containsMessage: 'Balise exposant contient des balises non textuelles',
    emptyMessage: 'Balise exposant vide (pas de texte entre ouverture et fermeture)',
  },
];

const decoratedInlineTagRegex = /^(?:<\/?(?:emph|em)\s*>|<\/?sup\s*>)$/i;

function inlineTagConfig(tag) {
  return INLINE_TAG_CONFIGS.find((config) => config.open.test(tag) || config.close.test(tag)) || null;
}

function isDecoratedInlineTag(tag) {
  return decoratedInlineTagRegex.test(tag);
}

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

// Widget to style inline formatting tags such as italics and superscript.
class InlineFormatTagWidget extends WidgetType {
  constructor(tag, view) {
    super();
    this.tag = tag;
    this.view = view;
    this.tooltip = null;
  }

  toDOM() {
    const config = inlineTagConfig(this.tag);
    const span = document.createElement("span");
    span.className = `cm-inline-tag ${config?.className || ''}`.trim();
    span.dataset.inlineTagType = config?.type || 'inline';
    span.textContent = this.tag.startsWith("</")
      ? (config?.closeLabel || "⟨/tag⟩")
      : (config?.openLabel || "⟨tag⟩");
    span.style.cursor = 'pointer';

    const bootstrapLib = window.bootstrap;
    if (bootstrapLib && areVersionEditorTooltipsEnabled()) {
      this.tooltip = new bootstrapLib.Tooltip(span, {
        title: 'Cliquez pour supprimer',
        trigger: 'hover',
        offset: [0, 10],
      });
    }

    span.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      try {
        const pos = this.view.posAtDOM(span);
        if (pos === null) return;

        this.view.dispatch({
          changes: { from: pos, to: pos + this.tag.length, insert: '' }
        });
        this.view.focus();
      } catch (err) {
        console.error('Error deleting inline tag:', err);
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
    span.setAttribute('data-image-name', this.imageName);
    span.style.cursor = 'pointer';

    const lineBefore = document.createElement("span");
    lineBefore.className = 'cm-page-number-mark-line';
    lineBefore.setAttribute('aria-hidden', 'true');

    const badge = document.createElement("span");
    badge.className = 'cm-page-number-mark-badge';
    badge.textContent = this.pageNumber;

    const i = document.createElement("i");
    i.className = 'bi bi-file-earmark';
    badge.prepend(i);

    const lineAfter = document.createElement("span");
    lineAfter.className = 'cm-page-number-mark-line';
    lineAfter.setAttribute('aria-hidden', 'true');

    span.append(lineBefore, badge, lineAfter);

    const bootstrapLib = window.bootstrap;
    if (bootstrapLib && areVersionEditorTooltipsEnabled()) {
      this.tooltip = new bootstrapLib.Tooltip(span, {
        title: () => this.pageNumber === '?' ? 'Cliquez pour numéroter la page' : 'Cliquez pour modifier le numéro de page',
        trigger: 'hover',
        offset: [0, 10],
      });
    }

    span.addEventListener('animationend', () => {
      span.classList.remove('page-marker-highlight');
    });

    span.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      try {
        // Get current position from cache
        const cache = this.getCacheFunction(this.view);
        const pageNumberData = cache.pageNumberPositions.get(this.imageName);

        if (!pageNumberData) return;

        const newPageNumber = prompt("Entrez le nouveau numéro de page, laissez vide pour le supprimer :", pageNumberData.content === '?' ? '' : pageNumberData.content);

        if (newPageNumber === null) return;

        if (newPageNumber.length > 6) {
          alert("Le numéro de page ne peut pas faire plus de 6 caractères.");
          return;
        }

        let changes = {};

        if (newPageNumber !== pageNumberData.content) {
          if (newPageNumber.trim() === '') {
            const markerPositions = cache.markerPositions.get(this.imageName) || null;
            const tagPos = markerPositions.pos;
            const tagEnd = tagPos + markerPositions.tag.length;

            changes =  { from: tagPos, to: tagEnd, insert: '' };
          } else {
            changes = { from: pageNumberData.start, to: pageNumberData.end, insert: newPageNumber };
          }
        }

        this.view.dispatch({changes});

        const callback = this.getClickedCallback();
        if (callback) callback();
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
const createHideTagsField = () => StateField.define({
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

      // Regex to match XML tags: <tag...> or </tag> or <tag/> or <pb ... pagination="123" />
      const tagRegex = /<\/?[a-zA-Z][^>]*\/?>/g;
      let match;

      while ((match = tagRegex.exec(text)) !== null) {
        const from = match.index;
        const to = from + match[0].length;
        const tag = match[0].toLowerCase();

        // Skip inline formatting tags - they have their own decoration plugin
        const originalTag = match[0];
        if (isDecoratedInlineTag(originalTag)) {
          continue;
        }

        // Handle <pb> tags with pagination attribute - hide parts around the number
        if (tag.startsWith('<pb') && originalTag.includes('pagination="')) {
             const paginationAttr = 'pagination="';
             const paginationIndex = originalTag.indexOf(paginationAttr);

             if (paginationIndex !== -1) {
                 const prefixEnd = from + paginationIndex + paginationAttr.length;
                 const suffixStart = text.indexOf('"', prefixEnd);

                 if (suffixStart !== -1 && suffixStart < to) {
                     // Hide the prefix: <pb ... pagination="
                     widgets.push(
                        Decoration.replace({
                            widget: new InvisibleTagWidget(text.substring(from, prefixEnd)),
                            inclusive: true
                        }).range(from, prefixEnd)
                     );

                     // Hide the suffix: " ... />
                     widgets.push(
                        Decoration.replace({
                            widget: new InvisibleTagWidget(text.substring(suffixStart, to)),
                            inclusive: true
                        }).range(suffixStart, to)
                     );

                     continue;
                 }
             }
        }

        // Handle tags by making them invisible.
        widgets.push(
          Decoration.replace({
            widget: new InvisibleTagWidget(match[0]),
            inclusive: true
          }).range(from, to)
        );
      }

      return Decoration.set(widgets.sort((a, b) => a.from - b.from));
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

// ViewPlugin to decorate inline formatting tags only when tags are hidden
const createInlineFormatTagPlugin = (hideTagsStateField) => ViewPlugin.fromClass(class {
  constructor(view) {
    this.view = view;
    this.inlineTagMatcher = new MatchDecorator({
      regexp: /(<emph\s*>|<\/emph\s*>|<em\s*>|<\/em\s*>|<sup\s*>|<\/sup\s*>)/gi,
      decoration: (match) => Decoration.replace({
        widget: new InlineFormatTagWidget(match[1], view),
      })
    });
    this.placeholders = this.buildDecorations(view);
  }

  update(update) {
    if (update.startState.field(hideTagsStateField) !== update.state.field(hideTagsStateField)) {
      this.placeholders = this.buildDecorations(update.view);
      return;
    }

    this.placeholders = this.inlineTagMatcher.updateDeco(update, this.placeholders);
  }

  buildDecorations(view) {
    if (!view.state.field(hideTagsStateField)) {
      return Decoration.none;
    }

    return this.inlineTagMatcher.createDeco(view);
  }

}, {
  decorations: instance => instance.placeholders,
  provide: plugin => EditorView.atomicRanges.of(view => {
    return view.plugin(plugin)?.placeholders || Decoration.none
  })
});

// ViewPlugin to manage page number widgets
const createPageNumberPlugin = (getClickedCallback, getCacheFunction, hideTagsStateField) => ViewPlugin.fromClass(class {
  constructor(view) {
    this.view = view;
    this.getClickedCallback = getClickedCallback;
    this.getCacheFunction = getCacheFunction;
    this.decorations = this.buildDecorations(view);
  }

  update(update) {
    if (
      update.docChanged ||
      update.startState.field(hideTagsStateField) !== update.state.field(hideTagsStateField)
    ) {
      this.decorations = this.buildDecorations(update.view);
    }
  }

  buildDecorations(view) {
    if (!view.state.field(hideTagsStateField)) {
      return Decoration.none;
    }

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

function validateInlineTagPairs(content, view, config) {
  const errors = [];
  const processedPositions = new Set();
  const openTagRegex = new RegExp(config.regexp.source, 'gi');
  const closeTagRegex = new RegExp(config.regexp.source, 'gi');
  const openTags = [];
  const closeTags = [];
  let match;

  while ((match = openTagRegex.exec(content)) !== null) {
    const tag = match[1] || match[0];
    if (!config.open.test(tag)) {
      continue;
    }
    openTags.push({
      pos: match.index,
      end: match.index + tag.length,
      tag,
    });
  }

  while ((match = closeTagRegex.exec(content)) !== null) {
    const tag = match[1] || match[0];
    if (!config.close.test(tag)) {
      continue;
    }
    closeTags.push({
      pos: match.index,
      end: match.index + tag.length,
      tag,
    });
  }

  const addError = (pos, type, message) => {
    if (processedPositions.has(pos)) {
      return false;
    }

    processedPositions.add(pos);
    errors.push({
      type,
      message,
      pos,
      lineNumber: view.state.doc.lineAt(pos).number,
    });
    return true;
  };

  const isInsideXmlTag = (pos) => {
    const beforeTag = content.substring(0, pos);
    return beforeTag.lastIndexOf('<') > beforeTag.lastIndexOf('>');
  };

  for (let i = 0; i < openTags.length; i++) {
    const openTag = openTags[i];

    if (isInsideXmlTag(openTag.pos)) {
      if (addError(openTag.pos, 'inside_tag', `${config.openName} à l'intérieur d'une balise XML`)) {
        continue;
      }
    }

    if (i < openTags.length - 1) {
      const nextOpen = openTags[i + 1];
      const currentClose = closeTags[i];

      if (currentClose && nextOpen.pos < currentClose.pos) {
        if (addError(nextOpen.pos, 'nested', config.nestedMessage)) {
          continue;
        }
      }
    }

    if (!closeTags[i]) {
      if (addError(openTag.pos, 'missing_close', `${config.openName} sans balise de fermeture correspondante`)) {
        continue;
      }
    }

    if (closeTags[i]) {
      const closeTag = closeTags[i];
      const betweenTags = content.substring(openTag.end, closeTag.pos);
      const withoutAllowedInlineTags = betweenTags.replace(/<\/?(?:emph|em|sup)\s*>/gi, '');

      if (/<[^>]+>/g.test(withoutAllowedInlineTags)) {
        if (addError(openTag.pos, 'contains_tags', config.containsMessage)) {
          continue;
        }
      }

      const textOnly = betweenTags.replace(/<[^>]+>/g, '').trim();
      if (textOnly.length === 0) {
        addError(openTag.pos, 'empty', config.emptyMessage);
      }
    }
  }

  if (closeTags.length > openTags.length) {
    for (let i = openTags.length; i < closeTags.length; i++) {
      const closeTag = closeTags[i];

      if (isInsideXmlTag(closeTag.pos)) {
        addError(closeTag.pos, 'inside_tag', `${config.closeName} à l'intérieur d'une balise XML`);
      } else {
        addError(closeTag.pos, 'missing_open', `${config.closeName} sans balise d'ouverture correspondante`);
      }
    }
  }

  return errors;
}

export default function (container, initialXml) {

  const hideTagsField = createHideTagsField();

  // Create compartments for dynamic reconfiguration
  const readOnlyCompartment = new Compartment();
  const editableCompartment = new Compartment();
  const lineNumbersCompartment = new Compartment();

  let onPageNumberClickedCallback = null;
  let onEditorReadyCallback = null;
  let onSearchPanelStateChangedCallback = null;
  let onContentChangedCallback = null;
  let isReadOnly = true;
  let editorReady = false;
  let searchPanelOpen = false;
  let skipCacheUpdate = false;
  let suppressContentChanged = false;
  let lineNumbersVisible = localStorage.getItem('editor-line-numbers') === 'true';

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
    if (!viewInstance || skipCacheUpdate) return;

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

    let regex = /<pb facs="([^"]+)" pagination="([^"]*)"\/>/g;
    let match;

    while ((match = regex.exec(content)) !== null) {
      const fullMatch = match[0];

      let imageName = match[1];

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

        let contentStart, contentEnd;

        const paginationAttr = 'pagination="';
        const paginationIndex = fullMatch.indexOf(paginationAttr);
        if (paginationIndex !== -1) {
            contentStart = pos + paginationIndex + paginationAttr.length;
            contentEnd = contentStart + pageNumber.length;
        }

        if (contentStart !== undefined && pageNumber.length > 0 && contentStart < contentEnd && contentEnd <= content.length) {
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
      lineNumbersCompartment.of(lineNumbersVisible ? lineNumbers() : []),
      EditorView.lineWrapping,
      drawSelection(),
      hideTagsField,
      createInlineFormatTagPlugin(hideTagsField),
      createPageNumberPlugin(
        () => onPageNumberClickedCallback,
        getCache,
        hideTagsField,
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
        if (update.docChanged && onContentChangedCallback && !suppressContentChanged) {
          onContentChangedCallback();
        }
      }),
      readOnlyCompartment.of(EditorState.readOnly.of(true)),
      editableCompartment.of(EditorView.editable.of(false)),
      EditorView.theme({
        "&": {
          backgroundColor: "#282a36",
          color: "#f8f8f2"
        },
        ".cm-content": {
          color: "inherit",
          textAlign: "left",
          textJustify: "auto"
        },
        ".cm-line": {
          textAlign: "left",
          textJustify: "auto"
        },
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
      }, { dark: true }),
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

    replaceDocument(content, { silent = false } = {}) {
      suppressContentChanged = !!silent;
      view.dispatch({
        changes: {
          from: 0,
          to: view.state.doc.length,
          insert: content
        },
        selection: { anchor: 0 }
      });
      invalidateCache();
      suppressContentChanged = false;
    },

    stopEnsureCacheUpdate() {
      ensureCacheUpdated(view);
      skipCacheUpdate = true;
    },

    resumeEnsureCacheUpdate() {
      skipCacheUpdate = false;
      ensureCacheUpdated(view);
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
        effects: toggleTagVisibility.of(!currentState),
        selection: { anchor: view.state.selection.main.head }
      });
      return !currentState;
    },

    getTagVisibility() {
      return view.state.field(hideTagsField);
    },

    toggleLineNumbers() {
      lineNumbersVisible = !lineNumbersVisible;
      view.dispatch({
        effects: lineNumbersCompartment.reconfigure(lineNumbersVisible ? lineNumbers() : [])
      });
      return lineNumbersVisible;
    },

    insertPageMarker(imageName, pageNumber = '?') {
      const { head } = view.state.selection.main;

      if (!this.canInsertAtPosition(head)) {
          alert("Impossible de placer le marqueur de page dans une balise XML.");
          return false;
      }

      if (!pageNumber || pageNumber === '?') {
          const newPageNumber = prompt("Entrez le nouveau numéro de page :");

          if (newPageNumber === null) return false;

          if (newPageNumber.length > 6) {
            alert("Le numéro de page ne peut pas faire plus de 6 caractères.");
            return false;
          }

          pageNumber = newPageNumber;
      }

      if (pageNumber.trim() === '') {
          return false;
      }

      // Build the page marker tag
      let pageMarkerTag = `<pb facs="${imageName}" pagination="${pageNumber}"/>`;

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

    removePageMarker(imageName) {
      const result = this.getPageMarkerTag(imageName);
      if (result) {
        view.dispatch({
          changes: { from: result.pos, to: result.pos + result.tag.length, insert: '' }
        });
        invalidateCache();
        return true;
      }
      return false;
    },

    removeAllPageMarkers() {
      ensureCacheUpdated(view);
      const changes = Array.from(markerCache.markerPositions.values())
        .map((marker) => ({
          from: marker.pos,
          to: marker.pos + marker.tag.length,
          insert: ''
        }))
        .sort((a, b) => b.from - a.from);

      if (changes.length === 0) {
        return 0;
      }

      view.dispatch({ changes });
      invalidateCache();
      view.focus();
      return changes.length;
    },

    scrollToPageMarker(imageName) {
      const result = this.getPageMarkerTag(imageName);
      if (result) {
        view.dispatch({
          selection: { anchor: result.pos, head: result.pos + result.tag.length },
          effects: EditorView.scrollIntoView(result.pos, { y: "center" })
        });
        view.focus();

        requestAnimationFrame(() => {
          const el = view.dom.querySelector(`.cm-page-number-mark[data-image-name="${imageName}"]`);
          if (el) {
            el.classList.add('page-marker-highlight');
          }
        });
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
      const openingTag = '<emph>';

      view.dispatch({
        changes: { from: head, insert: openingTag },
        selection: { anchor: head + openingTag.length }
      });

      invalidateCache();
      view.focus();
    },

    insertItalicCloseTag() {
      const { head } = view.state.selection.main;
      const closingTag = '</emph>';

      view.dispatch({
        changes: { from: head, insert: closingTag },
        selection: { anchor: head + closingTag.length }
      });

      invalidateCache();
      view.focus();
    },

    insertSuperscriptOpenTag() {
      const { head } = view.state.selection.main;
      const openingTag = '<sup>';

      view.dispatch({
        changes: { from: head, insert: openingTag },
        selection: { anchor: head + openingTag.length }
      });

      invalidateCache();
      view.focus();
    },

    insertSuperscriptCloseTag() {
      const { head } = view.state.selection.main;
      const closingTag = '</sup>';

      view.dispatch({
        changes: { from: head, insert: closingTag },
        selection: { anchor: head + closingTag.length }
      });

      invalidateCache();
      view.focus();
    },

    validateInlineTags() {
      const content = view.state.doc.toString();
      const errors = INLINE_TAG_CONFIGS.flatMap((config) => validateInlineTagPairs(content, view, config));
      errors.sort((a, b) => a.pos - b.pos);

      return errors;
    },

    validateItalicTags() {
      return this.validateInlineTags();
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
