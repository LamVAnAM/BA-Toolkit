<?php require_once '../config/bootstrap.php'; ?>
<div class="survey-shell">
    <div class="survey-nav-col">
        <div class="sticky-nav">
            <div class="nav-header">
                <h3>Sections</h3>
                <span class="badge" id="section-progress">0/15</span>
            </div>
            <ul id="section-toc" class="section-toc">
                <!-- Dynamically populated via JS -->
            </ul>
        </div>
    </div>

    <div class="survey-main-col">
        <div class="survey-header-card">
            <div class="unit-info">
                <div class="unit-icon"><i class="fas fa-clipboard-check"></i></div>
                <div>
                    <small>Active Survey</small>
                    <h2 id="current-dept-name">...</h2>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="action-btn ai-btn" onclick="openAiIntakeModal('full')">
                    <i class="fas fa-wand-sparkles"></i>
                    <span>AI Intake</span>
                </button>
                <div class="btn-group">
                    <button type="button" class="action-btn" onclick="generateProjectReport()">
                        <i class="fas fa-file-export"></i>
                        <span>Report</span>
                    </button>
                    <button type="button" class="action-btn primary" onclick="saveSurveyData()">
                        <i class="fas fa-save"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
            </div>
        </div>

        <form id="surveyForm" class="survey-form-stack">
        <!-- Section 1: Organization Context -->
        <div class="section" id="section-1">
            <div class="section-header">
                <h2><?= __('sec_org_context') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('dept_name') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="1" data-key="dept_name" placeholder="P04 Finance...">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(1, 'dept_name')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('sponsor') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="1" data-key="sponsor" placeholder="Name & Position">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(1, 'sponsor')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('personnel_size') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="1" data-key="personnel_size" placeholder="Number of staff">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(1, 'personnel_size')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('dept_role') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="1" data-key="dept_role" rows="2" placeholder="Main responsibilities..."></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(1, 'dept_role')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-1">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(1)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 2: Stakeholders -->
        <div class="section" id="section-2">
            <div class="section-header">
                <h2><?= __('sec_stakeholders') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('key_users') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="2" data-key="key_users" placeholder="Daily operators">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(2, 'key_users')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('decision_makers') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="2" data-key="decision_makers" placeholder="Approvers">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(2, 'decision_makers')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('it_contact') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="2" data-key="it_contact" placeholder="Technical support">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(2, 'it_contact')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-2">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(2)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 3: Business Goals -->
        <div class="section" id="section-3">
            <div class="section-header">
                <h2><?= __('sec_goals_pains') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group full">
                    <label><?= __('business_goals') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="3" data-key="business_goals" rows="2" placeholder="What do you want to achieve?"></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(3, 'business_goals')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('pain_points') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="3" data-key="pain_points" rows="3" placeholder="Current difficulties..."></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(3, 'pain_points')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('success_criteria') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="3" data-key="success_criteria" placeholder="How to measure success?">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(3, 'success_criteria')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-3">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(3)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 4: Business Process -->
        <div class="section" id="section-4">
            <div class="section-header">
                <h2><?= __('sec_process') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group full">
                    <label><?= __('process_asis') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="4" data-key="process_asis" rows="5" placeholder="Step 1..., Step 2..."></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(4, 'process_asis')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('process_tobe') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="4" data-key="process_tobe" rows="3" placeholder="Desired optimized process..."></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(4, 'process_tobe')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('approval_flow') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="4" data-key="approval_flow" placeholder="Number of steps, who approves?">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(4, 'approval_flow')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-4">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(4)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <!-- AS-IS Process Diagram -->
            <div id="diag-outer-process_asis" class="survey-diagram-outer" style="display:none; margin-top:20px;">
                <div class="diagram-header">
                    <div class="diagram-title"><i class="fas fa-history"></i> AS-IS Process Preview</div>
                    <div class="diagram-controls">
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('process_asis', 0.8)"><i class="fas fa-search-minus"></i></button>
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('process_asis', 1.2)"><i class="fas fa-search-plus"></i></button>
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('process_asis', 1.0)"><i class="fas fa-undo"></i></button>
                    </div>
                </div>
                <div class="diagram-viewport">
                    <div id="survey-diagram-process_asis" class="mermaid-wrapper"></div>
                </div>
            </div>

            <!-- TO-BE Process Diagram -->
            <div id="diag-outer-process_tobe" class="survey-diagram-outer" style="display:none; margin-top:20px;">
                <div class="diagram-header">
                    <div class="diagram-title" style="color:#2e7d32;"><i class="fas fa-rocket"></i> TO-BE Process Preview</div>
                    <div class="diagram-controls">
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('process_tobe', 0.8)"><i class="fas fa-search-minus"></i></button>
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('process_tobe', 1.2)"><i class="fas fa-search-plus"></i></button>
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('process_tobe', 1.0)"><i class="fas fa-undo"></i></button>
                    </div>
                </div>
                <div class="diagram-viewport">
                    <div id="survey-diagram-process_tobe" class="mermaid-wrapper"></div>
                </div>
            </div>
        </div>

        <!-- Section 5: User & Permission -->
        <div class="section" id="section-5">
            <div class="section-header">
                <h2><?= __('sec_user_perm') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('role_input') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="5" data-key="role_input">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(5, 'role_input')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('role_approve') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="5" data-key="role_approve">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(5, 'role_approve')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('role_viewer') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="5" data-key="role_viewer">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(5, 'role_viewer')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-5">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(5)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 6: Data Characteristics -->
        <div class="section" id="section-6">
            <div class="section-header">
                <h2><?= __('sec_data_char') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('data_vol') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="6" data-key="data_vol" placeholder="Records per month">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(6, 'data_vol')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('data_source') ?></label>
                    <select data-section="6" data-key="data_source">
                        <option value="Excel">Excel / Manual</option>
                        <option value="System">Other Internal System</option>
                        <option value="External">External Supplier/Customer</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label><?= __('data_freq') ?></label>
                    <select data-section="6" data-key="data_freq">
                        <option value="Realtime">Realtime</option>
                        <option value="Daily">Daily</option>
                        <option value="Monthly">Monthly</option>
                    </select>
                </div>
                <div class="field-add-row" id="field-add-6">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(6)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 7: System Integration -->
        <div class="section" id="section-7">
            <div class="section-header">
                <h2><?= __('sec_integration') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group full">
                    <label><?= __('current_systems') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="7" data-key="current_systems">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(7, 'current_systems')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('integration_req') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="7" data-key="integration_req" rows="2"></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(7, 'integration_req')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-7">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(7)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 8: Non-Functional -->
        <div class="section" id="section-8">
            <div class="section-header">
                <h2><?= __('sec_non_functional') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('security_level') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="8" data-key="security_level">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(8, 'security_level')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('audit_log') ?></label>
                    <select data-section="8" data-key="audit_log">
                        <option value="High">Full Log (Detailed)</option>
                        <option value="Medium">Basic (Who/When)</option>
                        <option value="Low">Not Critical</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label><?= __('perf_req') ?></label>
                    <input type="text" data-section="8" data-key="perf_req" placeholder="Max downtime, response time...">
                </div>
                <div class="field-add-row" id="field-add-8">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(8)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

                <!-- Section 9: KPI & BI -->
        <div class="section" id="section-9">
            <div class="section-header">
                <h2><?= __('sec_kpi') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group full survey-highlight-box">
                    <label><?= __('golden_question') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="9" data-key="golden_question" rows="2" placeholder="Mo ta dieu ban muon thay dau tien..."></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(9, 'golden_question')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('sec_kpi') ?></label>
                    <div id="kpi-dynamic-list" class="dynamic-list"></div>
                    <button type="button" class="btn btn-outline" onclick="addSurveyKPI()">+ <?= __('add_kpi') ?></button>
                </div>
            </div>
        </div>

        <!-- Section 10: Module Proposal -->
        <div class="section" id="section-10">
            <div class="section-header">
                <h2><?= __('sec_modules') ?></h2>
            </div>
            <div class="survey-module-intro">
                <div>
                    <strong>Danh mục module đề xuất</strong>
                    <p>Chọn các module phù hợp với nhu cầu vận hành hiện tại và định hướng mở rộng. Có thể chọn nhiều module cùng lúc.</p>
                </div>
                <span class="survey-module-count">Multi-select</span>
            </div>
            <div class="survey-module-grid" id="module-checkboxes">
                <label class="cb-item"><input type="checkbox" name="module" value="Project Management"><span>Project Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Finance & Accounting"><span>Finance & Accounting</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Procurement"><span>Procurement</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Sales & CRM"><span>Sales & CRM</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="HRM"><span>HRM</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Inventory"><span>Inventory</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Document Management"><span>Document Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Workflow Approval"><span>Workflow Approval</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Customer Service"><span>Customer Service</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Asset Management"><span>Asset Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Maintenance Management"><span>Maintenance Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Manufacturing"><span>Manufacturing</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Warehouse Management"><span>Warehouse Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Quality Management"><span>Quality Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Business Intelligence"><span>Business Intelligence</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Budget Planning"><span>Budget Planning</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Contract Management"><span>Contract Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Supplier Portal"><span>Supplier Portal</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Knowledge Base"><span>Knowledge Base</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Task Management"><span>Task Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Risk Management"><span>Risk Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Compliance Management"><span>Compliance Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Helpdesk / Ticketing"><span>Helpdesk / Ticketing</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="E-Office"><span>E-Office</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Learning Management"><span>Learning Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Recruitment"><span>Recruitment</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Payroll"><span>Payroll</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Performance Evaluation"><span>Performance Evaluation</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Expense Management"><span>Expense Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="POS"><span>POS</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="E-Commerce"><span>E-Commerce</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Transport & Logistics"><span>Transport & Logistics</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Portal / Intranet"><span>Portal / Intranet</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="Master Data Management"><span>Master Data Management</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="AI Assistant / Copilot"><span>AI Assistant / Copilot</span></label>
                <label class="cb-item"><input type="checkbox" name="module" value="RPA / Automation"><span>RPA / Automation</span></label>
            </div>
            <div class="survey-module-custom">
                <label for="custom-module-input"><strong>Thêm module tùy chỉnh</strong></label>
                <div class="survey-module-custom-row">
                    <input type="text" id="custom-module-input" placeholder="Ví dụ: Legal Management, Call Center, Loyalty Program...">
                    <button type="button" class="btn btn-primary btn-sm" onclick="addCustomModule()"><i class="fas fa-plus"></i> Thêm module</button>
                </div>
                <small style="color:#666;">Module tự thêm sẽ được lưu cùng project và tự hiển thị lại khi mở khảo sát.</small>
            </div>
        </div>

        <!-- Section 11: Data Model -->
        <div class="section" id="section-11">
            <div class="section-header">
                <h2><?= __('sec_data_model') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group full">
                    <label><?= __('key_entities') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="11" data-key="key_entities">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(11, 'key_entities')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('master_data') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="11" data-key="master_data" placeholder="Customer list, Product catalog...">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(11, 'master_data')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-11">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(11)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div id="diag-outer-key_entities" class="survey-diagram-outer" style="display:none; margin-top:20px;">
                <div class="diagram-header">
                    <div class="diagram-title"><i class="fas fa-sitemap"></i> Data Structure Preview</div>
                    <div class="diagram-controls">
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('key_entities', 0.8)"><i class="fas fa-search-minus"></i></button>
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('key_entities', 1.2)"><i class="fas fa-search-plus"></i></button>
                        <button type="button" class="zoom-btn" onclick="zoomSurveyDiagram('key_entities', 1.0)"><i class="fas fa-undo"></i></button>
                    </div>
                </div>
                <div class="diagram-viewport">
                    <div id="survey-diagram-key_entities" class="mermaid-wrapper"></div>
                </div>
            </div>
        </div>

        <!-- Section 12: Automation Opportunities -->
        <div class="section" id="section-12">
            <div class="section-header">
                <h2><?= __('sec_automation') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group full">
                    <label><?= __('manual_tasks') ?></label>
                    <div class="ai-input-wrapper">
                        <textarea data-section="12" data-key="manual_tasks" rows="2"></textarea>
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(12, 'manual_tasks')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group full">
                    <label><?= __('automation_potential') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="12" data-key="automation_potential">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(12, 'automation_potential')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-12">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(12)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 13: Risks -->
        <div class="section" id="section-13">
            <div class="section-header">
                <h2><?= __('sec_risks') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('impl_risk') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="13" data-key="impl_risk">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(13, 'impl_risk')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('migration_risk') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="13" data-key="migration_risk">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(13, 'migration_risk')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-13">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(13)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 14: Requirement Backlog -->
        <div class="section" id="section-14">
            <div class="section-header">
                <h2><?= __('sec_backlog') ?></h2>
            </div>
            <div class="form-group full">
                <label><?= __('priority_features') ?></label>
                <div class="ai-input-wrapper">
                    <textarea data-section="14" data-key="priority_features" rows="3"></textarea>
                    <button type="button" class="btn-ai-inline" onclick="normalizeSection(14, 'priority_features')"><i class="fas fa-magic"></i></button>
                </div>
                <div class="field-add-row" id="field-add-14">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(14)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 15: Implementation Phase -->
        <div class="section" id="section-15">
            <div class="section-header">
                <h2><?= __('sec_phases') ?></h2>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('phase_1') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="15" data-key="phase_1">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(15, 'phase_1')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __('phase_2') ?></label>
                    <div class="ai-input-wrapper">
                        <input type="text" data-section="15" data-key="phase_2">
                        <button type="button" class="btn-ai-inline" onclick="normalizeSection(15, 'phase_2')"><i class="fas fa-magic"></i></button>
                    </div>
                </div>
                <div class="field-add-row" id="field-add-15">
                    <input type="text" placeholder="<?= __('add_field') ?>..." class="new-field-label">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline(15)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>
        
        <div id="dynamic-sections-container"></div>

        <div class="section-add-container">
            <h3 style="margin-bottom:15px;"><?= __('add_section') ?></h3>
            <div style="display:flex; gap:10px; max-width:500px; margin:0 auto;">
                <input type="text" id="new-section-name" placeholder="E.g. Technical Requirements..." style="flex:1;">
                <button type="button" class="btn btn-primary" onclick="saveNewSectionInline()"><i class="fas fa-plus"></i></button>
            </div>
        </div>
    </form>
    </div>
</div>

