<!-- File: layout.section.php -->
<!-- Template: layout.section -->
<templateSetting caption="Section Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Width</dt>
        <dd>
            <select name="custom_type">
                <option value="container" selected="selected">Standard</option>
                <option value="container-fluid">Full Width</option>
            </select>
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
<toggle rel="custom_type" value="container">
    <section class="section" style="background-color:{custom_bg_color};" data-tpl-tooltip="Section">
        <div class="mx-auto w-full max-w-6xl px-4">
            <div class="drop-area"></div>
        </div>
    </section>
</toggle>
<toggle rel="custom_type" value="container-fluid">
    <section class="section" style="background-color:{custom_bg_color};" data-tpl-tooltip="Section">
        <div class="w-full px-4">
            <div class="drop-area"></div>
        </div>
    </section>
</toggle>
