<?php require_once '../config/bootstrap.php'; ?>
<!-- modules/process_mapping.php -->
<div class="section">
    <div class="section-header">
        <h2>Process Mapping (AS-IS / TO-BE)</h2>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" type="button" onclick="showModuleTemplates('process_mapping')"><i class="fas fa-magic"></i> Templates</button>
            <button class="btn btn-primary" onclick="showAddProcessModal()"><?= __('add_process') ?></button>
        </div>
    </div>
    <div id="process_mapping-template-panel" class="da-section-card da-hidden" style="display:none; margin-bottom:16px;">
        <div class="da-section-header">
            <h3><i class="fas fa-layer-group"></i> Process Templates</h3>
            <button type="button" class="da-close-btn" onclick="hideModuleTemplates('process_mapping')">&times;</button>
        </div>
        <div class="da-template-body">
            <div id="process_mapping-template-grid" class="da-template-grid"></div>
        </div>
    </div>
    <div class="diagram-split-layout">
        <div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('process_name') ?></th>
                        <th><?= __('process_type') ?></th>
                        <th><?= __('process_steps') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody id="process-list">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
        <div>
            <div class="section-header" style="margin-top:0;">
                <h2><i class="fas fa-project-diagram"></i> Process Flow Preview</h2>
            </div>
            <div id="process-diagram-preview" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--border); overflow:auto; text-align:left; min-height:420px;">
                <p style="color:#999; padding:40px;">No diagram to display. Add processes to see the flow.</p>
            </div>
        </div>
    </div>
</div>
