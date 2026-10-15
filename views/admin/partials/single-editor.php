<?php $editorBody = (string) ($editorBody ?? ''); $editorFormat = (string) ($editorFormat ?? 'plain'); ?>
<div class="admin-body-editor">
  <label for="single-body">正文</label>
  <p class="muted">支持段落、文字样式、链接和媒体库图片；编辑器未加载时可直接编辑文本框。</p>
  <input type="hidden" name="body_format" id="single-body-format" value="<?= \FangdaysCMS\Cms\View::e($editorFormat) ?>">
  <textarea id="single-body" name="body" rows="14"><?= \FangdaysCMS\Cms\View::e($editorBody) ?></textarea>
  <div id="single-editor" class="rich-editor-host" hidden></div>
  <div class="admin-editor-tools"><button class="layui-btn layui-btn-primary" type="button" id="single-insert-media" disabled><i class="fa fa-image" aria-hidden="true"></i> 从媒体库插入图片</button></div>
</div>
<dialog id="single-media-picker" class="admin-media-picker" aria-labelledby="single-media-picker-title">
  <div class="admin-media-picker-head"><h2 id="single-media-picker-title">选择正文图片</h2><button class="layui-btn layui-btn-primary" type="button" id="single-close-media-picker" aria-label="关闭">关闭</button></div>
  <?php if ($can['media.manage'] ?? false): ?><div class="admin-media-upload"><label for="single-image-upload">上传新图片</label><input id="single-image-upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><span id="single-upload-status" role="status" aria-live="polite"></span></div><?php endif; ?>
  <div id="single-media-grid" class="admin-media-picker-grid">
    <?php foreach ($media as $image): ?><button type="button" class="admin-media-choice" data-path="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>" data-name="<?= \FangdaysCMS\Cms\View::e($image['original_name']) ?>"><img src="<?= \FangdaysCMS\Cms\View::e($image['path']) ?>" alt=""><span><?= \FangdaysCMS\Cms\View::e($image['original_name']) ?></span></button><?php endforeach; ?>
  </div>
  <?php if (! $media): ?><p class="muted">媒体库暂无图片。</p><?php endif; ?>
</dialog>
<script src="/assets/ueditor/ueditor.config.js"></script>
<script src="/assets/ueditor/ueditor.all.min.js"></script>
<script src="/assets/ueditor/lang/zh-cn/zh-cn.js"></script>
<script>
  (() => {
    const form = document.querySelector('form.js-single-editor-form');
    const textarea = document.getElementById('single-body');
    const format = document.getElementById('single-body-format');
    const host = document.getElementById('single-editor');
    const trigger = document.getElementById('single-insert-media');
    const picker = document.getElementById('single-media-picker');
    if (!form || !textarea || !host || !window.UE) return;
    host.hidden = false;
    const editor = UE.getEditor('single-editor', {
      initialContent: <?= json_encode(\FangdaysCMS\Cms\RichText::editorHtml($editorBody, $editorFormat), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>
    });
    let editorReady = false;
    editor.ready(() => {
      editorReady = true;
      textarea.hidden = true;
      trigger.disabled = false;
    });
    form.addEventListener('submit', () => {
      if (!editorReady) return;
      textarea.value = editor.getContent();
      format.value = 'html';
    });
    const insert = (path, name) => {
      editor.execCommand('insertimage', {src: path, alt: name});
      picker.close();
      editor.focus();
    };
    trigger.addEventListener('click', () => picker.showModal());
    document.getElementById('single-close-media-picker').addEventListener('click', () => picker.close());
    document.getElementById('single-media-grid').addEventListener('click', (event) => {
      const choice = event.target.closest('button[data-path]');
      if (choice) insert(choice.dataset.path, choice.dataset.name);
    });
    const upload = document.getElementById('single-image-upload');
    if (upload) upload.addEventListener('change', async () => {
      const file = upload.files[0];
      if (!file) return;
      const status = document.getElementById('single-upload-status');
      status.textContent = '正在上传…';
      const data = new FormData();
      data.append('_csrf', form.querySelector('[name="_csrf"]').value);
      data.append('image', file);
      try {
        const response = await fetch('/admin/media/editor-upload', {method: 'POST', body: data, credentials: 'same-origin'});
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || '上传失败。');
        status.textContent = '上传成功。';
        insert(result.path, result.name);
      } catch (error) {
        status.textContent = error.message || '上传失败。';
      } finally {
        upload.value = '';
      }
    });
  })();
</script>
