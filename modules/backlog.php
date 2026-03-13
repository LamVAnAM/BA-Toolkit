<?php require_once '../config/bootstrap.php'; ?>
<!-- modules/backlog.php -->
<div class="section">
    <div class="section-header">
        <h2>Requirement Backlog</h2>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" type="button" onclick="showModuleTemplates('backlog')"><i class="fas fa-magic"></i> Templates</button>
            <button class="btn btn-primary" onclick="showAddBacklogModal()"><?= __('add_backlog') ?></button>
        </div>
    </div>
    <div id="backlog-template-panel" class="da-section-card da-hidden" style="display:none; margin-bottom:16px;">
        <div class="da-section-header">
            <h3><i class="fas fa-layer-group"></i> Tracking & Reports Templates</h3>
            <button type="button" class="da-close-btn" onclick="hideModuleTemplates('backlog')">&times;</button>
        </div>
        <div class="da-template-body">
            <div id="backlog-template-grid" class="da-template-grid"></div>
        </div>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= __('requirement') ?></th>
                <th><?= __('priority') ?></th>
                <th><?= __('status') ?></th>
                <th><?= __('actions') ?></th>
            </tr>
        </thead>
        <tbody id="backlog-list">
            <!-- Loaded via JS -->
        </tbody>
    </table>
</div>
