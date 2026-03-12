<!-- File: content.rich-text.php -->
<!-- Template: content.rich-text -->
<templateSetting caption="Rich Text Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Content</dt>
        <dd><textarea class="form-control" name="custom_html"><p>Rich text content.</p></textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_align" value="text-left" checked> Left</label>
            <label class="me-2"><input type="radio" name="custom_align" value="text-center"> Center</label>
            <label><input type="radio" name="custom_align" value="text-right"> Right</label>
        </dd>
    </dl>
</templateSetting>
<div class="rich-text space-y-4 {custom_align}" data-tpl-tooltip="Rich Text" data-editable>{custom_html}</div>
