<!-- File: interactive.map.php -->
<!-- Template: interactive.map -->
<?php $blockId = uniqid('map-block-'); ?>
<templateSetting caption="Map Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Section Title</dt>
        <dd>
            <input type="text" class="form-control" name="custom_title" value="Explore our locations">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Intro Text</dt>
        <dd>
            <textarea class="form-control" name="custom_intro" rows="3">Browse our global presence and tap any pin to learn more about a space.</textarea>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Map Height</dt>
        <dd>
            <select name="custom_map_height" class="form-select">
                <option value="medium" selected>Medium (420px)</option>
                <option value="short">Short (320px)</option>
                <option value="tall">Tall (540px)</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Location List?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_list" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_list" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Limit to Categories</dt>
        <dd>
            <input type="text" class="form-control" name="custom_category_filter" placeholder="All categories">
            <small class="form-text text-muted">Enter comma-separated category names, slugs, or IDs to limit which pins appear.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Default Zoom (single pin)</dt>
        <dd>
            <select name="custom_default_zoom" class="form-select">
                <option value="6" selected>City level (zoom 6)</option>
                <option value="8">Neighborhood (zoom 8)</option>
                <option value="10">Street level (zoom 10)</option>
                <option value="4">Region (zoom 4)</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Empty State Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_empty" value="No published locations match this view right now.">
        </dd>
    </dl>
</templateSetting>
<section id="<?= $blockId ?>" class="section map-block" data-tpl-tooltip="Map" data-map-block data-map-height="{custom_map_height}" data-map-show-list="{custom_show_list}" data-map-categories="{custom_category_filter}" data-map-default-zoom="{custom_default_zoom}" data-map-empty="{custom_empty}">
    <div class="mx-auto w-full max-w-6xl px-4">
        <div class="map-block__header text-center mb-8">
            <h2 class="text-3xl md:text-4xl font-semibold tracking-tight" data-editable>{custom_title}</h2>
            <p class="mt-3 text-slate-600" data-editable>{custom_intro}</p>
        </div>
        <div class="map-block__body">
            <div class="map-block__panel" data-map-panel>
                <div class="map-block__filters" data-map-filters></div>
                <div class="map-block__list" data-map-list role="list">
                    <article class="map-block__item map-block__item--placeholder" role="listitem">
                        <h3 class="map-block__item-title">Loading locations…</h3>
                        <p class="map-block__item-meta">Pins will appear as soon as locations are published.</p>
                    </article>
                </div>
            </div>
            <div class="map-block__map" data-map-container>
                <div class="map-block__loading" data-map-loading>Initializing map…</div>
            </div>
        </div>
        <div class="map-block__empty text-center text-slate-500 d-none" data-map-empty role="status" aria-live="polite">{custom_empty}</div>
    </div>
</section>
