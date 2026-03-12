<!-- File: layout.sidebar.php -->
<!-- Template: layout.sidebar -->
<?php $tpl_id = uniqid('tpl-'); ?>
<templateSetting caption="Options" order="1">
    <dl class="sparkDialog">
        <dt>Layout:</dt>
        <dd>
            <label><input type="radio" name="custom_layout" value="left" checked> Side | Main</label>
            <label><input type="radio" name="custom_layout" value="right"> Main | Side</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt class="formGroup">General Options</dt>
        <dt>Border:</dt>
        <dd>
            <select name="custom_border">
                <option value="" selected>Disable (default)</option>
                <option value=" border">Enable</option>
            </select>
        </dd>
        <dt>Gap Between the Side Area and the Main Area:</dt>
        <dd>
            <select name="custom_gap">
                <option value=" g-1">Small</option>
                <option value=" g-3" selected>Medium (default)</option>
                <option value=" g-5">Large</option>
                <option value=" g-5">Extra Large</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt class="formGroup">Side Area</dt>
        <dt>Side Area Size:</dt>
        <dd>
            <select name="custom_size">
                <option value="col-xl-3 col-lg-4">Small</option>
                <option value="col-lg-4" selected>Medium (default)</option>
                <option value="col-lg-5">Large</option>
            </select>
        </dd>
        <dt>Side Area Background Color:</dt>
        <dd class="color-options">
            <label><input type="radio" name="custom_side_bg" value="" checked> None</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-primary"> Primary</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-secondary"> Secondary</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-success"> Third</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-info"> Fourth</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-white"> White</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-light"> Light</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-secondary"> Gray</label>
            <label><input type="radio" name="custom_side_bg" value=" bg-dark"> Dark</label>
        </dd>
    </dl>
</templateSetting>

<toggle rel="custom_layout" value="left">
    <div id="<?= $tpl_id ?>" class="sidebar sidebar-default" data-tpl-tooltip="Sidebar - Style 1">
        <div class="row{custom_gap}">
            <div class="sidebar-side {custom_size} order-lg-first">
                <div class="sidebar-side-wrap">
                    <div class="sidebar-inner{custom_side_bg}{custom_border}">
                        <div class="drop-area"></div>
                    </div>
                </div>
            </div>
            <div class="sidebar-main col order-lg-last">
                <div class="sidebar-main-wrap">
                    <div class="sidebar-inner{custom_border}">
                        <div class="drop-area"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</toggle>
<toggle rel="custom_layout" value="right">
    <div id="<?= $tpl_id ?>" class="sidebar sidebar-default" data-tpl-tooltip="Sidebar - Style 1">
        <div class="row{custom_gap}">
            <div class="sidebar-side {custom_size} order-lg-last">
                <div class="sidebar-side-wrap">
                    <div class="sidebar-inner{custom_side_bg}{custom_border}">
                        <div class="drop-area"></div>
                    </div>
                </div>
            </div>
            <div class="sidebar-main col order-lg-first">
                <div class="sidebar-main-wrap">
                    <div class="sidebar-inner{custom_border}">
                        <div class="drop-area"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</toggle>
