/**
 * ML CSS Pro — Visual Editor Overlay v1.5.4
 * Own implementation inspired by the visual-editing workflow: hover, select,
 * edit in a right panel, preview live, save as CSS override.
 */
(function () {
  'use strict';

  var C = window.mlcsspVisual || {};
  if (!C.ajaxUrl || !C.nonce) return;

  var selected = null;
  var selectedInfo = null;
  var outline = null;
  var panel = null;
  var toolbar = null;
  var breadcrumb = null;
  var dirty = {};
  var currentDevice = 'desktop'; /* desktop|tablet|mobile */
  var originalBodyLayout = null;
  var pageScope = 'global'; /* global|page */
  var PAGE = (window.mlcsspVisual && window.mlcsspVisual.pageContext) || { title: '', url: '', page_key: '', body_class: '' };
  var savedOverrides = {}; /* selector → { prop: value } — persisted after successful save */
  var originalStyle = undefined;
  var originalStyles = [];
  var isPreviewing = false;

  var CONTROL_MAP = {
    // ── Text ──
    'font-family': { label: 'Fonte', section: 'text', type: 'font' },
    'color': { label: 'Cor', section: 'text', type: 'color' },
    'font-size': { label: 'Tamanho', section: 'text', type: 'size', unit: 'px', min: 8, max: 140 },
    'font-weight': { label: 'Peso', section: 'text', type: 'select', options: ['100','200','300','400','500','600','700','800','900','normal','bold'] },
    'line-height': { label: 'Altura da linha', section: 'text', type: 'size', unit: 'em', min: 0.7, max: 3, step: 0.01 },
    'font-style': { label: 'Estilo', section: 'text', type: 'segmented', options: [{v:'normal',t:'normal'},{v:'italic',t:'italic'}] },
    'text-transform': { label: 'Transformar', section: 'text', type: 'segmented', options: [{v:'none',t:'no'},{v:'uppercase',t:'AA'},{v:'capitalize',t:'Aa'},{v:'lowercase',t:'aa'}] },
    'text-decoration': { label: 'Decoracao', section: 'text', type: 'segmented', options: [{v:'none',t:'A'},{v:'underline',t:'A\u0332'},{v:'line-through',t:'A\u0336'}] },
    'text-align': { label: 'Alinhamento', section: 'text', type: 'segmented', options: [{v:'left',t:'\u2630'},{v:'center',t:'\u2261'},{v:'right',t:'\u2637'},{v:'justify',t:'\u25a4'}] },
    'letter-spacing': { label: 'Espaco letras', section: 'text', type: 'size', unit: 'px', min: -5, max: 30, step: 0.1 },
    'word-spacing': { label: 'Espaco palavras', section: 'text', type: 'size', unit: 'px', min: -5, max: 60, step: 0.5 },
    'text-shadow': { label: 'Sombra de texto', section: 'text', type: 'text', ph: 'ex: 1px 1px 2px rgba(0,0,0,.3)' },

    // ── Background ──
    'background-color': { label: 'Cor de fundo', section: 'background', type: 'color', allowTransparent: true },
    'background-image': { label: 'Imagem/gradiente', section: 'background', type: 'text', ph: 'url(...) ou linear-gradient(...)' },
    'background-size': { label: 'Tamanho', section: 'background', type: 'select', options: ['auto','cover','contain','100% auto','auto 100%'] },
    'background-position': { label: 'Posicao', section: 'background', type: 'select', options: ['center','top','bottom','left','right','center top','center bottom'] },
    'background-repeat': { label: 'Repetir', section: 'background', type: 'segmented', options: [{v:'repeat',t:'tile'},{v:'no-repeat',t:'no'},{v:'repeat-x',t:'X'},{v:'repeat-y',t:'Y'}] },
    'background-attachment': { label: 'Fixo', section: 'background', type: 'segmented', options: [{v:'scroll',t:'scroll'},{v:'fixed',t:'fixed'}] },

    // ── Spacing ──
    'padding-top': { label: 'Padding topo', section: 'spacing', type: 'size', unit: 'px', min: 0, max: 240 },
    'padding-right': { label: 'Padding direita', section: 'spacing', type: 'size', unit: 'px', min: 0, max: 240 },
    'padding-bottom': { label: 'Padding base', section: 'spacing', type: 'size', unit: 'px', min: 0, max: 240 },
    'padding-left': { label: 'Padding esquerda', section: 'spacing', type: 'size', unit: 'px', min: 0, max: 240 },
    'margin-top': { label: 'Margem topo', section: 'spacing', type: 'size', unit: 'px', min: -120, max: 240 },
    'margin-right': { label: 'Margem direita', section: 'spacing', type: 'text', ph: 'ex: auto' },
    'margin-bottom': { label: 'Margem base', section: 'spacing', type: 'size', unit: 'px', min: -120, max: 240 },
    'margin-left': { label: 'Margem esquerda', section: 'spacing', type: 'text', ph: 'ex: auto' },

    // ── Border ──
    'border-color': { label: 'Cor da borda', section: 'border', type: 'color' },
    'border-width': { label: 'Espessura geral', section: 'border', type: 'size', unit: 'px', min: 0, max: 40 },
    'border-style': { label: 'Estilo', section: 'border', type: 'select', options: ['none','solid','dashed','dotted','double','groove','ridge'] },
    'border-top-width': { label: 'Borda topo', section: 'border', type: 'size', unit: 'px', min: 0, max: 40 },
    'border-right-width': { label: 'Borda direita', section: 'border', type: 'size', unit: 'px', min: 0, max: 40 },
    'border-bottom-width': { label: 'Borda base', section: 'border', type: 'size', unit: 'px', min: 0, max: 40 },
    'border-left-width': { label: 'Borda esquerda', section: 'border', type: 'size', unit: 'px', min: 0, max: 40 },

    // ── Border Radius ──
    'border-radius': { label: 'Todos os cantos', section: 'radius', type: 'size', unit: 'px', min: 0, max: 120 },
    'border-top-left-radius': { label: 'Superior esq.', section: 'radius', type: 'size', unit: 'px', min: 0, max: 120 },
    'border-top-right-radius': { label: 'Superior dir.', section: 'radius', type: 'size', unit: 'px', min: 0, max: 120 },
    'border-bottom-right-radius': { label: 'Inferior dir.', section: 'radius', type: 'size', unit: 'px', min: 0, max: 120 },
    'border-bottom-left-radius': { label: 'Inferior esq.', section: 'radius', type: 'size', unit: 'px', min: 0, max: 120 },

    // ── Size ──
    'width': { label: 'Largura', section: 'size', type: 'text', ph: 'ex: 100%, 300px, auto' },
    'max-width': { label: 'Largura maxima', section: 'size', type: 'text', ph: 'ex: 1200px, 100%' },
    'min-width': { label: 'Largura minima', section: 'size', type: 'text', ph: 'ex: 200px' },
    'height': { label: 'Altura', section: 'size', type: 'text', ph: 'ex: auto, 400px' },
    'max-height': { label: 'Altura maxima', section: 'size', type: 'text', ph: 'ex: 600px, none' },
    'min-height': { label: 'Altura minima', section: 'size', type: 'text', ph: 'ex: 300px' },

    // ── Position ──
    'position': { label: 'Posicao', section: 'position', type: 'select', options: ['static','relative','absolute','fixed','sticky'] },
    'top': { label: 'Top', section: 'position', type: 'text', ph: 'ex: 0, 10px, auto' },
    'right': { label: 'Right', section: 'position', type: 'text', ph: 'ex: 0, auto' },
    'bottom': { label: 'Bottom', section: 'position', type: 'text', ph: 'ex: 0, auto' },
    'left': { label: 'Left', section: 'position', type: 'text', ph: 'ex: 0, auto' },
    'z-index': { label: 'Z-index', section: 'position', type: 'size', unit: '', min: -100, max: 9999, step: 1 },

    // ── Shadow ──
    'box-shadow': { label: 'Box shadow', section: 'shadow', type: 'text', ph: 'ex: 0 4px 12px rgba(0,0,0,.15)' },

    // ── Transform ──
    'transform': { label: 'Transform', section: 'transform', type: 'text', ph: 'ex: scale(1.05) rotate(2deg)' },

    // ── Filter ──
    'opacity': { label: 'Opacidade', section: 'filter', type: 'size', unit: '', min: 0, max: 1, step: 0.01 },
    'filter': { label: 'Filtro CSS', section: 'filter', type: 'text', ph: 'ex: blur(2px) brightness(1.1)' },

    // ── Visibility ──
    'display': { label: 'Display', section: 'visibility', type: 'select', options: ['block','inline','inline-block','flex','grid','none','inherit'] },
    'visibility': { label: 'Visibility', section: 'visibility', type: 'select', options: ['visible','hidden','collapse'] },
    'overflow': { label: 'Overflow', section: 'visibility', type: 'select', options: ['visible','hidden','auto','scroll'] },
    'cursor': { label: 'Cursor', section: 'visibility', type: 'select', options: ['auto','pointer','default','move','text','not-allowed','crosshair'] },
    'pointer-events': { label: 'Pointer events', section: 'visibility', type: 'select', options: ['auto','none'] }
  };

  var SECTIONS = [
    { key: 'text', title: 'Texto', icon: 'A' },
    { key: 'background', title: 'Fundo', icon: '\u25a7' },
    { key: 'spacing', title: 'Espacamento', icon: '\u25a1' },
    { key: 'border', title: 'Bordas', icon: '\u254b' },
    { key: 'radius', title: 'Arredondamento', icon: '\u25dc' },
    { key: 'size', title: 'Tamanho', icon: '\u2194' },
    { key: 'position', title: 'Posicao', icon: '\u2316' },
    { key: 'shadow', title: 'Sombra', icon: '\u25d2' },
    { key: 'transform', title: 'Transformar', icon: '\u27f2' },
    { key: 'filter', title: 'Filtros', icon: '\u25d0' },
    { key: 'visibility', title: 'Visibilidade', icon: '\u25c9' },
    { key: 'extra', title: 'Avancado', icon: '\u25c7' }
  ];

  var FONTS = ['inherit','Arial, sans-serif','Helvetica, Arial, sans-serif','Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif','Georgia, serif','Times New Roman, serif','Verdana, sans-serif','Tahoma, sans-serif','Trebuchet MS, sans-serif','Courier New, monospace'];

  function safeCssEscape(value) {
    if (window.CSS && CSS.escape) return CSS.escape(value);
    return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function isEditorChrome(el) {
    return !!(el && (el.closest('.mlcssp-ve-panel') || el.closest('.mlcssp-ve-toolbar') || el.closest('.mlcssp-ve-breadcrumb') || el.closest('.mlcssp-ve-outline') || el.closest('.mlcssp-ve-admin-bar')));
  }

  function pointTarget(evt) {
    if (!evt || typeof evt.clientX !== 'number' || typeof evt.clientY !== 'number') return null;
    var hidden = [];
    ['.mlcssp-ve-outline', '.mlcssp-ve-breadcrumb'].forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (node) {
        hidden.push({ node: node, display: node.style.display, pointerEvents: node.style.pointerEvents });
        node.style.pointerEvents = 'none';
      });
    });
    var el = document.elementFromPoint(evt.clientX, evt.clientY);
    hidden.forEach(function (item) {
      item.node.style.display = item.display;
      item.node.style.pointerEvents = item.pointerEvents;
    });
    return el;
  }

  function meaningfulTarget(el) {
    if (!el || el === document.documentElement) return document.body;
    if (isEditorChrome(el)) return null;

    /* Prefer the closest real editable element, never the editor chrome. */
    var direct = el;
    if (direct && direct.nodeType === 3) direct = direct.parentElement;
    if (!direct || direct.nodeType !== 1) return null;

    /* Text/interactive elements must win over broad wrappers. */
    var textTarget = direct.closest('input,textarea,select,button,a,label,h1,h2,h3,h4,h5,h6,p,li,blockquote,figcaption,span,strong,em,small');
    if (textTarget && !isEditorChrome(textTarget)) {
      var normalized = textTarget.closest('h1,h2,h3,h4,h5,h6,button,a,input,textarea,select,label,p,li,blockquote,figcaption');
      return normalized || textTarget;
    }

    /* Visual blocks second. */
    var block = direct.closest('[class*="card"],[class*="box"],[class*="item"],[class*="container"],[class*="panel"],[class*="grid"],[class*="form"],form,nav,header,footer,section,article,aside,main,table,img,[class*="u-section"],[class*="u-sheet"],[class*="u-text"],[class*="u-btn"],[class*="u-menu"],[class*="u-nav"]');
    if (block && !isEditorChrome(block)) return block;

    return direct === document.documentElement ? document.body : direct;
  }

  function selectorPrecise(el) {
    if (!el || el === document.body) return 'body';
    if (el.id) return '#' + safeCssEscape(el.id);
    var path = [];
    var cur = el;
    while (cur && cur.nodeType === 1 && cur !== document.body && path.length < 5) {
      var tag = cur.tagName.toLowerCase();
      var classes = Array.from(cur.classList || []).filter(function (c) { return c && c.indexOf('mlcssp-') !== 0; });
      var part = tag;
      if (classes.length) {
        part += '.' + classes.slice(0, 2).map(safeCssEscape).join('.');
      }
      var parent = cur.parentElement;
      if (parent) {
        var siblings = Array.from(parent.children).filter(function (n) { return n.tagName === cur.tagName; });
        if (siblings.length > 1 && !classes.length) {
          part += ':nth-of-type(' + (siblings.indexOf(cur) + 1) + ')';
        }
      }
      path.unshift(part);
      if (classes.length || tag.match(/^h[1-6]$|^(a|button|nav|header|footer|section|form)$/)) break;
      cur = parent;
    }
    return path.join(' > ') || el.tagName.toLowerCase();
  }

  function selectorGroup(el) {
    var tag = el.tagName.toLowerCase();
    var cls = Array.from(el.classList || []).filter(function (c) { return c && c.indexOf('mlcssp-') !== 0; });
    /* Priority class (Nicepage/WP component) */
    var priority = cls.find(function (c) { return /^(u-btn|u-text|u-section|u-header|u-footer|u-menu|u-nav|button|btn|card|container|box|item)/i.test(c); });
    if (priority) return '.' + safeCssEscape(priority);
    if (el.id) return '#' + safeCssEscape(el.id);
    /* For generic tags (h1-h6, p, a, button) add parent class context for specificity */
    var parent = el.parentElement;
    var parentSel = '';
    if (parent && parent !== document.body && parent !== document.documentElement) {
      var pCls = Array.from(parent.classList || []).filter(function (c) { return c && c.indexOf('mlcssp-') !== 0; });
      if (pCls.length) {
        parentSel = '.' + safeCssEscape(pCls[0]) + ' ';
      } else if (parent.id) {
        parentSel = '#' + safeCssEscape(parent.id) + ' ';
      } else {
        var gp = parent.parentElement;
        if (gp && gp !== document.body) {
          var gpCls = Array.from(gp.classList || []).filter(function (c) { return c && c.indexOf('mlcssp-') !== 0; });
          if (gpCls.length) parentSel = '.' + safeCssEscape(gpCls[0]) + ' ';
        }
      }
    }
    if (/^h[1-6]$/.test(tag)) return parentSel + tag;
    if (tag === 'a') return parentSel + (cls.includes('u-btn') ? '.u-btn' : 'a');
    if (tag === 'button') return parentSel + (cls.length ? '.' + cls[0] : 'button');
    if (cls.length) return parentSel + tag + '.' + safeCssEscape(cls[0]);
    return parentSel + tag;
  }

  function friendly(el) {
    var tag = el.tagName.toLowerCase();
    var text = (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 32);
    if (/^h[1-6]$/.test(tag)) return 'Título ' + tag.toUpperCase();
    if (tag === 'p') return 'Texto comum';
    if (tag === 'a') return el.className && String(el.className).indexOf('btn') >= 0 ? 'Botão/link' : 'Link';
    if (tag === 'button') return 'Botão';
    if (tag === 'nav') return 'Menu';
    if (tag === 'header') return 'Cabeçalho';
    if (tag === 'footer') return 'Rodapé';
    if (tag === 'section') return 'Seção';
    if (tag === 'form') return 'Formulário';
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return 'Campo de formulário';
    if (tag === 'img') return 'Imagem';
    if (String(el.className || '').match(/card|box|item/i)) return 'Card/bloco';
    return text ? 'Bloco: ' + text : 'Bloco visual';
  }

  function countMatches(sel) {
    try { return document.querySelectorAll(sel).length; } catch (e) { return 1; }
  }

  function targetElements() {
    if (!selected) return [];
    var sel = currentSelector();
    var scope = panel ? panel.querySelector('.mlcssp-ve-scope-select').value : 'precise';
    if (scope !== 'group') return [selected];
    try {
      var matches = Array.from(document.querySelectorAll(sel)).filter(function (el) {
        return el && !isEditorChrome(el);
      });
      return matches.length ? matches : [selected];
    } catch (e) {
      return [selected];
    }
  }

  function rememberOriginalStyles() {
    originalStyles = targetElements().map(function (el) {
      return { el: el, style: el.getAttribute('style') };
    });
  }

  function applyStylesToTargets(styles) {
    targetElements().forEach(function (el) {
      Object.keys(styles).forEach(function (prop) {
        el.style.setProperty(prop, styles[prop], 'important');
      });
    });
  }

  function updateScopeMeta() {
    if (!selectedInfo || !panel) return;
    var sel = currentSelector();
    var count = countMatches(sel);
    panel.querySelector('.mlcssp-ve-selector').textContent = sel;
    panel.querySelector('.mlcssp-ve-selected small').textContent = count + ' elemento(s) afetado(s) por este escopo';
  }

  function rgbToHex(value) {
    if (!value) return '';
    if (value === 'transparent' || value === 'rgba(0, 0, 0, 0)' || value === 'rgba(0,0,0,0)') return '';
    if (value.charAt(0) === '#') return value.length > 7 ? value.substring(0, 7) : value;
    var m = value.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!m) return '';
    return '#' + [m[1], m[2], m[3]].map(function (n) { return ('0' + parseInt(n, 10).toString(16)).slice(-2); }).join('');
  }

  function unitValue(raw, fallbackUnit) {
    raw = String(raw || '').trim();
    var m = raw.match(/^(-?[0-9.]+)([a-z%]*)$/i);
    if (!m) return { value: '', unit: fallbackUnit || 'px' };
    return { value: m[1], unit: m[2] || fallbackUnit || 'px' };
  }

  function captureBodyLayout() {
    if (originalBodyLayout) return;
    var st = document.body.style;
    originalBodyLayout = {
      width: st.getPropertyValue('width'), widthPriority: st.getPropertyPriority('width'),
      maxWidth: st.getPropertyValue('max-width'), maxWidthPriority: st.getPropertyPriority('max-width'),
      minWidth: st.getPropertyValue('min-width'), minWidthPriority: st.getPropertyPriority('min-width'),
      marginLeft: st.getPropertyValue('margin-left'), marginLeftPriority: st.getPropertyPriority('margin-left'),
      marginRight: st.getPropertyValue('margin-right'), marginRightPriority: st.getPropertyPriority('margin-right'),
      boxShadow: st.getPropertyValue('box-shadow'), boxShadowPriority: st.getPropertyPriority('box-shadow'),
      outline: st.getPropertyValue('outline'), outlinePriority: st.getPropertyPriority('outline'),
      transition: st.getPropertyValue('transition'), transitionPriority: st.getPropertyPriority('transition')
    };
  }

  function restoreBodyLayout() {
    if (!originalBodyLayout) return;
    var st = document.body.style;
    st.setProperty('width', originalBodyLayout.width || '', originalBodyLayout.widthPriority || '');
    st.setProperty('max-width', originalBodyLayout.maxWidth || '', originalBodyLayout.maxWidthPriority || '');
    st.setProperty('min-width', originalBodyLayout.minWidth || '', originalBodyLayout.minWidthPriority || '');
    st.setProperty('margin-left', originalBodyLayout.marginLeft || '', originalBodyLayout.marginLeftPriority || '');
    st.setProperty('margin-right', originalBodyLayout.marginRight || '', originalBodyLayout.marginRightPriority || '');
    st.setProperty('box-shadow', originalBodyLayout.boxShadow || '', originalBodyLayout.boxShadowPriority || '');
    st.setProperty('outline', originalBodyLayout.outline || '', originalBodyLayout.outlinePriority || '');
    st.setProperty('transition', originalBodyLayout.transition || '', originalBodyLayout.transitionPriority || '');
    document.documentElement.removeAttribute('data-mlcssp-device-width');
  }

  function applyResponsiveViewport() {
    document.documentElement.setAttribute('data-mlcssp-device', currentDevice);
    if (currentDevice === 'desktop') {
      restoreBodyLayout();
      return;
    }
    captureBodyLayout();
    var desired = currentDevice === 'tablet' ? 768 : 375;
    var leftW = toolbar ? Math.ceil(toolbar.getBoundingClientRect().width) : 42;
    var panelRect = panel ? panel.getBoundingClientRect() : { width: 360 };
    var rightW = Math.ceil(panelRect.width) + 28;
    var gutter = 18;
    var available = Math.max(260, window.innerWidth - leftW - rightW - (gutter * 2));
    var frameW = Math.min(desired, available);
    var left = leftW + gutter + Math.max(0, Math.floor((available - frameW) / 2));
    var st = document.body.style;
    st.setProperty('transition', 'width .18s ease, margin-left .18s ease, box-shadow .18s ease', 'important');
    st.setProperty('width', frameW + 'px', 'important');
    st.setProperty('max-width', frameW + 'px', 'important');
    st.setProperty('min-width', '0', 'important');
    st.setProperty('margin-left', left + 'px', 'important');
    st.setProperty('margin-right', rightW + 'px', 'important');
    st.setProperty('box-shadow', '0 0 0 1px rgba(21,94,111,.25), 0 18px 45px rgba(15,23,42,.18)', 'important');
    st.setProperty('outline', '1px solid rgba(21,94,111,.25)', 'important');
    document.documentElement.setAttribute('data-mlcssp-device-width', String(frameW));
    if (outline && selected) moveOutline(selected);
  }

  function createOutline() {
    outline = document.createElement('div');
    outline.className = 'mlcssp-ve-outline';
    outline.innerHTML = '<span></span>';
    document.body.appendChild(outline);
  }

  function moveOutline(el) {
    if (!el || isEditorChrome(el)) { outline.style.display = 'none'; return; }
    var target = meaningfulTarget(el);
    if (!target) { outline.style.display = 'none'; return; }
    var r = target.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) { outline.style.display = 'none'; return; }
    outline.style.display = 'block';
    outline.style.top = (r.top + window.scrollY) + 'px';
    outline.style.left = (r.left + window.scrollX) + 'px';
    outline.style.width = r.width + 'px';
    outline.style.height = r.height + 'px';
    outline.querySelector('span').textContent = friendly(target);
  }

  function createToolbar() {
    toolbar = document.createElement('div');
    toolbar.className = 'mlcssp-ve-toolbar';
    toolbar.innerHTML = [
      '<button title="Selecionar" class="is-active">↖</button>',
      '<button title="Camadas">▱</button>',
      '<button title="CSS">&lt;/&gt;</button>',
      '<button title="Layout">▤</button>',
      '<button title="Medidas">⌞</button>',
      '<button title="Grade">▦</button>',
      '<button title="Prévia">▷</button>',
      '<button title="Desfazer">↶</button>',
      '<button title="Refazer">↷</button>'
    ].join('');
    document.body.appendChild(toolbar);
  }

  function createBreadcrumb() {
    breadcrumb = document.createElement('div');
    breadcrumb.className = 'mlcssp-ve-breadcrumb';
    document.body.appendChild(breadcrumb);
  }

  function updateBreadcrumb(el) {
    var parts = [];
    var cur = el;
    while (cur && cur !== document.body && parts.length < 6) {
      parts.unshift(cur.tagName.toLowerCase() + (cur.classList && cur.classList.length ? '.' + Array.from(cur.classList).filter(function(c){return c.indexOf('mlcssp-') !== 0;}).slice(0,1).join('') : ''));
      cur = cur.parentElement;
    }
    parts.unshift('Body');
    breadcrumb.innerHTML = parts.map(function (p, i) { return '<span' + (i === parts.length - 1 ? ' class="is-current"' : '') + '>' + escapeHtml(p) + '</span>'; }).join('');
  }

  function createPanel() {
    panel = document.createElement('aside');
    panel.className = 'mlcssp-ve-panel is-empty';
    panel.innerHTML = '' +
      '<div class="mlcssp-ve-top"><button type="button" class="mlcssp-ve-close" title="Fechar">×</button><button type="button" title="Responsivo">▢</button><button type="button" title="Ajustes">☷</button><strong class="mlcssp-ve-save-state">Saved</strong></div>' +
      '<div class="mlcssp-ve-page"><span>▾ Homepage</span></div>' +
      '<div class="mlcssp-ve-selected"><b>Selecione um elemento</b><small>Passe o mouse e clique no site para editar visualmente.</small></div>' +
      '<div class="mlcssp-ve-devices"><button type="button" class="mlcssp-ve-device is-active" data-device="desktop" title="Desktop 1200px">🖥 Desktop</button><button type="button" class="mlcssp-ve-device" data-device="tablet" title="Tablet 768px">▭ Tablet</button><button type="button" class="mlcssp-ve-device" data-device="mobile" title="Mobile 375px">▯ Mobile</button></div>' +
      '<div class="mlcssp-ve-device-label">Editando: <strong class="mlcssp-ve-device-name">Desktop</strong></div>' +
      '<div class="mlcssp-ve-page-label">Pagina: <strong class="mlcssp-ve-page-name">' + (PAGE.title || PAGE.url || 'atual') + '</strong></div>' +
      '<div class="mlcssp-ve-scope"><label>Salvar para</label><select class="mlcssp-ve-pagescope-select"><option value="global">Site inteiro</option><option value="page">Apenas esta pagina</option></select></div>' +
      '<div class="mlcssp-ve-scope-hint mlcssp-ve-pagescope-hint">Esta alteracao sera aplicada em todo o site quando o seletor existir.</div>' +
      '<div class="mlcssp-ve-scope"><label>Aplicar em</label><select class="mlcssp-ve-scope-select"><option value="precise">Apenas este elemento</option><option value="group">Grupo similar</option></select></div>' +
      '<details class="mlcssp-ve-tech"><summary>Detalhes técnicos</summary><code class="mlcssp-ve-selector"></code><button type="button" class="mlcssp-ve-copy">Copiar seletor</button></details>' +
      '<div class="mlcssp-ve-sections"></div>' +
      '<div class="mlcssp-ve-actions"><button type="button" class="mlcssp-ve-preview">Pre-visualizar</button><button type="button" class="mlcssp-ve-save">Salvar alteracao</button></div>' +
      '<div class="mlcssp-ve-actions mlcssp-ve-actions-secondary"><button type="button" class="mlcssp-ve-undo">Desfazer preview</button><button type="button" class="mlcssp-ve-reset-prop">Resetar propriedade</button><button type="button" class="mlcssp-ve-reset-all">Resetar seletor</button></div>' +
      '<div class="mlcssp-ve-status"></div>' +
      '<div class="mlcssp-ve-footer">ML CSS Pro Visual • v1.5.4</div>';
    document.body.appendChild(panel);

    panel.querySelector('.mlcssp-ve-close').addEventListener('click', function () { window.location.href = removeQueryFlag(); });
    panel.querySelector('.mlcssp-ve-preview').addEventListener('click', previewDirty);
    panel.querySelector('.mlcssp-ve-save').addEventListener('click', saveDirty);
    panel.querySelector('.mlcssp-ve-undo').addEventListener('click', undoPreview);
    panel.querySelector('.mlcssp-ve-reset-prop').addEventListener('click', resetLastProperty);
    panel.querySelector('.mlcssp-ve-reset-all').addEventListener('click', resetSelector);
    panel.querySelector('.mlcssp-ve-copy').addEventListener('click', function () {
      var sel = currentSelector();
      navigator.clipboard && navigator.clipboard.writeText(sel);
      setStatus('Seletor copiado.', 'ok');
    });
    panel.querySelector('.mlcssp-ve-scope-select').addEventListener('change', function () {
      if (!selectedInfo) return;
      if (isPreviewing) restorePreview();
      updateScopeMeta();
      rememberOriginalStyles();
      if (Object.keys(dirty).length) livePreview();
    });
    panel.querySelector('.mlcssp-ve-pagescope-select').addEventListener('change', function () {
      pageScope = this.value === 'page' ? 'page' : 'global';
      var hint = panel.querySelector('.mlcssp-ve-pagescope-hint');
      if (pageScope === 'page') {
        if (!PAGE.body_class) {
          hint.textContent = 'Sem classe de pagina detectada. Use Site inteiro nesta pagina.';
          hint.style.color = '#b45309';
        } else {
          hint.textContent = 'Esta alteracao sera aplicada somente em: ' + (PAGE.title || PAGE.url);
          hint.style.color = '#155e6f';
        }
      } else {
        hint.textContent = 'Esta alteracao sera aplicada em todo o site quando o seletor existir.';
        hint.style.color = '#6b7a8d';
      }
    });
    panel.querySelectorAll('.mlcssp-ve-device').forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentDevice = btn.getAttribute('data-device') || 'desktop';
        panel.querySelectorAll('.mlcssp-ve-device').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        var names = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };
        var widths = { desktop: '1200px', tablet: '768px', mobile: '375px' };
        panel.querySelector('.mlcssp-ve-device-name').textContent = names[currentDevice];
        applyResponsiveViewport();
        if (isPreviewing) restorePreview();
        if (selected) {
          fillControlsFromComputed(selected);
          moveOutline(selected);
        }
        setStatus('Modo ' + names[currentDevice] + ' (' + widths[currentDevice] + ') ativo. A visualizacao fica centralizada fora da barra lateral.', 'info');
      });
    });
    SECTIONS.forEach(function (s, idx) {
      var block = document.createElement('section');
      block.className = 'mlcssp-ve-section' + (s.key === 'text' ? ' is-open' : '');
      block.setAttribute('data-section', s.key);
      block.innerHTML = '<button type="button" class="mlcssp-ve-section-title"><span>' + s.icon + '</span>' + s.title + '<b>⌄</b></button><div class="mlcssp-ve-section-body"></div>';
      block.querySelector('.mlcssp-ve-section-title').addEventListener('click', function () { block.classList.toggle('is-open'); });
      var wrap = panel.querySelector('.mlcssp-ve-sections');
      if (wrap) wrap.appendChild(block);
    });
    buildControls();
  }

  function buildControls() {
    Object.keys(CONTROL_MAP).forEach(function (prop) {
      var cfg = CONTROL_MAP[prop];
      var body = panel.querySelector('[data-section="' + cfg.section + '"] .mlcssp-ve-section-body');
      if (!body) body = panel.querySelector('[data-section="extra"] .mlcssp-ve-section-body');
      var row = document.createElement('div');
      row.className = 'mlcssp-ve-control';
      row.setAttribute('data-prop', prop);
      row.innerHTML = '<label>' + cfg.label + '</label>' + controlHtml(prop, cfg);
      body.appendChild(row);
    });

    panel.querySelectorAll('[data-prop] input,[data-prop] select').forEach(function (input) {
      input.addEventListener('input', function () { markDirty(input.closest('[data-prop]').getAttribute('data-prop')); livePreview(); });
      input.addEventListener('change', function () { markDirty(input.closest('[data-prop]').getAttribute('data-prop')); livePreview(); });
    });

    panel.querySelectorAll('.mlcssp-ve-segment button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('[data-prop]');
        row.querySelectorAll('button').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        row.querySelector('input[type="hidden"]').value = btn.getAttribute('data-value');
        markDirty(row.getAttribute('data-prop'));
        livePreview();
      });
    });
  }

  function controlHtml(prop, cfg) {
    if (cfg.type === 'font') {
      return '<select data-value="1">' + FONTS.map(function (f) { return '<option value="' + escapeAttr(f) + '">' + escapeHtml(f.split(',')[0]) + '</option>'; }).join('') + '</select>';
    }
    if (cfg.type === 'select') {
      return '<select data-value="1">' + cfg.options.map(function (o) { return '<option value="' + escapeAttr(o) + '">' + escapeHtml(o) + '</option>'; }).join('') + '</select>';
    }
    if (cfg.type === 'color') {
      return '<div class="mlcssp-ve-colorline"><input type="color" data-value="1"><input type="text" data-text="1" placeholder="transparente"><button type="button" class="mlcssp-ve-clear-color">×</button></div>';
    }
    if (cfg.type === 'size') {
      var step = cfg.step || 1;
      return '<div class="mlcssp-ve-rangeline"><input type="range" data-range="1" min="' + cfg.min + '" max="' + cfg.max + '" step="' + step + '"><input type="number" data-number="1" step="' + step + '"><span>' + escapeHtml(cfg.unit || '') + '</span><input type="hidden" data-value="1" data-unit="' + escapeAttr(cfg.unit || '') + '"></div>';
    }
    if (cfg.type === 'segmented') {
      return '<div class="mlcssp-ve-segment"><input type="hidden" data-value="1">' + cfg.options.map(function (o) { return '<button type="button" data-value="' + escapeAttr(o.v) + '">' + escapeHtml(o.t) + '</button>'; }).join('') + '</div>';
    }
    return '<input type="text" data-value="1">';
  }

  function openPanel(el) {
    /* Remove previous selected indicator */
    if (selected) selected.classList.remove('mlcssp-ve-element-selected');
    selected = meaningfulTarget(el);
    if (!selected) return;
    /* Add persistent selected indicator — visible even when hovering panel */
    selected.classList.add('mlcssp-ve-element-selected');
    selectedInfo = {
      precise: selectorPrecise(selected),
      group: selectorGroup(selected),
      label: friendly(selected)
    };
    dirty = {};
    originalStyle = selected.getAttribute('style');
    isPreviewing = false;
    updateBreadcrumb(selected);
    panel.classList.remove('is-empty');
    panel.querySelector('.mlcssp-ve-selected').innerHTML = '<b>' + escapeHtml(selectedInfo.label) + '</b><small></small>';
    panel.querySelector('.mlcssp-ve-selector').textContent = currentSelector();
    updateScopeMeta();
    rememberOriginalStyles();
    panel.querySelector('.mlcssp-ve-save-state').textContent = 'Saved';
    panel.querySelector('.mlcssp-ve-save-state').classList.remove('is-dirty');
    fillControlsFromComputed(selected);
    setStatus('Elemento selecionado. Ajuste os controles à direita.', 'info');
    panel.classList.add('is-open');
  }

  function fillControlsFromComputed(el) {
    var cs = window.getComputedStyle(el);
    /* Check if we have saved overrides for the current selector */
    var sel = currentSelector();
    var overridesForSel;
    if (currentDevice === 'desktop') {
      overridesForSel = savedOverrides[sel] || (C.overrides && C.overrides[sel]) || {};
    } else {
      /* Device-specific: read from C.responsive[device][sel] or local saved */
      var devSaved = (C.responsive && C.responsive[currentDevice] && C.responsive[currentDevice][sel]) || {};
      overridesForSel = devSaved;
    }
    Object.keys(CONTROL_MAP).forEach(function (prop) {
      var row = panel.querySelector('[data-prop="' + prop + '"]');
      if (!row) return;
      row.classList.remove('is-dirty');
      var cfg = CONTROL_MAP[prop];
      /* Use saved override value if it exists; otherwise use computed style */
      var raw = overridesForSel[prop] || cs.getPropertyValue(prop) || '';
      var hasSavedOverride = !!overridesForSel[prop];
      var valueInput = row.querySelector('[data-value]');
      if (cfg.type === 'color') {
        var hex = rgbToHex(raw);
        var color = row.querySelector('input[type="color"]');
        var text = row.querySelector('[data-text]');
        color.value = hex || '#ffffff';
        text.value = hex || 'transparent';
        /* CRITICAL: sync color picker → text field (was missing, causing saves to send old value) */
        color.oninput = color.onchange = function () {
          text.value = color.value;
          markDirty(prop);
          livePreview();
        };
        row.querySelector('.mlcssp-ve-clear-color').onclick = function () {
          text.value = 'transparent';
          markDirty(prop);
          livePreview();
        };
        text.oninput = function () { if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(text.value.trim())) color.value = text.value.trim(); markDirty(prop); livePreview(); };
      } else if (cfg.type === 'size') {
        var uv = unitValue(raw, cfg.unit || 'px');
        var range = row.querySelector('[data-range]');
        var num = row.querySelector('[data-number]');
        var hidden = row.querySelector('input[type="hidden"]');
        if (uv.value !== '') {
          range.value = Math.max(parseFloat(range.min), Math.min(parseFloat(range.max), parseFloat(uv.value)));
          num.value = uv.value;
          hidden.value = uv.value + (uv.unit || '');
        } else {
          hidden.value = raw;
        }
        range.oninput = function () { num.value = range.value; hidden.value = range.value + (cfg.unit || ''); markDirty(prop); livePreview(); };
        num.oninput = function () { range.value = num.value; hidden.value = num.value + (cfg.unit || ''); markDirty(prop); livePreview(); };
      } else if (cfg.type === 'segmented') {
        var hiddenSeg = row.querySelector('input[type="hidden"]');
        hiddenSeg.value = raw.trim();
        row.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-value') === hiddenSeg.value); });
      } else {
        valueInput.value = raw.trim();
      }
    });
  }

  function currentSelector() {
    if (!selectedInfo) return '';
    var scope = panel.querySelector('.mlcssp-ve-scope-select').value;
    return scope === 'group' ? selectedInfo.group : selectedInfo.precise;
  }

  function markDirty(prop) {
    dirty[prop] = true;
    var row = panel.querySelector('[data-prop="' + prop + '"]');
    if (row) row.classList.add('is-dirty');
    panel.querySelector('.mlcssp-ve-save-state').textContent = 'Unsaved';
    panel.querySelector('.mlcssp-ve-save-state').classList.add('is-dirty');
  }

  function readValue(prop) {
    var row = panel.querySelector('[data-prop="' + prop + '"]');
    if (!row) return '';
    var cfg = CONTROL_MAP[prop];
    if (cfg.type === 'color') {
      var text = row.querySelector('[data-text]').value.trim();
      if (text === '' || text.toLowerCase() === 'transparent') return 'transparent';
      return text;
    }
    var input = row.querySelector('[data-value]');
    return input ? String(input.value || '').trim() : '';
  }

  function dirtyStyles() {
    var out = {};
    Object.keys(dirty).forEach(function (prop) {
      var val = readValue(prop);
      if (val !== '') out[prop] = val;
    });
    return out;
  }

  function livePreview() {
    if (!selected) return;
    var styles = dirtyStyles();
    applyStylesToTargets(styles);
    isPreviewing = true;
    var count = targetElements().length;
    setStatus('Prévia ativa em ' + count + ' elemento(s). Clique em Salvar alteração para gravar.', 'info');
  }

  function previewDirty() {
    livePreview();
  }

  function restorePreview() {
    if (!selected) return;
    if (originalStyles && originalStyles.length) {
      originalStyles.forEach(function (item) {
        if (!item || !item.el) return;
        if (item.style === null) item.el.removeAttribute('style');
        else item.el.setAttribute('style', item.style);
      });
    } else if (typeof originalStyle !== 'undefined') {
      if (originalStyle === null) selected.removeAttribute('style');
      else selected.setAttribute('style', originalStyle);
    }
    isPreviewing = false;
  }

  function saveDirty() {
    if (!selected) return;
    var styles = dirtyStyles();
    var props = Object.keys(styles);
    if (!props.length) { setStatus('Nenhuma alteração pendente.', 'warn'); return; }
    var sel = currentSelector();
    setStatus('Salvando ' + props.length + ' alteração(ões)...', 'info');

    Promise.all(props.map(function (prop) {
      var fd = new FormData();
      fd.append('action', 'mlcssp_visual_save');
      fd.append('_wpnonce', C.nonce);
      fd.append('selector', sel);
      fd.append('property', prop);
      fd.append('value', styles[prop]);
      fd.append('device', currentDevice);
      fd.append('page_scope', pageScope);
      if (pageScope === 'page') { fd.append('page_key', PAGE.page_key||''); fd.append('page_url', PAGE.url||''); fd.append('page_title', PAGE.title||''); fd.append('body_class', PAGE.body_class||''); }
      return fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    })).then(function (responses) {
      var failed = responses.find(function (r) { return !r || !r.success; });
      if (failed) throw new Error((failed.data && failed.data.message) || failed.data || 'falha');
      /* Store saved values so reopening panel shows saved, not computed */
      if (currentDevice === 'desktop') {
        if (!savedOverrides[sel]) savedOverrides[sel] = {};
        Object.keys(styles).forEach(function (p) { savedOverrides[sel][p] = styles[p]; });
      } else {
        if (!C.responsive) C.responsive = { tablet: {}, mobile: {} };
        if (!C.responsive[currentDevice]) C.responsive[currentDevice] = {};
        if (!C.responsive[currentDevice][sel]) C.responsive[currentDevice][sel] = {};
        Object.keys(styles).forEach(function (p) { C.responsive[currentDevice][sel][p] = styles[p]; });
      }
      dirty = {};
      originalStyle = selected.getAttribute('style');
      rememberOriginalStyles();
      panel.querySelectorAll('.is-dirty').forEach(function (el) { el.classList.remove('is-dirty'); });
      panel.querySelector('.mlcssp-ve-save-state').textContent = 'Saved';
      panel.querySelector('.mlcssp-ve-save-state').classList.remove('is-dirty');
      setStatus('Alteração salva e ativa no frontend público.', 'ok');
    }).catch(function (err) {
      setStatus('Erro ao salvar: ' + err.message, 'err');
    });
  }

  function undoPreview() {
    if (isPreviewing) restorePreview();
    dirty = {};
    if (selected) fillControlsFromComputed(selected);
    panel.querySelectorAll('.is-dirty').forEach(function (el) { el.classList.remove('is-dirty'); });
    panel.querySelector('.mlcssp-ve-save-state').textContent = 'Saved';
    panel.querySelector('.mlcssp-ve-save-state').classList.remove('is-dirty');
    setStatus('Preview desfeito. Alteracoes nao salvas descartadas.', 'info');
  }

  function resetLastProperty() {
    var props = Object.keys(dirty);
    var sel = currentSelector();
    var savedProps = savedOverrides[sel] ? Object.keys(savedOverrides[sel]) : [];
    var target = props.length ? props[props.length - 1] : (savedProps.length ? savedProps[savedProps.length - 1] : '');
    if (!target) { setStatus('Nenhuma propriedade para resetar.', 'warn'); return; }
    if (!window.confirm('Resetar a propriedade "' + target + '" deste seletor?')) return;
    var fd = new FormData();
    fd.append('action', 'mlcssp_visual_reset');
    fd.append('_wpnonce', C.nonce);
    fd.append('selector', sel);
    fd.append('property', target);
    fd.append('device', currentDevice);
    fd.append('page_scope', pageScope);
    if (pageScope === 'page') { fd.append('page_key', PAGE.page_key||''); fd.append('page_url', PAGE.url||''); fd.append('page_title', PAGE.title||''); fd.append('body_class', PAGE.body_class||''); }
    setStatus('Resetando ' + target + '...', 'info');
    fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) throw new Error((res && res.data) || 'falha');
        if (savedOverrides[sel]) delete savedOverrides[sel][target];
        if (C.overrides && C.overrides[sel]) delete C.overrides[sel][target];
        delete dirty[target];
        if (isPreviewing) restorePreview();
        if (selected) fillControlsFromComputed(selected);
        setStatus('Propriedade "' + target + '" resetada e CSS recompilado.', 'ok');
      })
      .catch(function (err) { setStatus('Erro ao resetar: ' + err.message, 'err'); });
  }

  function resetSelector() {
    var sel = currentSelector();
    if (!window.confirm('Resetar TODAS as alteracoes salvas para "' + sel + '"? Outros seletores nao sao afetados.')) return;
    var fd = new FormData();
    fd.append('action', 'mlcssp_visual_reset');
    fd.append('_wpnonce', C.nonce);
    fd.append('selector', sel);
    fd.append('property', '__all__');
    fd.append('device', currentDevice);
    fd.append('page_scope', pageScope);
    if (pageScope === 'page') { fd.append('page_key', PAGE.page_key||''); fd.append('selector', '__page__'); fd.append('page_url', PAGE.url||''); fd.append('page_title', PAGE.title||''); fd.append('body_class', PAGE.body_class||''); }
    setStatus('Resetando seletor...', 'info');
    fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) throw new Error((res && res.data) || 'falha');
        delete savedOverrides[sel];
        if (C.overrides) delete C.overrides[sel];
        dirty = {};
        if (isPreviewing) restorePreview();
        if (selected) fillControlsFromComputed(selected);
        panel.querySelectorAll('.is-dirty').forEach(function (el) { el.classList.remove('is-dirty'); });
        setStatus('Todos os overrides de "' + sel + '" foram removidos.', 'ok');
      })
      .catch(function (err) { setStatus('Erro ao resetar: ' + err.message, 'err'); });
  }

  function setStatus(msg, type) {
    var el = panel.querySelector('.mlcssp-ve-status');
    el.textContent = msg;
    el.className = 'mlcssp-ve-status mlcssp-ve-s-' + type;
  }

  function removeQueryFlag() {
    var url = new URL(window.location.href);
    url.searchParams.delete('mlcssp_visual');
    return url.toString();
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]); });
  }
  function escapeAttr(s) { return escapeHtml(s); }

  function selectFromEvent(e) {
    if (!e || isEditorChrome(e.target)) return;
    var raw = pointTarget(e) || e.target;
    if (isEditorChrome(raw)) return;
    var target = meaningfulTarget(raw);
    if (!target) return;
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    openPanel(target);
  }

  function init() {
    document.documentElement.classList.add('mlcssp-visual-mode');
    createOutline();
    createToolbar();
    createBreadcrumb();
    createPanel();

    document.addEventListener('mouseover', function (e) {
      var target = meaningfulTarget(pointTarget(e) || e.target);
      moveOutline(target || e.target);
    }, true);
    document.addEventListener('pointerdown', selectFromEvent, true);
    document.addEventListener('mousedown', selectFromEvent, true);
    document.addEventListener('click', selectFromEvent, true);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (isPreviewing) restorePreview();
        panel.classList.remove('is-open');
      }
    });

    window.addEventListener('resize', function () {
      applyResponsiveViewport();
      if (selected) moveOutline(selected);
    });
    applyResponsiveViewport();

    setTimeout(function () { panel.classList.add('is-open'); }, 250);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
