/* ML CSS Pro - code editor engine (WordPress CodeMirror) */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var cfg = window.mlcsspEditor || {};
    var i18n = cfg.i18n || {};
    var editors = {};

    function initEditors() {
      var textareas = document.querySelectorAll('[data-mlcssp-editor]');
      if (!textareas.length) { return; }

      /* Expose editors globally so admin.js "Add to editor" can access them */
      if (!cfg._editors) { cfg._editors = {}; }

      textareas.forEach(function (textarea) {
        var id = textarea.id;
        if (!id) { return; }

        if (cfg.hasCodeEditor && window.wp && window.wp.codeEditor && cfg.codeEditor) {
          try {
            var instance = window.wp.codeEditor.initialize(textarea, cfg.codeEditor);
            if (instance && instance.codemirror) {
              editors[id] = instance.codemirror;
              cfg._editors[id] = instance.codemirror;
              // Keep textarea in sync before any form submit.
              var form = textarea.closest('form');
              if (form) {
                form.addEventListener('submit', function () {
                  editors[id].save();
                });
              }
            }
          } catch (e) {
            /* graceful fallback to plain textarea */
          }
        }
      });
    }

    function insertIntoEditor(targetId, code) {
      var cm = editors[targetId];
      if (cm) {
        var doc = cm.getDoc();
        var current = doc.getValue();
        var prefix = current && current.trim().length ? '\n\n' : '';
        var cursor = doc.getCursor();
        doc.replaceRange(prefix + code + '\n', cursor);
        cm.focus();
        cm.save();
        return true;
      }
      var textarea = document.getElementById(targetId);
      if (textarea) {
        var sep = textarea.value && textarea.value.trim().length ? '\n\n' : '';
        textarea.value = textarea.value + sep + code + '\n';
        return true;
      }
      return false;
    }

    function toast(message, type) {
      var area = document.getElementById('mlcssp-toast-area');
      if (!area || !message) { return; }
      var el = document.createElement('div');
      el.className = 'mlcssp-toast mlcssp-toast-' + (type || 'success');
      el.textContent = message;
      area.appendChild(el);
      requestAnimationFrame(function () { el.classList.add('is-visible'); });
      setTimeout(function () {
        el.classList.remove('is-visible');
        setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 220);
      }, 3200);
    }

    function bindExpand() {
      document.querySelectorAll('.mlcssp-editor-expand').forEach(function (button) {
        button.addEventListener('click', function () {
          var targetId = button.getAttribute('data-editor-target');
          var textarea = document.getElementById(targetId);
          if (!textarea) { return; }
          var field = textarea.closest('.mlcssp-field');
          if (!field) { return; }
          var expanded = field.classList.toggle('mlcssp-editor-expanded');
          document.body.classList.toggle('mlcssp-has-expanded-editor', expanded);
          button.textContent = expanded ? (i18n.collapse || 'Collapse') : (i18n.expand || 'Expand');
          var cm = editors[targetId];
          if (cm) { setTimeout(function () { cm.refresh(); }, 60); }
        });
      });
    }

    function bindSnippets() {
      document.querySelectorAll('.mlcssp-snippet').forEach(function (snippet) {
        var pre = snippet.querySelector('[data-snippet-code]');
        if (!pre) { return; }
        var code = pre.textContent || '';

        var insertBtn = snippet.querySelector('.mlcssp-snippet-insert');
        if (insertBtn) {
          insertBtn.addEventListener('click', function () {
            var targetId = insertBtn.getAttribute('data-target') || 'mlcssp-global-css';
            // Move to the advanced tab so the user sees the result.
            var navButton = document.querySelector('.mlcssp-tab-button[data-tab-target="mlcssp-tab-advanced"]');
            if (navButton) { navButton.click(); }
            if (insertIntoEditor(targetId, code)) {
              toast(i18n.inserted || 'Snippet inserted.', 'success');
            }
          });
        }

        var copyBtn = snippet.querySelector('.mlcssp-snippet-copy');
        if (copyBtn) {
          copyBtn.addEventListener('click', function () {
            copyText(code);
          });
        }
      });
    }

    function bindCopyValues() {
      document.querySelectorAll('.mlcssp-copy-text').forEach(function (button) {
        button.addEventListener('click', function () {
          copyText(button.getAttribute('data-copy-value') || '');
        });
      });
    }

    function copyText(text) {
      if (!text) { return; }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          toast(i18n.copied || 'Copied.', 'success');
        }).catch(function () {
          fallbackCopy(text);
        });
      } else {
        fallbackCopy(text);
      }
    }

    function fallbackCopy(text) {
      var temp = document.createElement('textarea');
      temp.value = text;
      temp.style.position = 'fixed';
      temp.style.opacity = '0';
      document.body.appendChild(temp);
      temp.select();
      try { document.execCommand('copy'); toast(i18n.copied || 'Copied.', 'success'); } catch (e) {}
      document.body.removeChild(temp);
    }

    initEditors();
    bindExpand();
    bindSnippets();
    bindCopyValues();
  });
})();
