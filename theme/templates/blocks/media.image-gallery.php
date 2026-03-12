<!-- File: media.image-gallery.php -->
<!-- Template: media.image-gallery -->
<templateSetting caption="Gallery Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 1 URL</dt>
        <dd>
            <input type="text" name="custom_img1" id="custom_img1" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img1')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 1 Alt Text</dt>
        <dd><input type="text" name="custom_alt1" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 2 URL</dt>
        <dd>
            <input type="text" name="custom_img2" id="custom_img2" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img2')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 2 Alt Text</dt>
        <dd><input type="text" name="custom_alt2" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 3 URL</dt>
        <dd>
            <input type="text" name="custom_img3" id="custom_img3" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img3')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 3 Alt Text</dt>
        <dd><input type="text" name="custom_alt3" value=""></dd>
    </dl>
</templateSetting>
<div class="image-gallery grid gap-6 md:grid-cols-3" data-tpl-tooltip="Image Gallery">
    <div class="gallery-item">
        <img src="{custom_img1}" alt="{custom_alt1}" class="w-full rounded-2xl shadow-sm">
    </div>
    <div class="gallery-item">
        <img src="{custom_img2}" alt="{custom_alt2}" class="w-full rounded-2xl shadow-sm">
    </div>
    <div class="gallery-item">
        <img src="{custom_img3}" alt="{custom_alt3}" class="w-full rounded-2xl shadow-sm">
    </div>
</div>
