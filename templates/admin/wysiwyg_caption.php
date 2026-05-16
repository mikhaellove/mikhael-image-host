<?php
// Reusable WYSIWYG editor partial.
// Expects $prefix to be set before include (e.g. 'upload', 'share', 'landingPage').
// $inputName controls the hidden input's `name` attribute so the same partial
// can back different form fields (defaults to 'caption' for back-compat).
$prefix = $prefix ?? 'caption';
$inputName = $inputName ?? 'caption';
$editorId = $prefix . 'CaptionEditor';
$toolbarId = $prefix . 'CaptionToolbar';
$hiddenId = $prefix . 'CaptionHidden';
?>
<div class="wysiwyg-wrap">
    <div id="<?= htmlspecialchars($toolbarId) ?>" class="wysiwyg-toolbar" role="toolbar" aria-label="Caption formatting">
        <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><strong>B</strong></button>
        <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><em>I</em></button>
        <button type="button" data-cmd="underline" title="Underline (Ctrl+U)"><span style="text-decoration: underline;">U</span></button>
        <span class="wysiwyg-sep"></span>
        <button type="button" data-cmd="insertUnorderedList" title="Bulleted list">• List</button>
        <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
        <span class="wysiwyg-sep"></span>
        <button type="button" data-cmd="createLink" title="Insert link">🔗 Link</button>
        <button type="button" data-cmd="unlink" title="Remove link">⛔ Unlink</button>
        <span class="wysiwyg-sep"></span>
        <button type="button" data-cmd="removeFormat" title="Clear formatting">✗ Clear</button>
    </div>
    <div id="<?= htmlspecialchars($editorId) ?>"
         class="wysiwyg-editor"
         contenteditable="true"
         data-placeholder="Add a caption (optional)"></div>
    <input type="hidden" id="<?= htmlspecialchars($hiddenId) ?>" name="<?= htmlspecialchars($inputName) ?>" value="">
</div>

<style>
    .wysiwyg-wrap {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        background: #fff;
    }

    .wysiwyg-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px;
        background: #f7f7f7;
        border-bottom: 1px solid #ddd;
        padding: 4px;
    }

    .wysiwyg-toolbar button {
        background: transparent;
        border: 1px solid transparent;
        border-radius: 3px;
        padding: 4px 8px;
        font-size: 13px;
        cursor: pointer;
        color: #333;
        line-height: 1.2;
    }

    .wysiwyg-toolbar button:hover {
        background: #e9ecef;
        border-color: #ced4da;
    }

    .wysiwyg-toolbar button.active {
        background: #dfeaff;
        border-color: #98b9ff;
    }

    .wysiwyg-sep {
        width: 1px;
        align-self: stretch;
        background: #ddd;
        margin: 2px 4px;
    }

    .wysiwyg-editor {
        min-height: 80px;
        padding: 8px 10px;
        font-family: inherit;
        font-size: 14px;
        line-height: 1.5;
        outline: none;
        overflow-y: auto;
        max-height: 300px;
    }

    .wysiwyg-editor:focus {
        background: #fffef5;
    }

    .wysiwyg-editor:empty:before {
        content: attr(data-placeholder);
        color: #999;
        pointer-events: none;
    }

    .wysiwyg-editor p {
        margin: 0 0 0.5em 0;
    }

    .wysiwyg-editor ul,
    .wysiwyg-editor ol {
        margin: 0 0 0.5em 0;
        padding-left: 1.5em;
    }
</style>

<script>
    (function() {
        if (window.initWysiwygCaption) return;

        window.initWysiwygCaption = function(prefix, initialHtml) {
            const editor = document.getElementById(prefix + 'CaptionEditor');
            const hidden = document.getElementById(prefix + 'CaptionHidden');
            const toolbar = document.getElementById(prefix + 'CaptionToolbar');
            if (!editor || !hidden || !toolbar) return;

            editor.innerHTML = initialHtml || '';
            hidden.value = initialHtml || '';

            if (editor.dataset.wysiwygBound === '1') return;
            editor.dataset.wysiwygBound = '1';

            try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

            function sync() {
                hidden.value = editor.innerHTML.trim() === '<br>' ? '' : editor.innerHTML;
            }

            function updateActive() {
                toolbar.querySelectorAll('button[data-cmd]').forEach(function(btn) {
                    const cmd = btn.dataset.cmd;
                    try {
                        if (['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList'].indexOf(cmd) !== -1) {
                            btn.classList.toggle('active', document.queryCommandState(cmd));
                        }
                    } catch (e) { /* ignore */ }
                });
            }

            toolbar.addEventListener('click', function(e) {
                const btn = e.target.closest('button[data-cmd]');
                if (!btn) return;
                e.preventDefault();
                const cmd = btn.dataset.cmd;
                editor.focus();

                if (cmd === 'createLink') {
                    const url = prompt('Enter URL:', 'https://');
                    if (!url) return;
                    if (!/^(https?:\/\/|mailto:|\/)/i.test(url)) {
                        alert('Link must start with http://, https://, mailto:, or /');
                        return;
                    }
                    document.execCommand('createLink', false, url);
                } else {
                    document.execCommand(cmd, false, null);
                }
                sync();
                updateActive();
            });

            editor.addEventListener('input', sync);
            editor.addEventListener('keyup', updateActive);
            editor.addEventListener('mouseup', updateActive);

            editor.addEventListener('paste', function(e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
            });

            editor.addEventListener('blur', sync);
        };
    })();
</script>
