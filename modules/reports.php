<!-- modules/reports.php -->
<div class="section">
    <div class="section-header">
        <h2>Consolidated Reports</h2>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" type="button" onclick="showModuleTemplates('reports')"><i class="fas fa-magic"></i> Templates</button>
        </div>
    </div>
    <div id="reports-template-panel" class="da-section-card da-hidden" style="display:none; margin-bottom:16px;">
        <div class="da-section-header">
            <h3><i class="fas fa-layer-group"></i> Report Templates</h3>
            <button type="button" class="da-close-btn" onclick="hideModuleTemplates('reports')">&times;</button>
        </div>
        <div class="da-template-body">
            <div id="reports-template-grid" class="da-template-grid"></div>
        </div>
    </div>
    <div style="padding:20px;">
        <ul>
            <li><a href="#" onclick="generateAIReport()">AI Requirement Report (Comprehensive)</a></li>
            <li><a href="#" onclick="generateProjectReport()">Requirement Specification Report (SRS)</a></li>
            <li><a href="#" onclick="exportDOCX()">Download Latest Report (DOCX)</a></li>
            <li><a href="#">Enterprise Architecture Overview</a></li>
            <li><a href="#">Gap Analysis Report</a></li>
        </ul>
    </div>
</div>
