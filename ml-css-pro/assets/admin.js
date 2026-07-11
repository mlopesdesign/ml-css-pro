/**
 * ML CSS Pro — admin UI controller v1.4.5
 * Handles: tab navigation, toasts, color pickers, confirm dialogs,
 * copy-to-clipboard, and detected-panel interactions.
 */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    /* ── Wrapper: must match the class in class-mlcssp-admin-ui.php ── */
    var wrap = document.querySelector('.mlcssp-wrap');
    if (!wrap) { return; }

    var toastArea = document.getElementById('mlcssp-toast-area');

    /* ── Toast helper ── */
    function showToast(message, type) {
      if (!toastArea || !message) { return; }
      var el = document.createElement('div');
      el.className = 'mlcssp-toast mlcssp-toast-' + (type || 'success');
      el.textContent = message;
      toastArea.appendChild(el);
      requestAnimationFrame(function () { el.classList.add('is-visible'); });
      setTimeout(function () {
        el.classList.remove('is-visible');
        setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 220);
      }, 4000);
    }

    /* ── Tab switching ── */
    function activateTab(targetId, sourceButton) {
      if (!targetId) { return; }
      document.querySelectorAll('.mlcssp-tab-button').forEach(function (btn) { btn.classList.remove('is-active'); });
      document.querySelectorAll('.mlcssp-tab-panel').forEach(function (panel) { panel.hidden = true; panel.classList.remove('is-active'); });
      if (sourceButton && sourceButton.classList.contains('mlcssp-tab-button')) {
        sourceButton.classList.add('is-active');
      }
      var panel = document.getElementById(targetId);
      if (panel) { panel.hidden = false; panel.classList.add('is-active'); }
      /* Also mark the nav button if activated from a non-nav element */
      var navBtn = document.querySelector('.mlcssp-tab-nav .mlcssp-tab-button[data-tab-target="' + targetId + '"]');
      if (navBtn) { navBtn.classList.add('is-active'); }
      /* Update URL without reload */
      try {
        var tab = targetId.replace('mlcssp-tab-', '');
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('mlcssp_toast');
        url.searchParams.delete('mlcssp_toast_type');
        window.history.replaceState({}, '', url.toString());
      } catch (e) {}
    }

    /* Bind ALL elements with data-tab-target (nav buttons + shortcut buttons) */
    document.querySelectorAll('[data-tab-target]').forEach(function (button) {
      button.addEventListener('click', function (e) {
        e.preventDefault();
        activateTab(button.getAttribute('data-tab-target'), button);
      });
    });

    /* ── Color picker sync ── */
    document.querySelectorAll('.mlcssp-color-field').forEach(function (field) {
      var picker = field.querySelector('[data-color-picker]');
      var text = field.querySelector('[data-color-text]');
      if (!picker || !text) { return; }
      picker.addEventListener('input', function () { text.value = picker.value; });
      text.addEventListener('input', function () { if (/^#[0-9a-fA-F]{6}$/.test(text.value)) { picker.value = text.value; } });
      field.querySelectorAll('[data-swatch]').forEach(function (swatch) {
        swatch.addEventListener('click', function () {
          var c = swatch.getAttribute('data-swatch') || '';
          text.value = c;
          if (/^#[0-9a-fA-F]{6}$/.test(c)) { picker.value = c; }
        });
      });
    });

    /* ── Confirm dialogs ── */
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (!window.confirm(btn.getAttribute('data-confirm'))) { e.preventDefault(); }
      });
    });

    /* ── Copy-to-clipboard (generic) ── */
    function copyText(text) {
      if (!text) { return; }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          showToast('Copiado.', 'success');
        }).catch(function () { fallbackCopy(text); });
      } else {
        fallbackCopy(text);
      }
    }
    function fallbackCopy(text) {
      var temp = document.createElement('textarea');
      temp.value = text;
      temp.style.cssText = 'position:fixed;opacity:0;';
      document.body.appendChild(temp);
      temp.select();
      try { document.execCommand('copy'); showToast('Copiado.', 'success'); } catch (e) {}
      document.body.removeChild(temp);
    }

    /* Copy by data-copy-value (selector chips, detected rows) */
    document.querySelectorAll('.mlcssp-copy-text').forEach(function (btn) {
      btn.addEventListener('click', function () {
        copyText(btn.getAttribute('data-copy-value') || '');
      });
    });

    /* Copy by data-copy-target (prompt copy) */
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var selector = btn.getAttribute('data-copy-target');
        var field = document.querySelector(selector);
        if (!field) { return; }
        copyText(field.value || field.textContent || '');
      });
    });

    /* ── "Add to editor" buttons in detected panels ── */
    document.querySelectorAll('.mlcssp-add-to-editor').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var sel = btn.getAttribute('data-sel') || '';
        var prop = btn.getAttribute('data-prop') || '';
        var val = btn.getAttribute('data-val') || '';
        if (!sel || !prop) { return; }
        var snippet = sel + ' {\n  ' + prop + ': ' + val + ';\n}';
        /* Try to insert into CodeMirror editor */
        var textarea = document.getElementById('mlcssp-global-css');
        if (window.mlcsspEditor && window.mlcsspEditor._editors && window.mlcsspEditor._editors['mlcssp-global-css']) {
          var cm = window.mlcsspEditor._editors['mlcssp-global-css'];
          var doc = cm.getDoc();
          var cur = doc.getValue();
          var sep = cur && cur.trim().length ? '\n\n' : '';
          doc.replaceRange(sep + snippet + '\n', cm.getCursor());
          cm.save();
        } else if (textarea) {
          var sep2 = textarea.value && textarea.value.trim().length ? '\n\n' : '';
          textarea.value += sep2 + snippet + '\n';
        }
        showToast('Adicionado ao editor.', 'success');
        /* Switch to advanced tab so user sees it */
        var navBtn = document.querySelector('[data-tab-target="mlcssp-tab-advanced"]');
        if (navBtn) { activateTab('mlcssp-tab-advanced', navBtn); }
      });
    });
  });
})();
