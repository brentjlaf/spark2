<!-- File: blog.post-details.php -->
<!-- Template: blog.post-details -->
<?php $blockId = uniqid('blog-detail-'); ?>
<templateSetting caption="Post Details Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Back Link Label</dt>
        <dd>
            <input type="text" class="form-control" name="custom_back_label" value="Back to all posts">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Back Link URL</dt>
        <dd>
            <input type="text" class="form-control" name="custom_back_base" value="/blogs">
            <small class="form-text text-muted">Example: <code>/blogs</code>, <code>https://example.com/blogs</code>, or leave blank to disable.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Back Link</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_back_link" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_back_link" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Back Button</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_back_button" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_back_button" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Category</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_category" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_category" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Author</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_author" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_author" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Publish Date</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_date" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_date" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Tags</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_tags" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_tags" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Featured Image</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_featured" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_featured" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Loading Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_loading_text" value="Loading post details…">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Empty State Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_empty" value="This post could not be found.">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Slug Override</dt>
        <dd>
            <input type="text" class="form-control" name="custom_slug" placeholder="Use the slug from the page URL">
            <small class="form-text text-muted">Optional: specify a slug to load a specific post regardless of the page URL.</small>
        </dd>
    </dl>
</templateSetting>
<section id="<?= $blockId ?>" class="blog-post-detail section" data-tpl-tooltip="Post Details" data-blog-detail data-back-base="{custom_back_base}" data-back-label="{custom_back_label}" data-show-back-link="{custom_show_back_link}" data-show-back-button="{custom_show_back_button}" data-show-category="{custom_show_category}" data-show-author="{custom_show_author}" data-show-date="{custom_show_date}" data-show-tags="{custom_show_tags}" data-show-featured="{custom_show_featured}" data-loading-text="{custom_loading_text}" data-empty="{custom_empty}" data-slug="{custom_slug}">
    <div class="mx-auto w-full max-w-4xl px-4">
        <div class="blog-post-detail__loading text-muted d-none" data-blog-detail-loading>{custom_loading_text}</div>
        <div class="blog-post-detail__empty text-center text-muted d-none" data-blog-detail-empty>{custom_empty}</div>
        <div class="blog-post-detail__body space-y-6" data-blog-detail-main>
            <nav class="blog-post-detail__back" aria-label="Back to blog" data-blog-detail-back-wrapper>
                <a class="blog-post-detail__back-link" href="{custom_back_base}" data-blog-detail-back>
                    <i class="fas fa-arrow-left me-2" aria-hidden="true"></i>
                    <span data-blog-detail-back-label>{custom_back_label}</span>
                </a>
            </nav>
            <header class="blog-post-detail__header">
                <span class="blog-post-detail__category" data-blog-detail-category>Category</span>
                <h1 class="blog-post-detail__title" data-blog-detail-title>Post title appears here</h1>
                <div class="blog-post-detail__meta" data-blog-detail-meta>
                    <span class="blog-post-detail__author" data-blog-detail-author>Author Name</span>
                    <span class="blog-post-detail__date" data-blog-detail-date>Jan 1, 2024</span>
                </div>
            </header>
            <figure class="blog-post-detail__featured" data-blog-detail-featured>
                <img src="https://via.placeholder.com/1200x600?text=Featured+Image" alt="Placeholder featured image" class="blog-post-detail__image" data-blog-detail-image>
                <figcaption class="blog-post-detail__image-caption text-muted small mt-2" data-blog-detail-image-caption>Optional image caption</figcaption>
            </figure>
            <article class="blog-post-detail__content mw-rich-text" data-blog-detail-content>
                <p>This is placeholder content. Publish a blog post to replace it with real article content.</p>
            </article>
            <div class="blog-post-detail__tags" data-blog-detail-tags>
                <span class="blog-post-detail__tags-label uppercase text-xs font-semibold text-slate-500">Tags</span>
                <div class="blog-post-detail__tags-list mt-2 flex flex-wrap gap-2" data-blog-detail-tags-list>
                    <span class="blog-post-detail__tag">#example</span>
                    <span class="blog-post-detail__tag">#placeholder</span>
                </div>
            </div>
            <div class="blog-post-detail__actions" data-blog-detail-actions>
                <a class="btn-outline-primary blog-post-detail__back-button" href="{custom_back_base}" data-blog-detail-back-secondary>
                    <i class="fas fa-arrow-left me-2" aria-hidden="true"></i>
                    <span data-blog-detail-back-button-label>{custom_back_label}</span>
                </a>
            </div>
        </div>
    </div>
</section>
