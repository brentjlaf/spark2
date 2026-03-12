<!-- File: layout.content-width.php -->
<!-- Template: layout.content-width -->
<templateSetting caption="Options" order="1">
    <dl class="mwDialog">
        <dt>Width:</dt>
        <dd>
            <select name="custom_width">
                <option value="480">480 pixel wide</option>
                <option value="570">570 pixel wide</option>
                <option value="670" selected="selected">670 pixel wide (default)</option>
                <option value="770">770 pixel wide</option>
                <option value="870">870 pixel wide</option>
                <option value="970">970 pixel wide</option>
                <option value="1170">1170 pixel wide</option>
                <option value="1240">1240 pixel wide</option>
            </select>
        </dd>
        <dt>Position:</dt>
        <dd>
            <select name="custom_position">
                <option value=" mx-auto" selected="selected">Center (default)</option>
                <option value=" me-auto">Left</option>
                <option value=" ms-auto">Right</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<div class="content-width container{custom_position} content-width-{custom_width}" data-tpl-tooltip="Content Width">
    <div class="drop-area"></div>
</div>

