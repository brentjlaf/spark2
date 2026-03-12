<!-- File: layout.call-to-action-banner.php -->
<!-- Template: layout.call-to-action-banner -->
<templateSetting caption="Call to Action Banner Settings" order="1">
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
    <dl class="sparkDialog _tpl-box">
        <dt>Background Image</dt>
        <dd>
            <input type="text" name="custom_bg_image" id="custom_bg_image" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_bg_image')"><i class="fa-solid fa-image-portrait btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Text Contrast</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_text_contrast" value=" cta-banner--text-dark" checked> Dark text</label>
            <label><input type="radio" name="custom_text_contrast" value=" cta-banner--text-light"> Light text</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Banner Size</dt>
        <dd>
            <select name="custom_size">
                <option value="small">Compact</option>
                <option value="medium" selected="selected">Comfortable</option>
                <option value="large">Spacious</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Text Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_align" value=" text-start"> Left</label>
            <label><input type="radio" name="custom_align" value=" text-center" checked> Center</label>
            <label><input type="radio" name="custom_align" value=" text-end"> Right</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Headline</dt>
        <dd><input type="text" name="custom_headline" value="Ready to transform your next project?"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Subcopy</dt>
        <dd><textarea class="form-control" name="custom_subcopy" rows="3">Build a tailored digital experience with Spark CMS. Launch pages faster while keeping brand and accessibility on point.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Primary CTA</dt>
        <dd>
            <div class="formGroup">Label</div>
            <input type="text" name="custom_primary_label" value="Get Started">
            <div class="formGroup">Link</div>
            <input type="text" name="custom_primary_link" value="#">
            <div class="formGroup">Style</div>
            <select name="custom_primary_style">
                <option value=" btn-primary" selected="selected">Primary</option>
                <option value=" btn-secondary">Secondary</option>
            </select>
            <div class="formGroup"><label><input type="checkbox" name="custom_primary_new_window" value=' target="_blank" rel="noopener"'> Open in new window</label></div>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Secondary CTA</dt>
        <dd>
            <div class="formGroup"><label><input type="checkbox" name="custom_show_secondary" value="true"> Display secondary button</label></div>
            <div class="formGroup">Label</div>
            <input type="text" name="custom_secondary_label" value="Talk to sales">
            <div class="formGroup">Link</div>
            <input type="text" name="custom_secondary_link" value="#">
            <div class="formGroup">Style</div>
            <select name="custom_secondary_style">
                <option value=" btn-secondary" selected="selected">Secondary</option>
                <option value=" btn-primary">Primary</option>
            </select>
            <div class="formGroup"><label><input type="checkbox" name="custom_secondary_new_window" value=' target="_blank" rel="noopener"'> Open in new window</label></div>
        </dd>
    </dl>
</templateSetting>
<section class="cta-banner hero-{custom_size}{custom_text_contrast}" style="background-color:{custom_bg_color};background-image:url('{custom_bg_image}');" data-tpl-tooltip="Call to Action Banner">
    <div class="container">
        <div class="cta-banner__inner{custom_align}">
            <h2 class="cta-banner__headline" data-editable>{custom_headline}</h2>
            <p class="cta-banner__subcopy" data-editable>{custom_subcopy}</p>
            <div class="cta-banner__actions">
                <a href="{custom_primary_link}" class="btn{custom_primary_style}"{custom_primary_new_window} data-editable data-tpl-tooltip="Primary CTA">{custom_primary_label}</a>
                <toggle rel="custom_show_secondary" value="true">
                    <a href="{custom_secondary_link}" class="btn{custom_secondary_style}"{custom_secondary_new_window} data-editable data-tpl-tooltip="Secondary CTA">{custom_secondary_label}</a>
                </toggle>
            </div>
        </div>
    </div>
</section>
