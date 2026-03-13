<?php require_once '../config/bootstrap.php'; ?>
<!-- modules/integration.php -->
<div class="section">
    <div class="section-header">
        <h2>System Integration</h2>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" type="button" onclick="showModuleTemplates('integration')"><i class="fas fa-magic"></i> Templates</button>
            <button class="btn btn-primary" onclick="showAddIntegrationModal()"><?= __('add_integration') ?></button>
        </div>
    </div>
    <div id="integration-template-panel" class="da-section-card da-hidden" style="display:none; margin-bottom:16px;">
        <div class="da-section-header">
            <h3><i class="fas fa-layer-group"></i> Integration Templates</h3>
            <button type="button" class="da-close-btn" onclick="hideModuleTemplates('integration')">&times;</button>
        </div>
        <div class="da-template-body">
            <div id="integration-template-grid" class="da-template-grid"></div>
        </div>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th><?= __('system_name') ?></th>
                <th><?= __('interface_type') ?></th>
                <th><?= __('actions') ?></th>
            </tr>
        </thead>
        <tbody id="integration-list">
            <!-- Loaded via JS -->
        </tbody>
    </table>
</div>
