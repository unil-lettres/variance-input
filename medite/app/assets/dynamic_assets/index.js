/**
 * Skipped minification because the original files appears to be already minified.
 * Do NOT use SRI with dynamically generated files! More information: https://www.jsdelivr.com/using-sri-with-dynamic-files
 */
!function(e, t) {
    "object" == typeof exports && "object" == typeof module ? module.exports = t() : "function" == typeof define && define.amd ? define([], t) : "object" == typeof exports ? exports.Fragment = t() : e.Fragment = t()
}(window, (function() {
    return function(e) {
        var t = {};
        function n(r) {
            if (t[r])
                return t[r].exports;
            var o = t[r] = {
                i: r,
                l: !1,
                exports: {}
            };
            return e[r].call(o.exports, o, o.exports, n),
            o.l = !0,
            o.exports
        }
        return n.m = e,
        n.c = t,
        n.d = function(e, t, r) {
            n.o(e, t) || Object.defineProperty(e, t, {
                enumerable: !0,
                get: r
            })
        }
        ,
        n.r = function(e) {
            "undefined" != typeof Symbol && Symbol.toStringTag && Object.defineProperty(e, Symbol.toStringTag, {
                value: "Module"
            }),
            Object.defineProperty(e, "__esModule", {
                value: !0
            })
        }
        ,
        n.t = function(e, t) {
            if (1 & t && (e = n(e)),
            8 & t)
                return e;
            if (4 & t && "object" == typeof e && e && e.__esModule)
                return e;
            var r = Object.create(null);
            if (n.r(r),
            Object.defineProperty(r, "default", {
                enumerable: !0,
                value: e
            }),
            2 & t && "string" != typeof e)
                for (var o in e)
                    n.d(r, o, function(t) {
                        return e[t]
                    }
                    .bind(null, o));
            return r
        }
        ,
        n.n = function(e) {
            var t = e && e.__esModule ? function() {
                return e.default
            }
            : function() {
                return e
            }
            ;
            return n.d(t, "a", t),
            t
        }
        ,
        n.o = function(e, t) {
            return Object.prototype.hasOwnProperty.call(e, t)
        }
        ,
        n.p = "/",
        n(n.s = 0)
    }([function(e, t, n) {
        function r(e, t) {
            for (var n = 0; n < t.length; n++) {
                var r = t[n];
                r.enumerable = r.enumerable || !1,
                r.configurable = !0,
                "value"in r && (r.writable = !0),
                Object.defineProperty(e, r.key, r)
            }
        }
        function o(e, t, n) {
            return t && r(e.prototype, t),
            n && r(e, n),
            e
        }
        n(1).toString();
        /**
 * Base Paragraph Block for the Editor.js.
 * Represents simple paragraph
 *
 * @author CodeX (team@codex.so)
 * @copyright CodeX 2018
 * @license The MIT License (MIT)
 */
        var a = function() {
            function e(t) {
                var n = t.data
                  , r = t.config
                  , o = t.api
                  , a = t.readOnly;
                !function(e, t) {
                    if (!(e instanceof t))
                        throw new TypeError("Cannot call a class as a function")
                }(this, e),
                this.api = o,
                //this.readOnly = true,
                this.readOnly = a,
                this._CSS = {
                    block: this.api.styles.block,
                    wrapper: "ce-fragment"
                },
                this.readOnly || (this.onKeyUp = this.onKeyUp.bind(this)),
                this._placeholder = r.placeholder ? r.placeholder : e.DEFAULT_PLACEHOLDER,
                this._data = {},
                this._element = this.drawView(),
                this._preserveBlank = void 0 !== r.preserveBlank && r.preserveBlank,
                this.data = n
            }
            return o(e, null, [{
                key: "DEFAULT_PLACEHOLDER",
                get: function() {
                    return ""
                }
            }]),
            o(e, [{
                key: "onKeyUp",
                value: function(e) {
                    "Backspace" !== e.code && "Delete" !== e.code || "" === this._element.textContent && (this._element.innerHTML = "")
                }
            }, {
                key: "drawView",
                value: function() {
                    var e = document.createElement("DIV");
                    return e.classList.add(this._CSS.wrapper, this._CSS.block),
                    e.contentEditable = !1,
                    e.dataset.placeholder = this.api.i18n.t(this._placeholder),
                    this.readOnly || (e.contentEditable = !0,
                    e.addEventListener("keyup", this.onKeyUp)),
                    e
                }
            }, {
                key: "render",
                value: function() {
                    return this._element
                }
            }, {
                key: "merge",
                value: function(e) {
                    var t = {
                        text: this.data.text + e.text
                    };
                    this.data = t
                }
            }, {
                key: "validate",
                value: function(e) {
                    return !("" === e.text.trim() && !this._preserveBlank)
                }
            }, {
                key: "save",
                value: function(e) {
                    return {
                        text: e.innerHTML
                    }
                }
            }, {
                key: "onPaste",
                value: function(e) {
                    var t = {
                        text: e.detail.data.innerHTML
                    };
                    this.data = t
                }
            }, {
                key: "data",
                get: function() {
                    var e = this._element.innerHTML;
                    return this._data.text = e,
                    this._data
                },
                set: function(e) {
                    this._data = e || {},
                    this._element.innerHTML = this._data.text || ""
                }
            }], [{
                key: "conversionConfig",
                get: function() {
                    return {
                        export: "text",
                        import: "text"
                    }
                }
            }, {
                key: "sanitize",
                get: function() {
                    return {
                        text: {
                            span: true,
                            br: !0
                        }
                    }
                }
            }, {
                key: "isReadOnlySupported",
                get: function() {
                    return !0
                }
            }, {
                key: "pasteConfig",
                get: function() {
                    return {
                        tags: ["P"]
                    }
                }
            }, {
                key: "toolbox",
                get: function() {
                    return {
                        icon: n(5).default,
                        title: "Text"
                    }
                }
            }]),
            e
        }();
        e.exports = a
    }
    , function(e, t, n) {
        var r = n(2)
          , o = n(3);
        "string" == typeof (o = o.__esModule ? o.default : o) && (o = [[e.i, o, ""]]);
        var a = {
            insert: "head",
            singleton: !1
        };
        r(o, a);
        e.exports = o.locals || {}
    }
    , function(e, t, n) {
        "use strict";
        var r, o = function() {
            return void 0 === r && (r = Boolean(window && document && document.all && !window.atob)),
            r
        }, a = function() {
            var e = {};
            return function(t) {
                if (void 0 === e[t]) {
                    var n = document.querySelector(t);
                    if (window.HTMLIFrameElement && n instanceof window.HTMLIFrameElement)
                        try {
                            n = n.contentDocument.head
                        } catch (e) {
                            n = null
                        }
                    e[t] = n
                }
                return e[t]
            }
        }(), i = [];
        function c(e) {
            for (var t = -1, n = 0; n < i.length; n++)
                if (i[n].identifier === e) {
                    t = n;
                    break
                }
            return t
        }
        function u(e, t) {
            for (var n = {}, r = [], o = 0; o < e.length; o++) {
                var a = e[o]
                  , u = t.base ? a[0] + t.base : a[0]
                  , s = n[u] || 0
                  , l = "".concat(u, " ").concat(s);
                n[u] = s + 1;
                var f = c(l)
                  , d = {
                    css: a[1],
                    media: a[2],
                    sourceMap: a[3]
                };
                -1 !== f ? (i[f].references++,
                i[f].updater(d)) : i.push({
                    identifier: l,
                    updater: y(d, t),
                    references: 1
                }),
                r.push(l)
            }
            return r
        }
        function s(e) {
            var t = document.createElement("style")
              , r = e.attributes || {};
            if (void 0 === r.nonce) {
                var o = n.nc;
                o && (r.nonce = o)
            }
            if (Object.keys(r).forEach((function(e) {
                t.setAttribute(e, r[e])
            }
            )),
            "function" == typeof e.insert)
                e.insert(t);
            else {
                var i = a(e.insert || "head");
                if (!i)
                    throw new Error("Couldn't find a style target. This probably means that the value for the 'insert' parameter is invalid.");
                i.appendChild(t)
            }
            return t
        }
        var l, f = (l = [],
        function(e, t) {
            return l[e] = t,
            l.filter(Boolean).join("\n")
        }
        );
        function d(e, t, n, r) {
            var o = n ? "" : r.media ? "@media ".concat(r.media, " {").concat(r.css, "}") : r.css;
            if (e.styleSheet)
                e.styleSheet.cssText = f(t, o);
            else {
                var a = document.createTextNode(o)
                  , i = e.childNodes;
                i[t] && e.removeChild(i[t]),
                i.length ? e.insertBefore(a, i[t]) : e.appendChild(a)
            }
        }
        function p(e, t, n) {
            var r = n.css
              , o = n.media
              , a = n.sourceMap;
            if (o ? e.setAttribute("media", o) : e.removeAttribute("media"),
            a && btoa && (r += "\n/*# sourceMappingURL=data:application/json;base64,".concat(btoa(unescape(encodeURIComponent(JSON.stringify(a)))), " */")),
            e.styleSheet)
                e.styleSheet.cssText = r;
            else {
                for (; e.firstChild; )
                    e.removeChild(e.firstChild);
                e.appendChild(document.createTextNode(r))
            }
        }
        var h = null
          , v = 0;
        function y(e, t) {
            var n, r, o;
            if (t.singleton) {
                var a = v++;
                n = h || (h = s(t)),
                r = d.bind(null, n, a, !1),
                o = d.bind(null, n, a, !0)
            } else
                n = s(t),
                r = p.bind(null, n, t),
                o = function() {
                    !function(e) {
                        if (null === e.parentNode)
                            return !1;
                        e.parentNode.removeChild(e)
                    }(n)
                }
                ;
            return r(e),
            function(t) {
                if (t) {
                    if (t.css === e.css && t.media === e.media && t.sourceMap === e.sourceMap)
                        return;
                    r(e = t)
                } else
                    o()
            }
        }
        e.exports = function(e, t) {
            (t = t || {}).singleton || "boolean" == typeof t.singleton || (t.singleton = o());
            var n = u(e = e || [], t);
            return function(e) {
                if (e = e || [],
                "[object Array]" === Object.prototype.toString.call(e)) {
                    for (var r = 0; r < n.length; r++) {
                        var o = c(n[r]);
                        i[o].references--
                    }
                    for (var a = u(e, t), s = 0; s < n.length; s++) {
                        var l = c(n[s]);
                        0 === i[l].references && (i[l].updater(),
                        i.splice(l, 1))
                    }
                    n = a
                }
            }
        }
    }
    , function(e, t, n) {
        (t = n(4)(!1)).push([e.i, ".ce-paragraph {\n    line-height: 1.6em;\n    outline: none;\n}\n\n.ce-paragraph[data-placeholder]:empty::before{\n  content: attr(data-placeholder);\n  color: #707684;\n  font-weight: normal;\n  opacity: 0;\n}\n\n/** Show placeholder at the first paragraph if Editor is empty */\n.codex-editor--empty .ce-block:first-child .ce-paragraph[data-placeholder]:empty::before {\n  opacity: 1;\n}\n\n.codex-editor--toolbox-opened .ce-block:first-child .ce-paragraph[data-placeholder]:empty::before,\n.codex-editor--empty .ce-block:first-child .ce-paragraph[data-placeholder]:empty:focus::before {\n  opacity: 0;\n}\n\n.ce-paragraph p:first-of-type{\n    margin-top: 0;\n}\n\n.ce-paragraph p:last-of-type{\n    margin-bottom: 0;\n}\n", ""]),
        e.exports = t
    }
    , function(e, t, n) {
        "use strict";
        e.exports = function(e) {
            var t = [];
            return t.toString = function() {
                return this.map((function(t) {
                    var n = function(e, t) {
                        var n = e[1] || ""
                          , r = e[3];
                        if (!r)
                            return n;
                        if (t && "function" == typeof btoa) {
                            var o = (i = r,
                            c = btoa(unescape(encodeURIComponent(JSON.stringify(i)))),
                            u = "sourceMappingURL=data:application/json;charset=utf-8;base64,".concat(c),
                            "/*# ".concat(u, " */"))
                              , a = r.sources.map((function(e) {
                                return "/*# sourceURL=".concat(r.sourceRoot || "").concat(e, " */")
                            }
                            ));
                            return [n].concat(a).concat([o]).join("\n")
                        }
                        var i, c, u;
                        return [n].join("\n")
                    }(t, e);
                    return t[2] ? "@media ".concat(t[2], " {").concat(n, "}") : n
                }
                )).join("")
            }
            ,
            t.i = function(e, n, r) {
                "string" == typeof e && (e = [[null, e, ""]]);
                var o = {};
                if (r)
                    for (var a = 0; a < this.length; a++) {
                        var i = this[a][0];
                        null != i && (o[i] = !0)
                    }
                for (var c = 0; c < e.length; c++) {
                    var u = [].concat(e[c]);
                    r && o[u[0]] || (n && (u[2] ? u[2] = "".concat(n, " and ").concat(u[2]) : u[2] = n),
                    t.push(u))
                }
            }
            ,
            t
        }
    }
    , function(e, t, n) {
        "use strict";
        n.r(t),
        t.default = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0.2 -0.3 9 11.4" width="12" height="14">\n  <path d="M0 2.77V.92A1 1 0 01.2.28C.35.1.56 0 .83 0h7.66c.28.01.48.1.63.28.14.17.21.38.21.64v1.85c0 .26-.08.48-.23.66-.15.17-.37.26-.66.26-.28 0-.5-.09-.64-.26a1 1 0 01-.21-.66V1.69H5.6v7.58h.5c.25 0 .45.08.6.23.17.16.25.35.25.6s-.08.45-.24.6a.87.87 0 01-.62.22H3.21a.87.87 0 01-.61-.22.78.78 0 01-.24-.6c0-.25.08-.44.24-.6a.85.85 0 01.61-.23h.5V1.7H1.73v1.08c0 .26-.08.48-.23.66-.15.17-.37.26-.66.26-.28 0-.5-.09-.64-.26A1 1 0 010 2.77z"/>\n</svg>\n'
    }
    ])
}
));

