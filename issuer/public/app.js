document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    const field = document.getElementById(button.dataset.copy);
    if (!field) return;
    try {
      await navigator.clipboard.writeText(field.value);
      const label = button.textContent;
      button.textContent = '已复制';
      window.setTimeout(() => { button.textContent = label; }, 1600);
    } catch (_) {
      field.select();
      button.textContent = '请按 ⌘C 复制';
    }
  });
});
