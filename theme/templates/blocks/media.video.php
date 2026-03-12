<!-- File: media.video.php -->
<!-- Template: media.video -->
<templateSetting caption="Video Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Embed URL</dt>
        <dd>
            <input type="text" name="custom_src" id="custom_src" value="https://www.youtube.com/embed/dQw4w9WgXcQ" class="form-control">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_src')"><i class="fa-solid fa-film btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
            <small class="form-text text-muted">Paste an embed URL from YouTube, Vimeo, or choose a video from the media library.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Title</dt>
        <dd><input type="text" name="custom_title" value="Featured Video" class="form-control"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Caption</dt>
        <dd><textarea name="custom_caption" rows="2" class="form-control">Add an optional caption for additional context.</textarea></dd>
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
    <figure class="space-y-3" data-tpl-tooltip="Video">
        <div class="aspect-video overflow-hidden rounded-2xl bg-slate-900/10 shadow-sm">
            <iframe class="h-full w-full" src="{custom_src}" title="{custom_title}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
        </div>
        <figcaption class="text-sm text-slate-500" data-editable>{custom_caption}</figcaption>
    </figure>
</div>
