<?php require_once '../config/bootstrap.php'; ?>
<!-- modules/organization.php -->
<div class="section">
    <div class="section-header">
        <h2><?= __('dept_list') ?></h2>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" type="button" onclick="showModuleTemplates('organization')"><i class="fas fa-magic"></i> Templates</button>
        </div>
    </div>
    <div id="organization-template-panel" class="da-section-card da-hidden" style="display:none; margin-bottom:16px;">
        <div class="da-section-header">
            <h3><i class="fas fa-layer-group"></i> Department Templates</h3>
            <button type="button" class="da-close-btn" onclick="hideModuleTemplates('organization')">&times;</button>
        </div>
        <div class="da-template-body">
            <div id="organization-template-grid" class="da-template-grid"></div>
        </div>
    </div>
    <table style="width:100%; border-collapse:collapse; background:white; border-radius:8px; overflow:hidden;">
        <thead style="background:var(--light);">
            <tr>
                <th style="padding:12px; text-align:left;"><?= __('id') ?></th>
                <th style="padding:12px; text-align:left;"><?= __('dept_name_table') ?></th>
                <th style="padding:12px; text-align:left;"><?= __('sponsor_label') ?></th>
                <th style="padding:12px; text-align:left;"><?= __('actions') ?></th>
            </tr>
        </thead>
        <tbody id="dept-table-body">
            <tr class="inline-add-row" id="add-dept-row">
                <td>*</td>
                <td><input type="text" id="new-dept-name" placeholder="<?= __('dept_name') ?>..." style="width:100%;"></td>
                <td><input type="text" id="new-dept-sponsor" placeholder="<?= __('sponsor') ?>..." style="width:100%;"></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="saveNewDeptInline()"><?= __('save') ?></button>
                </td>
            </tr>
            <!-- Loaded via JS -->
        </tbody>
    </table>
</div>
