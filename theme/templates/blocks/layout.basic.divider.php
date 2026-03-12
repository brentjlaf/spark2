<!-- File: layout.basic.divider.php -->
<!-- Template: layout.basic.divider -->
<templateSetting caption="Divider Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Style</dt>
        <dd>
            <select name="custom_style">
                <option value="none">Spacer Only</option>
                <option value="solid" selected="selected">Solid</option>
                <option value="dashed">Dashed</option>
                <option value="dotted">Dotted</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Height</dt>
        <dd>
            <select name="custom_height">
                <option value="my-2">Small</option>
                <option value="my-4" selected="selected">Medium</option>
                <option value="my-5">Large</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<toggle rel="custom_style" value="none">
    <div class="{custom_height}" data-tpl-tooltip="Spacer"></div>
</toggle>
<toggle rel="custom_style" value="solid">
    <hr class="divider-solid {custom_height}" data-tpl-tooltip="Divider"/>
</toggle>
<toggle rel="custom_style" value="dashed">
    <hr class="divider-dashed {custom_height}" data-tpl-tooltip="Divider"/>
</toggle>
<toggle rel="custom_style" value="dotted">
    <hr class="divider-dotted {custom_height}" data-tpl-tooltip="Divider"/>
</toggle>
