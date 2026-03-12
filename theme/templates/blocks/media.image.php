<!-- File: media.image.php -->
<!-- Template: media.image -->
<templateSetting caption="Image Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Source</dt>
        <dd>
            <input type="text" name="custom_src" id="custom_src" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_src')"><i class="fa-solid fa-images btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alt Text</dt>
        <dd><input type="text" name="custom_alt" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_align" value="text-start"> Left</label>
            <label><input type="radio" name="custom_align" value="text-center" checked> Center</label>
            <label><input type="radio" name="custom_align" value="text-end"> Right</label>
        </dd>
    </dl>
</templateSetting>
<div class="{custom_align}">
    <img src="{custom_src}" alt="{custom_alt}" class="inline-block max-w-full rounded-2xl shadow-sm" data-tpl-tooltip="Image"/>
</div>
