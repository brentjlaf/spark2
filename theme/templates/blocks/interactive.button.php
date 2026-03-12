<!-- File: interactive.button.php -->
<!-- Template: interactive.button -->
<templateSetting caption="Button Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Text</dt>
        <dd><input type="text" name="custom_text" value="Click Me"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Link</dt>
        <dd><input type="text" name="custom_link" value="#"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Open in New Window</dt>
        <dd><label><input type="checkbox" name="custom_new_window" value=' target="_blank"'> New window</label></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_align" value="text-left" checked> Left</label>
            <label><input type="radio" name="custom_align" value="text-center"> Center</label>
            <label><input type="radio" name="custom_align" value="text-right"> Right</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Background Color</dt>
        <dd>
            <div class="color-picker">
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--primary)">
                    <span class="color-swatch" style="background-color: var(--primary);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--secondary)">
                    <span class="color-swatch" style="background-color: var(--secondary);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--third)">
                    <span class="color-swatch" style="background-color: var(--third);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--fourth)">
                    <span class="color-swatch" style="background-color: var(--fourth);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--white)" checked>
                    <span class="color-swatch" style="background-color: var(--white);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--black)">
                    <span class="color-swatch" style="background-color: var(--black);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--light)">
                    <span class="color-swatch" style="background-color: var(--light);"></span>
                </label>
                <label>
                    <input type="radio" name="custom_bg_color" value="var(--gray)">
                    <span class="color-swatch" style="background-color: var(--gray);"></span>
                </label>
            </div>
        </dd>
    </dl>
</templateSetting>
<div class="{custom_align}">
    <a href="{custom_link}" class="inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm transition hover:opacity-90" style="background-color:{custom_bg_color}; color: var(--white);"{custom_new_window} data-tpl-tooltip="Button" data-editable>{custom_text}</a>
</div>
