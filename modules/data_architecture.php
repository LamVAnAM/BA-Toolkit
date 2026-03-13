<?php require_once '../config/bootstrap.php'; ?>
<div class="section">
    <div class="da-welcome-card">
        <div>
            <h3><i class="fas fa-database"></i> Data Architecture Designer</h3>
            <p>Thiết kế kiến trúc dữ liệu cho hệ thống. Bắt đầu nhanh với template hoặc tạo thủ công.</p>
        </div>
        <div class="da-quick-actions">
            <button class="da-quick-btn" onclick="showDaTemplates()"><i class="fas fa-magic"></i> Template</button>
            <button class="da-quick-btn" onclick="showAiGenerateEntitiesModal()"><i class="fas fa-robot"></i> AI Generate</button>
            <button class="da-quick-btn" onclick="triggerEntityCsvImport()"><i class="fas fa-file-import"></i> Import</button>
            <button class="da-quick-btn" onclick="exportEntityCsv()"><i class="fas fa-file-export"></i> Export</button>
            <button class="da-quick-btn" onclick="triggerEntityJsonImport()"><i class="fas fa-file-code"></i> JSON</button>
        </div>
    </div>

    <div class="da-stats-row">
        <div class="da-stat-card">
            <span class="num" id="da-stat-entities">0</span>
            <span class="label">Entities</span>
        </div>
        <div class="da-stat-card">
            <span class="num" id="da-stat-attrs">0</span>
            <span class="label">Attributes</span>
        </div>
        <div class="da-stat-card pk">
            <span class="num" id="da-stat-pk">0</span>
            <span class="label">Primary Keys</span>
        </div>
        <div class="da-stat-card fk">
            <span class="num" id="da-stat-fk">0</span>
            <span class="label">Foreign Keys</span>
        </div>
        <div class="da-stat-card null">
            <span class="num" id="da-stat-null">0%</span>
            <span class="label">Nullable</span>
        </div>
    </div>

    <div class="da-search-bar">
        <input type="text" id="entity-search-input" placeholder="Tìm kiếm entity, attribute..." oninput="applyEntityFilters()">
        <select id="entity-type-filter" onchange="applyEntityFilters()">
            <option value="">Tất cả loại</option>
            <option value="master">Master Data</option>
            <option value="transaction">Transaction</option>
            <option value="lookup">Lookup</option>
        </select>
        <button class="btn btn-primary" onclick="showAddEntityModal()"><i class="fas fa-plus"></i> Thêm Entity</button>
    </div>

    <div id="da-template-panel" class="da-section-card da-hidden">
        <div class="da-section-header">
            <h3><i class="fas fa-layer-group"></i> Chọn Template</h3>
            <button onclick="hideModuleTemplates('data_architecture')" class="da-close-btn">&times;</button>
        </div>
        <div class="da-template-body">
            <p class="da-template-intro">Chọn template phù hợp với nghiệp vụ của bạn:</p>
            <div id="data_architecture-template-grid" class="da-template-grid"></div>
        </div>
    </div>

    <div class="da-help-tip" id="da-help-tip">
        <i class="fas fa-lightbulb"></i>
        <span id="da-help-text">Tip: Bắt đầu bằng việc xác định các thực thể chính (Master Data) trong hệ thống.</span>
    </div>

    <div class="diagram-split-layout">
        <div>
            <div class="da-section-card">
                <div class="da-section-header">
                    <h3><i class="fas fa-database"></i> Danh sách Entities</h3>
                    <span id="da-entity-count" class="da-muted-meta">0 entities</span>
                </div>
                <div id="entity-list"></div>
            </div>

            <div class="da-section-card">
                <div class="da-section-header">
                    <h3><i class="fas fa-project-diagram"></i> Quan hệ giữa các Entity</h3>
                    <button class="btn btn-primary btn-sm da-rel-add-btn" onclick="showAddRelationshipModal()">
                        <i class="fas fa-plus"></i> Thêm
                    </button>
                </div>
                <div id="relationship-list" class="da-scroll-box da-rel-box"></div>
            </div>

            <div class="da-section-card">
                <div class="da-section-header">
                    <h3><i class="fas fa-book"></i> Data Dictionary</h3>
                    <button class="btn btn-outline btn-sm da-dict-export-btn" onclick="exportDataDictionaryTxt()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
                <div id="data-dictionary-box" class="da-scroll-box da-dict-box"></div>
            </div>
        </div>

        <div>
            <div class="da-section-card da-sticky-card">
                <div class="da-section-header">
                    <h3><i class="fas fa-sitemap"></i> ER Diagram</h3>
                    <div class="da-diagram-controls">
                        <button class="btn btn-outline btn-sm" onclick="zoomDataDiagram(1.1)" title="Phóng to">+</button>
                        <button class="btn btn-outline btn-sm" onclick="zoomDataDiagram(0.9)" title="Thu nhỏ">-</button>
                        <button class="btn btn-outline btn-sm" onclick="zoomDataDiagram(1)" title="Đặt lại">&#x27F2;</button>
                    </div>
                </div>
                <div id="data-diagram-preview" class="da-diagram-preview">
                    <div class="da-empty-state">
                        <i class="fas fa-sitemap"></i>
                        <h4>Chưa có dữ liệu</h4>
                        <p>Thêm entities để xem sơ đồ quan hệ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="file" id="entity-csv-file" accept=".csv" class="da-hidden-input" onchange="handleEntityCsvImport(this)">
<input type="file" id="entity-json-file" accept=".json" class="da-hidden-input" onchange="handleEntityJsonImport(this)">
