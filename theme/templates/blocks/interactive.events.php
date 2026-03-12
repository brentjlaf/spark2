<!-- File: interactive.events.php -->
<!-- Template: interactive.events -->
<?php $blockId = uniqid('events-block-'); ?>
<templateSetting caption="Events Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Section Title</dt>
        <dd>
            <input type="text" class="form-control" name="custom_title" value="Upcoming Events">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Intro Text</dt>
        <dd>
            <textarea class="form-control" name="custom_intro" rows="3">Join us for workshops, meetups, and community gatherings hosted by SparkCMS.</textarea>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Layout Style</dt>
        <dd>
            <select name="custom_layout" class="form-select">
                <option value="cards" selected>Cards</option>
                <option value="list">List</option>
                <option value="compact">Compact</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Number of Events</dt>
        <dd>
            <select name="custom_limit" class="form-select">
                <option value="3" selected>Show 3 events</option>
                <option value="4">Show 4 events</option>
                <option value="6">Show 6 events</option>
                <option value="9">Show 9 events</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Filter by Category</dt>
        <dd>
            <input type="text" class="form-control" name="custom_category" placeholder="All categories">
            <small class="form-text text-muted">Match an event category name, slug, or ID to limit the results.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Detail Page URL Prefix</dt>
        <dd>
            <input type="text" class="form-control" name="custom_detail_base" value="/events">
            <small class="form-text text-muted">The event slug (derived from the title or ID) is appended automatically.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Button Label</dt>
        <dd>
            <input type="text" class="form-control" name="custom_button_label" value="View event">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Button?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_button" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_button" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Description?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_description" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_description" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Description Length</dt>
        <dd>
            <input type="number" class="form-control" name="custom_description_length" value="160" min="40" max="320">
            <small class="form-text text-muted">Maximum number of characters shown in the preview text.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Location?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_location" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_location" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Categories?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_categories" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_categories" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Starting Price?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_price" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_price" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Empty State Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_empty" value="There are no upcoming events right now.">
        </dd>
    </dl>
</templateSetting>
<section id="<?= $blockId ?>" class="section events-block events-block--layout-{custom_layout}" data-tpl-tooltip="Events" data-events-block data-events-layout="{custom_layout}" data-events-limit="{custom_limit}" data-events-category="{custom_category}" data-events-detail-base="{custom_detail_base}" data-events-button-label="{custom_button_label}" data-events-show-button="{custom_show_button}" data-events-show-description="{custom_show_description}" data-events-description-length="{custom_description_length}" data-events-show-location="{custom_show_location}" data-events-show-categories="{custom_show_categories}" data-events-show-price="{custom_show_price}" data-events-empty="{custom_empty}">
    <div class="mx-auto w-full max-w-6xl px-4">
        <div class="text-center mb-8">
            <h2 class="text-3xl md:text-4xl font-semibold tracking-tight" data-editable>{custom_title}</h2>
            <p class="mt-3 text-slate-600" data-editable>{custom_intro}</p>
        </div>
        <div class="events-block__items" data-events-items>
            <article class="events-block__item events-block__item--placeholder">
                <div class="events-block__body">
                    <h3 class="events-block__title">Loading events…</h3>
                    <p class="events-block__description">Upcoming events will appear here once published.</p>
                </div>
            </article>
        </div>
        <div class="events-block__empty text-center text-slate-500 d-none" data-events-empty>{custom_empty}</div>
    </div>
</section>
