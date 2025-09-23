import { EditorView, basicSetup } from "codemirror"
import { xml } from "@codemirror/lang-xml"
import { oneDark } from "@codemirror/theme-one-dark"

window.mountCodeMirror = (selector, initial = "") => {
  const el = document.querySelector(selector)
  if (!el) return null
  return new EditorView({
    doc: initial,
    extensions: [basicSetup, xml(), oneDark],
    parent: el,
  })
}
