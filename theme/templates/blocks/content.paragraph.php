<!-- File: content.paragraph.php -->
<!-- Template: content.paragraph -->
<templateSetting caption="Paragraph Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Text</dt>
        <dd><textarea class="form-control" name="custom_text">Sample paragraph text.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Style</dt>
        <dd>
            <select name="custom_style" class="form-select">
                <option value="">Normal</option>
                <option value="text-lg text-slate-600">Lead</option>
            </select>
        </dd>
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
<p class="mb-3 text-slate-600 {custom_align} {custom_style}" data-tpl-tooltip="Paragraph" data-editable>{custom_text}</p>
