<!-- File: layout.column-3.php -->
<!-- Template: layout.column-3 -->
<templateSetting caption="Column Settings" order="1">
    <dl class="mwDialog">
        <dt>Gap:</dt>
        <dd>
            <select name="custom_gap">
                <option value=" g-0">None</option>
                <option value=" g-1">Small</option>
                <option value=" g-3" selected="selected">Medium (default)</option>
                <option value=" g-5">Large</option>
                <option value=" g-5">Extra Large</option>
            </select>
        </dd>
        <dt>Alignment:</dt>
        <dd>
            <select name="custom_alignment">
                <option value="" selected="selected">Align columns top (default)</option>
                <option value=" align-items-center">Align columns centered</option>
                <option value=" align-items-end">Align columns bottom</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<div class="row {custom_gap} {custom_alignment}" data-tpl-tooltip="3 Columns">
    <div class="col"><div class="drop-area"></div></div>
    <div class="col"><div class="drop-area"></div></div>
    <div class="col"><div class="drop-area"></div></div>
</div>

