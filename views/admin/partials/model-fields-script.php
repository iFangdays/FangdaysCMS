<script>
  (() => {
    const form = document.querySelector('form.admin-editor-grid, form.js-single-editor-form');
    if (!form) return;
    const editors = new Map();
    const initialize = () => {
      if (!window.UE) return;
      form.querySelectorAll('textarea.js-extra-editor:not(:disabled)').forEach((textarea) => {
        if (editors.has(textarea)) return;
        const host = document.getElementById(textarea.id.replace('-text-', '-host-'));
        if (!host) return;
        const editor = UE.getEditor(host.id, {initialContent: textarea.value});
        const state = {editor, ready: false};
        editors.set(textarea, state);
        editor.ready(() => { state.ready = true; host.hidden = false; textarea.hidden = true; });
      });
    };
    initialize();
    form.querySelector('select[name="category_id"]')?.addEventListener('change', () => setTimeout(initialize, 0));
    form.addEventListener('submit', () => {
      editors.forEach((state, textarea) => {
        if (!textarea.disabled && state.ready) textarea.value = state.editor.getContent();
      });
    });
    form.addEventListener('change', (event) => {
      if (!event.target.matches('select[data-extra-image]')) return;
      const preview = event.target.closest('.stack').querySelector('img.admin-gallery-preview');
      preview.hidden = !event.target.value;
      if (event.target.value) preview.src = event.target.value;
      else preview.removeAttribute('src');
    });
  })();
</script>
