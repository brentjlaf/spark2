<!-- File: interactive.form.php -->
<!-- Template: interactive.form -->
<templateSetting caption="Form Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Select Form</dt>
        <dd>
            <select name="custom_form_id" class="form-select" data-forms-select data-placeholder="Select a form...">
                <option value="">Select a form...</option>
            </select>
            <p class="mt-2 small text-muted">Manage forms from the Forms tab in the CMS.</p>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Success Message</dt>
        <dd>
            <input type="text" name="custom_success_message" value="Thank you! We'll be in touch soon." />
        </dd>
    </dl>
</templateSetting>
<div class="spark-form-embed" data-form-id="{custom_form_id}">
    <template data-success-template>{custom_success_message}</template>
    <div class="spark-form-placeholder">Select a form to display.</div>
</div>
