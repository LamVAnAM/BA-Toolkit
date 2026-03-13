// assets/js/app.js

const __nativeFetch = window.fetch.bind(window);
window.fetch = (input, init = {}) => {
    const requestUrl = typeof input === 'string' ? input : (input && input.url) || '';
    const method = String(init.method || (typeof input !== 'string' && input.method) || 'GET').toUpperCase();
    const isSameOrigin = !/^https?:\/\//i.test(requestUrl) || requestUrl.startsWith(window.location.origin);

    if (isSameOrigin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined) || {});
        if (window.CSRF_TOKEN && !headers.has('X-CSRF-Token')) {
            headers.set('X-CSRF-Token', window.CSRF_TOKEN);
        }
        init.headers = headers;
    }

    return __nativeFetch(input, init);
};

function clearHeaderActions() {
    const container = document.getElementById('header-actions');
    if (container) container.innerHTML = '';
}

function buildModuleProcessMermaid(processes = []) {
    if (!Array.isArray(processes) || processes.length === 0) return '';
    let code = 'graph TD\n';
    processes.forEach((p, idx) => {
        const label = String(p.name || `Process ${idx + 1}`).replace(/["]/g, '').trim();
        const nodeId = `MP${idx}`;
        code += `  ${nodeId}["${wrapMermaidText(label, 36)}"]\n`;
        if (idx > 0) code += `  MP${idx - 1} --> ${nodeId}\n`;
    });
    return code;
}

function buildModuleEntityMermaid(entities = [], relationships = []) {
    if (!Array.isArray(entities) || entities.length === 0) return '';
    let code = 'erDiagram\n';
    entities.forEach((e) => {
        const name = String(e.name || '').trim().replace(/\s+/g, '_');
        if (!name) return;
        code += `  ${name} {\n`;
        const attrs = Array.isArray(e.attributes_detail) && e.attributes_detail.length > 0
            ? e.attributes_detail
            : String(e.attributes || '').split(',').map(x => ({ name: x.trim(), data_type: 'string' })).filter(Boolean);
        attrs.forEach((a) => {
            const an = String(a.name || a).trim().replace(/\s+/g, '_');
            if (!an) return;
            code += `    ${String(a.data_type || 'string').toLowerCase()} ${an}\n`;
        });
        code += `  }\n`;
    });
    (Array.isArray(relationships) ? relationships : []).forEach((r) => {
        const from = String(r.from_name || '').trim().replace(/\s+/g, '_');
        const to = String(r.to_name || '').trim().replace(/\s+/g, '_');
        if (!from || !to) return;
        const edge = String(r.relationship_type || '') === 'one-to-one'
            ? '||--||'
            : (String(r.relationship_type || '') === 'many-to-many' ? '}o--o{' : '||--o{');
        code += `  ${from} ${edge} ${to} : "${String(r.foreign_key || '').replace(/["]/g, '')}"\n`;
    });
    return code;
}

async function generateProjectReport() {
    const deptId = localStorage.getItem('current_dept_id');
    const deptName = localStorage.getItem('current_dept_name');
    if (!deptId) return;

    const [surveyRes, processRes, daRes, sectionFilesRes] = await Promise.all([
        fetch(`api/load_survey.php?department_id=${deptId}`),
        fetch(`api/manage_data.php?type=processes&department_id=${deptId}&action=load`),
        fetch(`api/data_architecture.php?action=list&department_id=${deptId}`),
        fetch(`api/section_files.php?department_id=${deptId}`)
    ]);

    const data = await surveyRes.json();
    const processDataRaw = await processRes.json();
    const daRaw = await daRes.json();
    const sectionFilesRaw = await sectionFilesRes.json();
    const processData = Array.isArray(processDataRaw) ? processDataRaw : [];
    const entityData = Array.isArray(daRaw.entities) ? daRaw.entities : [];
    const daRelationships = Array.isArray(daRaw.relationships) ? daRaw.relationships : [];
    const sectionFiles = Array.isArray(sectionFilesRaw.items) ? sectionFilesRaw.items : [];
    const sectionFilesMap = {};
    sectionFiles.forEach((it) => {
        const sid = String(it.section_id || '');
        if (!sectionFilesMap[sid]) sectionFilesMap[sid] = [];
        sectionFilesMap[sid].push(it);
    });

    let html = `
        <div style="text-align:center; border-bottom:3px solid var(--primary); padding-bottom:20px; margin-bottom:30px;">
            <h1 style="color:var(--primary); font-size:1.8rem;">${i18n('requirement_survey').toUpperCase()}</h1>
            <p style="color:#666; margin-top:5px;">${i18n('unit')}: <strong>${deptName}</strong></p>
            <p style="font-size:0.8rem; color:#999;">${i18n('created_at')}: ${new Date().toLocaleString()}</p>
        </div>
    `;

    // Map fields by section
    const sectionMap = {};
    if (data.fields) {
        data.fields.forEach(f => {
            if (!sectionMap[f.section_id]) sectionMap[f.section_id] = { name: f.section_name, fields: [] };
            sectionMap[f.section_id].fields.push(f);
        });
    }

    // Build 15 sections
    for (let i = 1; i <= 15; i++) {
        const section = sectionMap[i];
        if (!section && i !== 9 && i !== 10) continue; // Skip empty sections unless special

        html += `<div class="report-section">`;
        html += `<div class="report-title">${i}. ${section ? section.name : "Section " + i}</div>`;
        html += `<div class="report-content"><ul>`;

        if (section) {
            section.fields.forEach(f => {
                if (f.field_value) {
                    html += `<li><strong>${f.field_label}:</strong> ${f.field_value}</li>`;

                    // Inject Diagram into Report if field is visualizable
                    if (['process_asis', 'process_tobe', 'key_entities'].includes(f.field_key)) {
                        const diagramTitle = f.field_key.includes('asis') ? 'AS-IS Process' :
                            f.field_key.includes('tobe') ? 'TO-BE Process' :
                                'Data Structure';
                        html += `
                            <div class="report-diagram">
                                <div class="report-diagram-title"><i class="fas fa-project-diagram"></i> ${diagramTitle} Diagram</div>
                                <div class="mermaid">${generateMermaidCode(f.field_key, f.field_value)}</div>
                            </div>
                        `;
                    }
                }
            });
        }

        // Special handling for Section 9 (KPIs)
        if (i === 9 && data.kpis && data.kpis.length > 0) {
            html += `<li><strong>${i18n('sec_kpi')}:</strong></li>`;
            html += `<div style="margin-left:20px; padding:10px; background:#f1f8e9; border-left:4px solid #4caf50;">`;
            data.kpis.forEach(k => html += `<div>- ${k}</div>`);
            html += `</div>`;
        }

        // Special handling for Section 10 (Modules)
        if (i === 10 && data.modules && data.modules.length > 0) {
            html += `<li><strong>${i18n('sec_modules')}:</strong></li>`;
            html += `<div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">`;
            data.modules.forEach(m => html += `<span style="padding:4px 10px; background:#e3f2fd; color:#1565c0; border-radius:15px; font-size:0.85rem;">${m}</span>`);
            html += `</div>`;
        }

        const images = sectionFilesMap[String(i)] || [];
        if (images.length > 0) {
            html += `<div style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:10px;">`;
            images.forEach(img => {
                const safe = String(img.original_name || 'image').replace(/</g, '&lt;');
                html += `<div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; background:#fff;">
                    <img src="${img.public_url}" alt="${safe}" style="width:100%; height:110px; object-fit:cover; display:block;">
                    <div style="padding:6px 8px; font-size:0.75rem; color:#666;">${safe}</div>
                </div>`;
            });
            html += `</div>`;
        }

        html += `</ul></div></div>`;
    }

    if (Array.isArray(processData) && processData.length > 0) {
        const processCode = buildModuleProcessMermaid(processData);
        if (processCode) {
            html += `
                <div class="report-section">
                    <div class="report-title">Module Process Mapping Diagram</div>
                    <div class="report-content">
                        <div class="mermaid">${processCode}</div>
                    </div>
                </div>
            `;
        }
    }

    if (Array.isArray(entityData) && entityData.length > 0) {
        const entityCode = buildModuleEntityMermaid(entityData, daRelationships);
        if (entityCode) {
            html += `
                <div class="report-section">
                    <div class="report-title">Module Data Architecture Diagram</div>
                    <div class="report-content">
                        <div class="mermaid">${entityCode}</div>
                    </div>
                </div>
            `;
        }
    }

    document.getElementById('reportContent').innerHTML = html;
    document.getElementById('reportModal').style.display = 'flex';

    // Run Mermaid after DOM update
    if (window.mermaid) {
        setTimeout(() => {
            mermaid.run({ nodes: document.querySelectorAll('#reportContent .mermaid') });
        }, 300);
    }
}

function exportPDF() {
    const element = document.getElementById('reportContent');
    const opt = {
        margin: 10,
        filename: `BA_Report_${localStorage.getItem('current_dept_name') || 'Export'}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}

function exportDOCX() {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId) {
        showToast('Please select a department first.');
        return;
    }
    window.open(`api/export_report_docx.php?department_id=${encodeURIComponent(deptId)}`, '_blank');
}

function copyReport() {
    const content = document.getElementById('reportContent').innerText;
    navigator.clipboard.writeText(content).then(() => showToast('Copied to clipboard!'));
}

document.addEventListener('DOMContentLoaded', () => {
    loadPublicConfig();
    loadView('dashboard');
});

async function loadView(viewName) {
    const contentArea = document.getElementById('content-area');
    const viewTitle = document.getElementById('view-title');
    const headerActions = document.getElementById('header-actions');

    // Update active menu item
    document.querySelectorAll('.menu-item, .nav-link').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('onclick')?.includes(viewName)) {
            item.classList.add('active');
        }
    });
    closeSidebar();

    // Clear previous view
    contentArea.innerHTML = '<div style="text-align:center; padding:50px;">Loading...</div>';
    headerActions.innerHTML = '';

    try {
        const response = await fetch(`modules/${viewName}.php`);
        if (!response.ok) throw new Error('View not found');
        const html = await response.text();
        contentArea.innerHTML = html;

        // Update Title & Header Actions
        switch (viewName) {
            case 'dashboard':
                viewTitle.innerText = i18n('dashboard');
                initDashboard();
                break;
            case 'organization':
                viewTitle.innerText = i18n('departments');
                headerActions.innerHTML = `<button class="btn btn-primary" onclick="showAddDeptModal()">${i18n('add_dept')}</button>`;
                initOrganization();
                break;
            case 'survey':
                viewTitle.innerText = i18n('survey_form');
                headerActions.innerHTML = `<button class="btn btn-success" onclick="saveSurveyData()"><i class="fas fa-floppy-disk"></i> ${i18n('save')}</button>`;
                initSurvey();
                break;
            case 'process_mapping':
                viewTitle.innerText = i18n('analysis_design');
                initProcessMapping();
                break;
            case 'data_architecture':
                viewTitle.innerText = i18n('analysis_design');
                initDataArchitecture();
                break;
            case 'integration':
                viewTitle.innerText = i18n('tracking_reports');
                initIntegration();
                break;
            case 'backlog':
                viewTitle.innerText = i18n('tracking_reports');
                initBacklog();
                break;
            case 'reports':
                viewTitle.innerText = i18n('reports');
                break;
            case 'settings':
                viewTitle.innerText = i18n('configuration');
                initSettings();
                break;
            case 'ai_toolkit':
                viewTitle.innerText = "AI Toolkit";
                initAiToolkit();
                break;
            case 'admin_users':
                viewTitle.innerText = 'User Management';
                initAdminUsers();
                break;
            case 'profile':
                viewTitle.innerText = 'User Profile';
                initUserProfile();
                break;

            default:
                viewTitle.innerText = i18n(viewName);
        }
    } catch (error) {
        contentArea.innerHTML = `<div style="color:var(--danger); padding:20px;">Error: ${error.message}</div>`;
    }
}

// Survey Helpers
function scrollToSection(id) {
    const el = document.getElementById(`section-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });

        // Update TOC active state
        document.querySelectorAll('.toc-item').forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('onclick')?.includes(`(${id})`)) {
                item.classList.add('active');
            }
        });
    }
}

function updateSurveyProgress() {
    const sections = Array.from(document.querySelectorAll('#surveyForm .section'));
    const total = sections.length;
    let filled = 0;
    document.querySelectorAll('.toc-item').forEach(item => item.classList.remove('completed'));

    sections.forEach((section) => {
        const inputs = section.querySelectorAll('[data-key]');
        const isFilled = Array.from(inputs).some(input => input.value && input.value.trim() !== '');
        const sectionId = (section.id || '').replace('section-', '');

        if (isFilled) {
            filled++;
            const tocItem = document.querySelector(`.toc-item[data-section-id="${sectionId}"]`);
            if (tocItem) tocItem.classList.add('completed');
        }
    });

    const progEl = document.getElementById('section-progress');
    if (progEl) progEl.innerText = `${filled}/${total || 0}`;
}

// Global UI Helpers
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
}

function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.innerText = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function closeModal() {
    const reportModal = document.getElementById('reportModal');
    const mainModal = document.getElementById('mainModal');
    if (reportModal) reportModal.style.display = 'none';
    if (mainModal) mainModal.style.display = 'none';
}

function handleAiApiMissingKey(data) {
    if (!data || data.error_code !== 'MISSING_AI_KEY') return false;
    showToast(data.error || 'AI API key is not configured.');
    const html = `
        <p style="margin-bottom:10px;">You have not configured an AI API key yet.</p>
        <ol style="margin-left:18px;">
            <li>Open the <strong>AI API Key</strong> page</li>
            <li>Generate a key at <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a></li>
            <li>Paste the key in the format <code>gsk_...</code> and save</li>
        </ol>
        <div style="text-align:right; margin-top:12px;">
            <button class="btn btn-primary" onclick="closeMainModal(); loadView('ai_toolkit');">Open AI API Key</button>
        </div>
    `;
    showModal('Missing API Key', html);
    return true;
}



async function loadOllamaModels() {
    const endpoint = (document.getElementById('user-ollama-endpoint')?.value || '').trim();
    if (!endpoint) return showToast('Please enter Ollama endpoint first.');
    try {
        const response = await fetch('api/ai_tools.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'ollama_models', ai_endpoint: endpoint })
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Cannot load Ollama models');

        const select = document.getElementById('user-ollama-model');
        if (!select) return;
        const current = select.value;
        const models = Array.isArray(data.models) ? data.models : [];
        if (models.length === 0) {
            showToast('No models found on Ollama. Pull model first: ollama pull llama3.1:8b');
            return;
        }
        select.innerHTML = models.map(m => `<option value="${m}">${m}</option>`).join('');
        if (models.includes(current)) select.value = current;
        showToast(`Loaded ${models.length} model(s) from Ollama.`);
    } catch (e) {
        showToast('Ollama model list error: ' + e.message);
    }
}

async function testUserAiConfig() {
    const provider = document.getElementById('user-ai-provider')?.value || 'groq';
    const payload = { action: 'test', ai_provider: provider };
    if (provider === 'groq') {
        payload.groq_api_key = (document.getElementById('user-groq-key')?.value || '').trim();
        payload.groq_model = (document.getElementById('user-groq-model')?.value || '').trim();
        if (!payload.groq_api_key) return showToast('Enter Groq API key to test.');
    } else {
        payload.ai_endpoint = (document.getElementById('user-ollama-endpoint')?.value || '').trim();
        payload.ai_model = (document.getElementById('user-ollama-model')?.value || '').trim();
        if (!payload.ai_endpoint || !payload.ai_model) return showToast('Enter Ollama endpoint/model to test.');
    }

    try {
        const response = await fetch('api/ai_tools.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'AI test failed');
        showToast(`AI test OK (${data.model}, ${data.latency_ms}ms)`);
    } catch (e) {
        showToast('AI test error: ' + e.message);
    }
}

async function initAdminUsers() {
    await loadAdminUsers('pending');
}

async function loadAdminUsers(status = 'all') {
    const tbody = document.getElementById('admin-user-table-body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#666;">Loading...</td></tr>';

    try {
        const response = await fetch(`api/admin_users.php?status=${encodeURIComponent(status)}`);
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Failed to load users');

        const users = Array.isArray(data.users) ? data.users : [];
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#666;">No users found.</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(u => `
            <tr>
                <td>${u.id}</td>
                <td>${u.username || ''}</td>
                <td>${u.full_name || ''}</td>
                <td>${u.email || ''}</td>
                <td>${u.role || 'user'}</td>
                <td>${Number(u.is_approved) === 1 ? 'Approved' : 'Pending'}</td>
                <td>${u.created_at ? new Date(u.created_at).toLocaleString() : ''}</td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button class="btn btn-sm ${Number(u.is_approved) === 1 ? 'btn-outline' : 'btn-success'}" onclick="adminSetApproval(${u.id}, ${Number(u.is_approved) === 1 ? 0 : 1})">
                            ${Number(u.is_approved) === 1 ? 'Revoke' : 'Approve'}
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="adminSetRole(${u.id}, '${u.role === 'admin' ? 'user' : 'admin'}')">
                            Make ${u.role === 'admin' ? 'User' : 'Admin'}
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--danger);">${e.message}</td></tr>`;
    }
}

async function initUserProfile() {
    try {
        const response = await fetch('api/profile.php');
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Failed to load profile');

        const user = data.user || {};
        const mappings = {
            'profile-username': user.username || '',
            'profile-role': user.role || '',
            'profile-full-name': user.full_name || '',
            'profile-email': user.email || '',
            'profile-provider': user.oauth_provider || 'local',
            'profile-created-at': user.created_at ? new Date(user.created_at).toLocaleString() : ''
        };

        Object.entries(mappings).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.value = value;
        });

        const note = document.getElementById('profile-password-note');
        if (note) {
            note.innerText = user.oauth_provider === 'google'
                ? 'Google sign-in account cannot change password here.'
                : 'Use your current password to set a new password.';
        }
    } catch (e) {
        showToast('Profile load error: ' + e.message);
    }
}

async function saveUserProfile() {
    const fullName = document.getElementById('profile-full-name')?.value.trim() || '';
    const email = document.getElementById('profile-email')?.value.trim() || '';

    try {
        const response = await fetch('api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_profile', full_name: fullName, email })
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Profile update failed');
        showToast(data.message || 'Profile updated.');
        await initUserProfile();
    } catch (e) {
        showToast('Profile update error: ' + e.message);
    }
}

async function changeUserPassword() {
    const currentPassword = document.getElementById('profile-current-password')?.value || '';
    const newPassword = document.getElementById('profile-new-password')?.value || '';
    const confirmPassword = document.getElementById('profile-confirm-password')?.value || '';

    try {
        const response = await fetch('api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'change_password',
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            })
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Password update failed');
        showToast(data.message || 'Password changed.');
        ['profile-current-password', 'profile-new-password', 'profile-confirm-password'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    } catch (e) {
        showToast('Password update error: ' + e.message);
    }
}

async function adminSetApproval(userId, approved) {
    const action = approved ? 'approve' : 'revoke';
    try {
        const response = await fetch('api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, user_id: userId })
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Update failed');
        showToast('User updated.');
        loadAdminUsers('all');
    } catch (e) {
        showToast(e.message);
    }
}

async function adminSetRole(userId, role) {
    try {
        const response = await fetch('api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_role', user_id: userId, role })
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Role update failed');
        showToast('Role updated.');
        loadAdminUsers('all');
    } catch (e) {
        showToast(e.message);
    }
}

// Placeholders for modular JS - will be implemented in detail
async function initDashboard() {
    try {
        const response = await fetch('api/departments.php');
        const depts = await response.json();
        renderDeptCards(depts);
    } catch (e) {
        console.error('Failed to load dashboard:', e);
    }
}

function renderDeptCards(depts) {
    const list = document.getElementById('dept-card-list');
    if (!list) return;

    if (!depts || depts.length === 0) {
        list.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:80px; color:var(--text-disabled);">
            <i class="fas fa-folder-open" style="font-size:3rem; margin-bottom:16px; opacity:0.3;"></i>
            <p>${i18n('select_unit_hint')}</p>
        </div>`;
        return;
    }

    list.innerHTML = depts.map(d => `
        <div class="card" onclick="startSurvey(${d.id}, '${d.name}')">
            <div class="card-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="card-content">
                <h3>${d.name}</h3>
                <div class="card-meta">
                    <span><i class="fas fa-user-tie"></i> ${d.sponsor || 'No Sponsor'}</span>
                    <span><i class="fas fa-clock"></i> ${new Date(d.created_at).toLocaleDateString()}</span>
                </div>
            </div>
            <div class="card-chevron">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    `).join('');
}

function filterDepts() {
    const query = document.getElementById('dept-search')?.value.toLowerCase() || '';
    fetch('api/departments.php')
        .then(r => r.json())
        .then(depts => {
            const filtered = depts.filter(d =>
                d.name.toLowerCase().includes(query) ||
                (d.sponsor && d.sponsor.toLowerCase().includes(query))
            );
            renderDeptCards(filtered);
        });
}

function startSurvey(deptId, deptName) {
    localStorage.setItem('current_dept_id', deptId);
    localStorage.setItem('current_dept_name', deptName);
    loadView('survey');
}

/**
 * SECTIONS BAR (Survey Navigation - Restored to Vertical TOC)
 */
function renderSectionsBar() {
    const bar = document.getElementById('section-toc');
    if (!bar) return;

    const sections = Array.from(document.querySelectorAll('.section'));
    bar.innerHTML = sections.map((sec, idx) => {
        const id = (sec.id || '').replace('section-', '');
        const title = sec.querySelector('h2')?.innerText || 'Section';
        return `
            <li class="toc-item" id="nav-item-${id}" data-section-id="${id}" onclick="scrollToSection('${id}')" title="${title}">
                <span>${idx + 1}</span> ${title}
            </li>
        `;
    }).join('');

    updateSurveyProgress();
    updateActiveSection();
}

function scrollToSection(id) {
    const el = document.getElementById(`section-${id}`);
    if (el) {
        // Offset for sticky topbar
        const yOffset = -100;
        const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset;
        window.scrollTo({ top: y, behavior: 'smooth' });
    }
}

let scrollTimeout;
function updateActiveSection() {
    const sections = document.querySelectorAll('.section');
    const bar = document.getElementById('section-toc');
    if (!bar || sections.length === 0) return;

    let current = '';
    const scrollPos = window.pageYOffset + 200; // Trigger slightly early

    sections.forEach(sec => {
        if (scrollPos >= sec.offsetTop) {
            current = (sec.id || '').replace('section-', '');
        }
    });

    if (!current && sections.length > 0) current = sections[0].id.replace('section-', '');

    document.querySelectorAll('.toc-item').forEach(item => {
        item.classList.remove('active');
        if (item.id === `nav-item-${current}`) {
            item.classList.add('active');
        }
    });
}

// Global scroll listener for survey
window.addEventListener('scroll', () => {
    if (document.getElementById('section-toc')) {
        updateActiveSection();
    }
});


async function initSurvey() {
    const deptId = localStorage.getItem('current_dept_id');
    const deptName = localStorage.getItem('current_dept_name');
    if (!deptId) return loadView('dashboard');

    document.getElementById('current-dept-name').innerText = deptName;
    document.getElementById('kpi-dynamic-list').innerHTML = '';

    await loadSurveyData(deptId);
    ensureSectionImageSlots();
    await loadSectionFilesForSurvey(deptId);
    renderSectionsBar();
    updateSurveyProgress();
}

async function loadSurveyData(deptId) {
    const response = await fetch(`api/load_survey.php?department_id=${deptId}`);
    const data = await response.json();

    // Reset current UI state before repopulating
    document.querySelectorAll('#surveyForm [data-key]').forEach(input => {
        input.value = '';
        delete input.dataset.rawValue;
        delete input.dataset.isNormalized;
    });
    document.querySelectorAll('input[name="module"]').forEach(cb => { cb.checked = false; });
    document.querySelectorAll('#module-checkboxes .custom-module-item').forEach(el => el.remove());
    const kpiList = document.getElementById('kpi-dynamic-list');
    if (kpiList) kpiList.innerHTML = '';

    // Group fields by section first to handle dynamic sections
    const sectionGroups = {};
    if (data.fields) {
        data.fields.forEach(f => {
            if (!sectionGroups[f.section_id]) sectionGroups[f.section_id] = { name: f.section_name, fields: [] };
            sectionGroups[f.section_id].fields.push(f);
        });
    }

    // Render/Populate sections
    for (const sectionId in sectionGroups) {
        let sectionEl = document.getElementById(`section-${sectionId}`);
        if (!sectionEl && isNaN(sectionId) === false) {
            // It's a numerical ID but not in HTML? Might be a legacy or custom section
            sectionEl = createDynamicSection(sectionId, sectionGroups[sectionId].name);
        } else if (!sectionEl) {
            // Custom section with non-numeric ID
            sectionEl = createDynamicSection(sectionId, sectionGroups[sectionId].name);
        }

        sectionGroups[sectionId].fields.forEach(f => {
            let input = sectionEl.querySelector(`[data-key="${f.field_key}"]`);
            if (!input) {
                input = createDynamicField(sectionId, f.field_key, f.field_label);
            }
            if (input) {
                const preferredValue = f.normalized_value || f.raw_value || f.field_value || '';
                input.value = preferredValue;
                input.dataset.rawValue = f.raw_value || f.field_value || '';
                if ((f.normalization_state || '').toLowerCase() === 'normalized' && (f.normalized_value || '').trim() !== '') {
                    input.dataset.isNormalized = '1';
                } else {
                    delete input.dataset.isNormalized;
                }

                // Auto-render diagrams after loading
                if (['process_asis', 'process_tobe', 'key_entities'].includes(f.field_key)) {
                    renderSurveyDiagram(sectionId, f.field_key, preferredValue);
                    // Bind real-time rendering
                    input.addEventListener('input', (e) => {
                        renderSurveyDiagram(sectionId, f.field_key, e.target.value);
                    });
                }
            }
        });
    }

    if (data.modules) {
        data.modules.forEach(m => {
            const cb = document.querySelector(`input[name="module"][value="${m}"]`);
            if (cb) {
                cb.checked = true;
            } else {
                appendCustomModuleOption(m, true);
            }
        });
    }

    if (data.kpis) {
        data.kpis.forEach(k => addSurveyKPI(k));
    }
}

async function saveSurveyData() {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId) return false;

    const surveyData = {
        department_id: deptId,
        sections: [],
        modules: [],
        kpis: []
    };

    // Collect all sections
    document.querySelectorAll('.section').forEach(sectionEl => {
        const sectionId = sectionEl.id.replace('section-', '');
        const sectionName = sectionEl.querySelector('h2').innerText;

        const sectionData = {
            id: sectionId,
            name: sectionName,
            fields: []
        };

        const inputs = sectionEl.querySelectorAll('[data-key]');
        inputs.forEach(input => {
            // Find label
            let labelText = '';
            if (input.previousElementSibling && input.previousElementSibling.tagName === 'LABEL') {
                labelText = input.previousElementSibling.innerText;
            } else if (input.closest('.form-group')?.querySelector('label')) {
                labelText = input.closest('.form-group').querySelector('label').innerText;
            }

            const currentValue = input.value || '';
            const isNormalized = input.dataset.isNormalized === '1';
            const rawValue = isNormalized ? (input.dataset.rawValue || currentValue) : currentValue;
            const normalizedValue = isNormalized ? currentValue : null;

            sectionData.fields.push({
                key: input.getAttribute('data-key'),
                label: labelText,
                value: currentValue,
                raw_value: rawValue,
                normalized_value: normalizedValue,
                normalization_state: isNormalized ? 'normalized' : 'raw'
            });
        });

        if (sectionData.fields.length > 0 || sectionId === '9' || sectionId === '10') {
            surveyData.sections.push(sectionData);
        }
    });

    // Collect modules
    document.querySelectorAll('input[name="module"]:checked').forEach(cb => {
        surveyData.modules.push(cb.value);
    });

    // Collect KPIs
    document.querySelectorAll('.kpi-item-input').forEach(input => {
        if (input.value) surveyData.kpis.push(input.value);
    });

    try {
        const response = await fetch('api/save_survey.php', {
            method: 'POST',
            body: JSON.stringify(surveyData),
            headers: { 'Content-Type': 'application/json' }
        });
        const res = await response.json();
        if (res.status === 'success') {
            showToast('Survey saved successfully!');
            return true;
        } else {
            throw new Error(res.error);
        }
    } catch (e) {
        showToast('Error: ' + e.message);
        return false;
    }
}

function addSurveyKPI(val = '') {
    const list = document.getElementById('kpi-dynamic-list');
    if (!list) return;
    const div = document.createElement('div');
    div.className = 'dynamic-item';
    div.innerHTML = `
        <button type="button" class="btn-rm" onclick="this.parentElement.remove()">x</button>
        <input class="kpi-item-input" value="${val}" placeholder="Enter KPI name...">
    `;
    list.appendChild(div);
}

function appendCustomModuleOption(name, checked = true) {
    const moduleName = String(name || '').trim();
    if (!moduleName) return;

    const existing = Array.from(document.querySelectorAll('#module-checkboxes input[name="module"]'))
        .find(input => String(input.value).trim().toLowerCase() === moduleName.toLowerCase());
    if (existing) {
        existing.checked = checked;
        return;
    }

    const container = document.getElementById('module-checkboxes');
    if (!container) return;

    const label = document.createElement('label');
    label.className = 'cb-item custom-module-item';
    label.innerHTML = `
        <input type="checkbox" name="module" value="${moduleName.replace(/"/g, '&quot;')}" ${checked ? 'checked' : ''}>
        <span>${moduleName}</span>
        <button type="button" class="btn-rm module-chip-remove" onclick="this.closest('label').remove()">x</button>
    `;
    container.appendChild(label);
}

function addCustomModule() {
    const input = document.getElementById('custom-module-input');
    if (!input) return;

    const moduleName = input.value.trim();
    if (!moduleName) {
        showToast('Please enter a module name.');
        return;
    }

    appendCustomModuleOption(moduleName, true);
    input.value = '';
    input.focus();
}

// DYNAMIC SURVEY HELPERS
function addNewField(sectionId) {
    // This is now handled inline by saveNewFieldInline
    const container = document.getElementById(`field-add-${sectionId}`);
    if (container) {
        const input = container.querySelector('input');
        if (input) input.focus();
    }
}

function saveNewFieldInline(sectionId) {
    const container = document.getElementById(`field-add-${sectionId}`);
    const input = container.querySelector('input');
    const label = input.value.trim();
    if (!label) return;

    const key = label.toLowerCase().replace(/[^a-z0-9]/g, '_') + '_' + Date.now();
    createDynamicField(sectionId, key, label);
    input.value = ''; // Clear for next one
    updateSurveyProgress();
}

function createDynamicField(sectionId, key, label) {
    const sectionEl = document.getElementById(`section-${sectionId}`);
    let container = sectionEl.querySelector('.form-grid') || sectionEl.querySelector('.form-group')?.parentElement;

    if (!container) {
        container = document.createElement('div');
        container.className = 'form-grid';
        sectionEl.appendChild(container);
    }

    const div = document.createElement('div');
    div.className = 'form-group';
    div.innerHTML = `
        <label>${label}</label>
        <div style="display:flex; gap:10px; align-items:center;">
            <div class="ai-input-wrapper" style="flex:1;">
                <input type="text" data-section="${sectionId}" data-key="${key}" value="" style="width:100%;">
                <button type="button" class="btn-ai-inline" onclick="normalizeSection('${sectionId}', '${key}')"><i class="fas fa-magic"></i></button>
            </div>
            <button type="button" class="btn-rm" style="position:static;" onclick="this.closest('.form-group').remove()">x</button>
        </div>
    `;
    container.appendChild(div);
    const createdInput = div.querySelector('input');
    createdInput?.addEventListener('input', updateSurveyProgress);
    return createdInput;
}

function addNewSection() {
    const input = document.getElementById('new-section-name');
    if (input) {
        input.scrollIntoView({ behavior: 'smooth' });
        input.focus();
    }
}

function saveNewSectionInline() {
    const input = document.getElementById('new-section-name');
    const name = input.value.trim();
    if (!name) return;

    const id = 'custom_' + Date.now();
    createDynamicSection(id, name);
    input.value = ''; // Clear

    renderSectionsBar();
    updateSurveyProgress();

    // Scroll to new section
    const newSection = document.getElementById(`section-${id}`);
    if (newSection) newSection.scrollIntoView({ behavior: 'smooth' });
}

function createDynamicSection(id, name) {
    const container = document.getElementById('dynamic-sections-container');
    const div = document.createElement('div');
    div.className = 'section';
    div.id = `section-${id}`;
    div.innerHTML = `
        <div class="section-header">
            <h2>${name}</h2>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-rm" style="position:static; font-size:1.2rem; color:var(--danger);" onclick="this.closest('.section').remove(); renderSectionsBar(); updateSurveyProgress();">x</button>
            </div>
        </div>
        <div class="form-grid"></div>
        <div class="field-add-row" id="field-add-${id}">
            <input type="text" placeholder="${i18n('add_field')}..." class="new-field-label">
            <button type="button" class="btn btn-primary btn-sm" onclick="saveNewFieldInline('${id}')">+</button>
        </div>
    `;
    container.appendChild(div);
    ensureSingleSectionImageSlot(div, id);
    return div;
}

// MODAL HELPERS
function showModal(title, html) {
    const modal = document.getElementById('mainModal');
    const body = modal.querySelector('.modal-body');
    modal.querySelector('h2').innerText = title;
    body.innerHTML = html;
    modal.style.display = 'flex';
}

function closeMainModal() {
    const overlay = document.getElementById('mainModal');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.querySelector('.modal')?.classList.remove('modal-fullscreen');
    }
}

function toggleAiIntakeFullscreen() {
    const modal = document.querySelector('#mainModal .modal');
    if (!modal) return;
    modal.classList.toggle('modal-fullscreen');
}

function ensureSectionImageSlots() {
    document.querySelectorAll('#surveyForm .section').forEach(sectionEl => {
        const sectionId = (sectionEl.id || '').replace('section-', '');
        ensureSingleSectionImageSlot(sectionEl, sectionId);
    });
}

function ensureSingleSectionImageSlot(sectionEl, sectionId) {
    if (!sectionEl || !sectionId) return;
    if (!sectionEl.querySelector(`.section-upload-wrap[data-section-upload="${sectionId}"]`)) {
        const wrap = document.createElement('div');
        wrap.className = 'section-upload-wrap';
        wrap.setAttribute('data-section-upload', sectionId);
        wrap.innerHTML = `
            <div class="section-upload-toolbar">
                <strong>Illustration Images</strong>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="file" id="section-image-input-${sectionId}" accept="image/png,image/jpeg,image/webp" style="display:none;" onchange="handleSectionImageUpload('${sectionId}', this)">
                    <button type="button" class="btn btn-outline btn-sm" onclick="triggerSectionImageUpload('${sectionId}')"><i class="fas fa-image"></i> Upload</button>
                </div>
            </div>
            <div class="section-images-grid" id="section-images-${sectionId}"></div>
        `;
        sectionEl.appendChild(wrap);
    }
}

function triggerSectionImageUpload(sectionId) {
    const input = document.getElementById(`section-image-input-${sectionId}`);
    if (input) input.click();
}

async function handleSectionImageUpload(sectionId, input) {
    const deptId = localStorage.getItem('current_dept_id');
    const file = input?.files?.[0];
    if (!deptId || !file) return;

    const fd = new FormData();
    fd.append('department_id', String(deptId));
    fd.append('section_id', String(sectionId));
    fd.append('image', file);

    try {
        const res = await fetch('api/upload_section_image.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Upload failed');
        showToast('Image uploaded successfully.');
        await loadSectionFilesForSurvey(deptId);
    } catch (e) {
        showToast('Upload error: ' + e.message);
    } finally {
        if (input) input.value = '';
    }
}

async function loadSectionFilesForSurvey(deptId) {
    try {
        const res = await fetch(`api/section_files.php?department_id=${encodeURIComponent(deptId)}`);
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Cannot load section files');

        const items = Array.isArray(data.items) ? data.items : [];
        const grouped = {};
        items.forEach(item => {
            const sid = String(item.section_id || '');
            if (!grouped[sid]) grouped[sid] = [];
            grouped[sid].push(item);
        });

        document.querySelectorAll('#surveyForm .section').forEach(sectionEl => {
            const sectionId = (sectionEl.id || '').replace('section-', '');
            const box = document.getElementById(`section-images-${sectionId}`);
            if (!box) return;
            const list = grouped[sectionId] || [];
            if (list.length === 0) {
                box.innerHTML = '<div style="font-size:0.85rem; color:#777;">No image uploaded for this section.</div>';
                return;
            }

            box.innerHTML = list.map(item => {
                const safe = String(item.original_name || 'image').replace(/</g, '&lt;');
                const created = item.created_at ? new Date(item.created_at).toLocaleString() : '';
                return `
                    <div class="section-image-card">
                        <img src="${item.public_url}" alt="${safe}">
                        <div class="section-image-meta">
                            <div title="${safe}">${safe}</div>
                            <div>${created}</div>
                            <div class="section-image-actions">
                                <button class="btn btn-outline btn-sm" type="button" onclick="window.open('${item.public_url}','_blank')">View</button>
                                <button class="btn btn-danger btn-sm" type="button" onclick="deleteSectionFile(${item.id})">Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        });
    } catch (e) {
        showToast('Load section images error: ' + e.message);
    }
}

async function deleteSectionFile(id) {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId || !id) return;
    if (!confirm('Delete this image?')) return;

    try {
        const res = await fetch('api/section_files.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, department_id: Number(deptId) })
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'Delete failed');
        showToast('Image deleted.');
        await loadSectionFilesForSurvey(deptId);
    } catch (e) {
        showToast('Delete error: ' + e.message);
    }
}
// GENERIC DATA HELPERS
async function manageData(type, action = 'load', data = null) {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId) return [];

    const url = `api/manage_data.php?type=${type}&department_id=${deptId}&action=${action}`;
    const options = {
        method: action === 'load' ? 'GET' : 'POST',
        headers: { 'Content-Type': 'application/json' }
    };
    if (data) options.body = JSON.stringify(data);

    const response = await fetch(url, options);
    return await response.json();
}

// Module Initialization Functions
async function initProcessMapping() {
    const data = await manageData('processes');
    const list = document.getElementById('process-list');
    list.innerHTML = data.map(d => `
        <tr>
            <td>${d.name}</td>
            <td><span class="tag">${d.type}</span></td>
            <td style="font-size:0.85rem; color:#666;">${d.steps || '-'}</td>
            <td>
                <button class="btn-rm" onclick="deleteModuleData('processes', ${d.id}, initProcessMapping)"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="4" style="text-align:center; padding:20px;">No processes defined.</td></tr>';

    // Auto-render diagram if container exists
    renderProcessDiagram(data);
}

function showAddProcessModal() {
    const html = `
        <div class="form-grid">
            <div class="form-group full">
                <label>${i18n('process_name')}</label>
                <input type="text" id="p_name">
            </div>
            <div class="form-group">
                <label>${i18n('process_type')}</label>
                <select id="p_type">
                    <option value="AS-IS">AS-IS (Current)</option>
                    <option value="TO-BE">TO-BE (Future)</option>
                </select>
            </div>
            <div class="form-group full">
                <label>${i18n('process_steps')}</label>
                <textarea id="p_steps" rows="3" placeholder="Step 1, Step 2..."></textarea>
            </div>
        </div>
        <div style="text-align:right; margin-top:20px;">
            <button class="btn btn-outline" onclick="closeMainModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveProcess()">Save</button>
        </div>
    `;
    showModal(i18n('add_process'), html);
}

async function saveProcess() {
    const data = {
        name: document.getElementById('p_name').value,
        type: document.getElementById('p_type').value,
        steps: document.getElementById('p_steps').value
    };
    await manageData('processes', 'save', data);
    closeMainModal();
    initProcessMapping();
}

function renderProcessDiagram(processes) {
    const container = document.getElementById('process-diagram-preview');
    if (!container || processes.length === 0) return;

    let code = 'graph TD\n';
    processes.forEach((p, index) => {
        const next = processes[index + 1];
        const label = wrapMermaidText(String(p.name || '').replace(/["()]/g, ''), 34);
        code += `  P${p.id}["${label} (${p.type})"]\n`;
        if (next) {
            code += `  P${p.id} --> P${next.id}\n`;
        }
    });

    container.innerHTML = `<div class="mermaid">${code}</div>`;
    mermaid.run({ nodes: [container.querySelector('.mermaid')] });
}

let __daState = { entities: [], relationships: [], zoom: 1 };

function daEsc(value) {
    return String(value ?? '').replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
}

async function daApi(action = 'list', payload = null, method = 'GET') {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId) throw new Error('Please select a department first.');
    const isGet = method === 'GET';
    const url = `api/data_architecture.php?action=${encodeURIComponent(action)}&department_id=${encodeURIComponent(deptId)}`;
    const res = await fetch(url, isGet ? {} : {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ department_id: Number(deptId) }, payload || {}))
    });
    const data = await res.json();
    if (!res.ok || data.error) {
        if (handleAiApiMissingKey(data)) throw new Error('Missing key');
        throw new Error(data.error || 'Data Architecture API failed');
    }
    return data;
}

async function initDataArchitecture() {
    const data = await daApi('list');
    __daState.entities = Array.isArray(data.entities) ? data.entities : [];
    __daState.relationships = Array.isArray(data.relationships) ? data.relationships : [];
    renderEntityList(__daState.entities);
    renderRelationshipList(__daState.relationships);
    renderDataDictionary(__daState.entities);
    renderQualityMetrics(data.quality || {});
    renderDataDiagram(__daState.entities, __daState.relationships);
}

function applyEntityFilters() {
    const q = (document.getElementById('entity-search-input')?.value || '').toLowerCase().trim();
    const type = (document.getElementById('entity-type-filter')?.value || '').toLowerCase();
    const entities = (__daState.entities || []).filter(e => {
        const text = `${e.name || ''} ${e.attributes || ''} ${e.description || ''} ${e.data_source || ''}`.toLowerCase();
        const okSearch = !q || text.includes(q);
        const okType = !type || String(e.entity_type || '').toLowerCase() === type;
        return okSearch && okType;
    });
    renderEntityList(entities);
    renderDataDiagram(entities, __daState.relationships || []);
}

function renderQualityMetrics(q) {
    const statEntities = document.getElementById('da-stat-entities');
    const statAttrs = document.getElementById('da-stat-attrs');
    const statPk = document.getElementById('da-stat-pk');
    const statFk = document.getElementById('da-stat-fk');
    const statNull = document.getElementById('da-stat-null');
    const countEl = document.getElementById('da-entity-count');

    if (statEntities) statEntities.textContent = q.entities || 0;
    if (statAttrs) statAttrs.textContent = q.attributes_total || 0;
    if (statPk) statPk.textContent = q.primary_keys || 0;
    if (statFk) statFk.textContent = q.foreign_keys || 0;
    if (statNull) statNull.textContent = (q.nullable_pct || 0) + '%';
    if (countEl) countEl.textContent = (q.entities || 0) + ' entities';
}

function renderEntityList(entities) {
    const list = document.getElementById('entity-list');
    if (!list) return;

    if (!entities || entities.length === 0) {
        list.innerHTML = `
            <div class="da-empty-state">
                <i class="fas fa-database"></i>
                <h4>Chưa có entity nào</h4>
                <p>Thêm mới hoặc chọn template để bắt đầu</p>
                <button class="btn btn-primary" onclick="showDaTemplates()">
                    <i class="fas fa-magic"></i> Chọn template
                </button>
            </div>
        `;
        return;
    }

    const iconMap = { master: 'fa-database', transaction: 'fa-exchange-alt', lookup: 'fa-search' };

    list.innerHTML = entities.map(d => {
        const type = d.entity_type || 'master';
        const icon = iconMap[type] || 'fa-database';
        const attrs = Array.isArray(d.attributes_detail) ? d.attributes_detail : [];
        const attrPreview = attrs.slice(0, 3).map(a => `<span class="da-attr-chip ${a.is_primary_key ? 'pk' : (a.is_foreign_key ? 'fk' : '')}">${daEsc(a.name)}<span class="type">${a.data_type}</span></span>`).join('');
        const moreAttrs = attrs.length > 3 ? `<span class="da-attr-chip">+${attrs.length - 3} more</span>` : '';

        return `
            <div class="da-entity-row">
                <div class="da-entity-icon ${type}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="da-entity-info">
                    <div class="da-entity-name">
                        ${daEsc(d.name)}
                        <span class="tag ${type}">${type === 'master' ? 'Master' : (type === 'transaction' ? 'Transaction' : 'Lookup')}</span>
                    </div>
                    <div class="da-entity-meta">
                        ${d.data_source ? 'Nguồn: ' + daEsc(d.data_source) + ' • ' : ''}
                        ${attrs.length} attributes
                    </div>
                    <div style="margin-top: 6px;">${attrPreview}${moreAttrs}</div>
                </div>
                <div class="da-entity-actions">
                    <button class="btn-edit" onclick="showAddEntityModal(${Number(d.id)})"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-delete" onclick="deleteEntity(${Number(d.id)})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
    }).join('');
}

function renderRelationshipList(rows) {
    const list = document.getElementById('relationship-list');
    if (!list) return;

    if (!rows || rows.length === 0) {
        list.innerHTML = `
            <div style="text-align:center; padding: 30px; color: #888;">
                <i class="fas fa-project-diagram" style="font-size: 2rem; color: #ddd; margin-bottom: 10px; display: block;"></i>
                <p style="font-size: 0.9rem;">Chưa có quan hệ nào. Nhấn "Thêm" để tạo quan hệ giữa các entity.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = rows.map(r => `
        <div class="da-rel-card">
            <div class="rel-line">
                <span class="entity-ref">${daEsc(r.from_name || '')}</span>
                <span class="rel-type">${daEsc(r.relationship_type || '')}</span>
                <span class="da-rel-arrow">→</span>
                <span class="entity-ref">${daEsc(r.to_name || '')}</span>
                ${r.foreign_key ? `<span class="fk-ref">(${daEsc(r.foreign_key)})</span>` : ''}
            </div>
            ${r.description ? `<div style="font-size: 0.8rem; color: #888; margin-top: 6px;">${daEsc(r.description)}</div>` : ''}
            <div style="margin-top: 10px; display: flex; gap: 6px;">
                <button class="btn btn-outline btn-sm" onclick="showAddRelationshipModal(${Number(r.id)})"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn btn-outline btn-sm" style="color: var(--danger);" onclick="deleteRelationship(${Number(r.id)})"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

function renderDataDictionary(entities) {
    const box = document.getElementById('data-dictionary-box');
    if (!box) return;
    if (!entities || entities.length === 0) {
        box.innerHTML = '<p style="color:#999; text-align:center; padding: 20px;">Chưa có dữ liệu.</p>';
        return;
    }

    box.innerHTML = entities.map(e => {
        const attrs = Array.isArray(e.attributes_detail) ? e.attributes_detail : [];
        const chips = attrs.map(a =>
            `<span class="da-attr-chip ${a.is_primary_key ? 'pk' : (a.is_foreign_key ? 'fk' : '')}">${daEsc(a.name)}<span class="type">${a.data_type}</span></span>`
        ).join('');

        return `
            <div class="da-dict-entry">
                <div class="dict-header">
                    <span class="title">${daEsc(e.name)}</span>
                    <span class="meta">${e.entity_type || 'master'} • ${attrs.length} fields</span>
                </div>
                <div class="dict-body">
                    ${e.description ? `<div style="font-size: 0.85rem; color: #666; margin-bottom: 8px;">${daEsc(e.description)}</div>` : ''}
                    <div>${chips || '<span style="color:#999; font-size: 0.8rem;">No attributes</span>'}</div>
                </div>
            </div>
        `;
    }).join('');
}

function renderDataDiagram(entities, relationships = []) {
    const container = document.getElementById('data-diagram-preview');
    if (!container) return;
    if (!entities || entities.length === 0) {
        container.innerHTML = `
            <div class="da-empty-state">
                <i class="fas fa-sitemap"></i>
                <h4>Chưa có dữ liệu</h4>
                <p>Thêm entity để xem sơ đồ quan hệ</p>
            </div>
        `;
        return;
    }

    let code = 'erDiagram\n';
    entities.forEach(e => {
        const name = String(e.name || '').replace(/\s+/g, '_');
        if (!name) return;
        code += `    ${name} {\n`;
        const attrs = Array.isArray(e.attributes_detail) && e.attributes_detail.length > 0
            ? e.attributes_detail
            : String(e.attributes || '').split(',').map(v => ({ name: v.trim(), data_type: 'string' }));
        attrs.forEach(a => {
            const aName = String(a.name || '').trim().replace(/\s+/g, '_');
            const aType = String(a.data_type || 'string').toLowerCase();
            if (aName) code += `        ${aType} ${aName}\n`;
        });
        code += '    }\n';
    });

    (relationships || []).forEach(r => {
        const from = String(r.from_name || '').trim().replace(/\s+/g, '_');
        const to = String(r.to_name || '').trim().replace(/\s+/g, '_');
        if (!from || !to) return;
        const relType = String(r.relationship_type || 'one-to-many');
        const edge = relType === 'one-to-one' ? '||--||' : (relType === 'many-to-many' ? '}o--o{' : '||--o{');
        code += `    ${from} ${edge} ${to} : "${(r.foreign_key || '').replace(/"/g, '')}"\n`;
    });

    container.innerHTML = `<div id="data-diagram-inner" class="mermaid" style="transform-origin:top left; transform:scale(${__daState.zoom || 1});">${code}</div>`;
    if (window.mermaid) mermaid.run({ nodes: [container.querySelector('.mermaid')] });
}

function zoomDataDiagram(factor) {
    if (factor === 1) __daState.zoom = 1;
    else __daState.zoom = Math.max(0.4, Math.min(2.5, (__daState.zoom || 1) * factor));
    renderDataDiagram(__daState.entities, __daState.relationships);
}

function getEntityById(id) {
    return (__daState.entities || []).find(e => Number(e.id) === Number(id));
}

function buildAttributeRows(attrs = []) {
    return attrs.map((a, idx) => `
        <tr data-idx="${idx}">
            <td><input class="attr-name" value="${daEsc(a.name || '')}" placeholder="Field"></td>
            <td><select class="attr-type">
                ${['string', 'int', 'decimal', 'date', 'boolean', 'datetime'].map(t => `<option value="${t}" ${String(a.data_type || 'string') === t ? 'selected' : ''}>${t}</option>`).join('')}
            </select></td>
            <td><input type="checkbox" class="attr-pk" ${a.is_primary_key ? 'checked' : ''}></td>
            <td><input type="checkbox" class="attr-fk" ${a.is_foreign_key ? 'checked' : ''}></td>
            <td><input type="checkbox" class="attr-null" ${a.is_nullable === 0 ? '' : 'checked'}></td>
            <td><input class="attr-ref" value="${daEsc(a.reference_entity || '')}" placeholder="Ref Entity"></td>
            <td><input class="attr-desc" value="${daEsc(a.description || '')}" placeholder="Description"></td>
            <td><button class="btn-rm" onclick="this.closest('tr').remove()">x</button></td>
        </tr>
    `).join('');
}

function showAddEntityModal(entityId = 0) {
    const e = entityId ? getEntityById(entityId) : null;
    const attrs = e?.attributes_detail || [];
    const html = `
        <div class="form-grid">
            <div class="form-group"><label>Name</label><input id="da-entity-name" value="${daEsc(e?.name || '')}"></div>
            <div class="form-group"><label>Type</label><select id="da-entity-type"><option value="master" ${e?.entity_type === 'master' ? 'selected' : ''}>master</option><option value="transaction" ${e?.entity_type === 'transaction' ? 'selected' : ''}>transaction</option><option value="lookup" ${e?.entity_type === 'lookup' ? 'selected' : ''}>lookup</option></select></div>
            <div class="form-group"><label>Data Source</label><input id="da-entity-source" value="${daEsc(e?.data_source || '')}" placeholder="ERP, CRM, Manual..."></div>
            <div class="form-group full"><label>Description</label><textarea id="da-entity-desc" rows="2">${daEsc(e?.description || '')}</textarea></div>
            <div class="form-group full">
                <label>Attributes</label>
                <table class="data-table"><thead><tr><th>Name</th><th>Type</th><th>PK</th><th>FK</th><th>Nullable</th><th>Ref</th><th>Description</th><th></th></tr></thead>
                <tbody id="da-attr-body">${buildAttributeRows(attrs)}</tbody></table>
                <button class="btn btn-outline btn-sm" onclick="addDaAttrRow()">+ Attribute</button>
            </div>
        </div>
        <div style="text-align:right; margin-top:20px;"><button class="btn btn-outline" onclick="closeMainModal()">Cancel</button> <button class="btn btn-primary" onclick="saveEntity(${entityId || 0})">Save</button></div>
    `;
    showModal(entityId ? 'Edit Entity' : i18n('add_entity'), html);
}

function addDaAttrRow() {
    const body = document.getElementById('da-attr-body');
    if (!body) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input class="attr-name" placeholder="Field"></td><td><select class="attr-type"><option>string</option><option>int</option><option>decimal</option><option>date</option><option>boolean</option><option>datetime</option></select></td><td><input type="checkbox" class="attr-pk"></td><td><input type="checkbox" class="attr-fk"></td><td><input type="checkbox" class="attr-null" checked></td><td><input class="attr-ref" placeholder="Ref Entity"></td><td><input class="attr-desc" placeholder="Description"></td><td><button class="btn-rm" onclick="this.closest('tr').remove()">x</button></td>`;
    body.appendChild(tr);
}

function collectDaAttributes() {
    const rows = Array.from(document.querySelectorAll('#da-attr-body tr'));
    const out = [];
    rows.forEach(r => {
        const name = (r.querySelector('.attr-name')?.value || '').trim();
        if (!name) return;
        out.push({
            name,
            data_type: (r.querySelector('.attr-type')?.value || 'string').trim(),
            is_primary_key: r.querySelector('.attr-pk')?.checked ? 1 : 0,
            is_foreign_key: r.querySelector('.attr-fk')?.checked ? 1 : 0,
            is_nullable: r.querySelector('.attr-null')?.checked ? 1 : 0,
            reference_entity: (r.querySelector('.attr-ref')?.value || '').trim(),
            description: (r.querySelector('.attr-desc')?.value || '').trim()
        });
    });
    return out;
}

async function saveEntity(entityId = 0) {
    const entity = {
        id: entityId || undefined,
        name: (document.getElementById('da-entity-name')?.value || '').trim(),
        entity_type: (document.getElementById('da-entity-type')?.value || 'master').trim(),
        data_source: (document.getElementById('da-entity-source')?.value || '').trim(),
        description: (document.getElementById('da-entity-desc')?.value || '').trim(),
        attributes: collectDaAttributes()
    };
    if (!entity.name) return showToast('Entity name is required.');
    if (!entity.attributes.length) return showToast('At least one attribute is required.');
    const pkCount = entity.attributes.filter(a => a.is_primary_key === 1).length;
    if (pkCount === 0) return showToast('Define at least one primary key.');

    await daApi('save_entity', { entity }, 'POST');
    closeMainModal();
    initDataArchitecture();
}

async function deleteEntity(id) {
    if (!confirm('Delete entity?')) return;
    await daApi('delete_entity', { entity_id: id }, 'POST');
    initDataArchitecture();
}

function showAddRelationshipModal(relId = 0) {
    const rel = relId ? (__daState.relationships || []).find(r => Number(r.id) === Number(relId)) : null;
    const options = (__daState.entities || []).map(e => `<option value="${e.id}">${daEsc(e.name)}</option>`).join('');
    const html = `
        <div class="form-grid">
            <div class="form-group"><label>From Entity</label><select id="da-rel-from">${options}</select></div>
            <div class="form-group"><label>Type</label><select id="da-rel-type"><option value="one-to-one">one-to-one</option><option value="one-to-many">one-to-many</option><option value="many-to-many">many-to-many</option></select></div>
            <div class="form-group"><label>To Entity</label><select id="da-rel-to">${options}</select></div>
            <div class="form-group"><label>Foreign Key</label><input id="da-rel-fk" value="${daEsc(rel?.foreign_key || '')}"></div>
            <div class="form-group full"><label>Description</label><textarea id="da-rel-desc" rows="2">${daEsc(rel?.description || '')}</textarea></div>
        </div>
        <div style="text-align:right; margin-top:20px;"><button class="btn btn-outline" onclick="closeMainModal()">Cancel</button> <button class="btn btn-primary" onclick="saveRelationship(${relId || 0})">Save</button></div>
    `;
    showModal(relId ? 'Edit Relationship' : 'Add Relationship', html);
    if (rel) {
        document.getElementById('da-rel-from').value = String(rel.entity_from_id || '');
        document.getElementById('da-rel-to').value = String(rel.entity_to_id || '');
        document.getElementById('da-rel-type').value = String(rel.relationship_type || 'one-to-many');
    }
}

async function saveRelationship(relId = 0) {
    const relationship = {
        id: relId || undefined,
        entity_from_id: Number(document.getElementById('da-rel-from')?.value || 0),
        entity_to_id: Number(document.getElementById('da-rel-to')?.value || 0),
        relationship_type: document.getElementById('da-rel-type')?.value || 'one-to-many',
        foreign_key: (document.getElementById('da-rel-fk')?.value || '').trim(),
        description: (document.getElementById('da-rel-desc')?.value || '').trim()
    };
    await daApi('save_relationship', { relationship }, 'POST');
    closeMainModal();
    initDataArchitecture();
}

async function deleteRelationship(id) {
    if (!confirm('Delete relationship?')) return;
    await daApi('delete_relationship', { relationship_id: id }, 'POST');
    initDataArchitecture();
}

async function viewEntityVersions(entityId) {
    const data = await daApi('versions', { entity_id: entityId }, 'GET');
    const rows = (data.versions || []).map(v => `<tr><td>${v.id}</td><td>${daEsc(v.action_type)}</td><td>${daEsc(v.created_at)}</td></tr>`).join('');
    showModal('Entity History', `<table class="data-table"><thead><tr><th>ID</th><th>Action</th><th>Time</th></tr></thead><tbody>${rows || '<tr><td colspan="3">No history.</td></tr>'}</tbody></table>`);
}

function triggerEntityCsvImport() {
    const input = document.getElementById('entity-csv-file');
    if (input) input.click();
}

function parseCsvRows(text) {
    const input = String(text || '');
    if (!input.trim()) return [];
    const rows = [];
    let row = [];
    let cell = '';
    let inQuotes = false;

    for (let i = 0; i < input.length; i++) {
        const ch = input[i];
        const next = input[i + 1];
        if (ch === '"') {
            if (inQuotes && next === '"') {
                cell += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (ch === ',' && !inQuotes) {
            row.push(cell.trim());
            cell = '';
        } else if ((ch === '\n' || ch === '\r') && !inQuotes) {
            if (ch === '\r' && next === '\n') i++;
            row.push(cell.trim());
            rows.push(row);
            row = [];
            cell = '';
        } else {
            cell += ch;
        }
    }
    if (cell.length > 0 || row.length > 0) {
        row.push(cell.trim());
        rows.push(row);
    }
    if (rows.length < 2) return [];

    const headers = rows[0].map(h => String(h || '').trim().toLowerCase());
    return rows.slice(1).filter(r => r.some(v => String(v || '').trim() !== '')).map(r => {
        const out = {};
        headers.forEach((h, idx) => out[h] = String(r[idx] || '').trim());
        return out;
    });
}

async function handleEntityCsvImport(input) {
    const file = input?.files?.[0];
    if (!file) return;
    const text = await file.text();
    const rows = parseCsvRows(text);
    if (!rows.length) return showToast('CSV has no data rows.');
    const res = await daApi('import_csv', { rows }, 'POST');
    showToast(`Imported ${res.inserted || 0}, merged ${res.updated || 0} entities.`);
    showImportConflictReport(res);
    input.value = '';
    initDataArchitecture();
}

function exportEntityCsv() {
    const entities = __daState.entities || [];
    const header = 'entity_name,entity_type,data_source,attributes,description';
    const lines = entities.map(e => [e.name, e.entity_type, e.data_source, e.attributes, (e.description || '').replace(/,/g, ';')].map(v => `"${String(v || '').replace(/"/g, '""')}"`).join(','));
    const csv = [header, ...lines].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `data_architecture_${Date.now()}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

function triggerEntityJsonImport() {
    const input = document.getElementById('entity-json-file');
    if (input) input.click();
}

async function handleEntityJsonImport(input) {
    const file = input?.files?.[0];
    if (!file) return;
    try {
        const text = await file.text();
        const data = JSON.parse(text);
        const entities = Array.isArray(data.entities) ? data.entities : [];
        const relationships = Array.isArray(data.relationships) ? data.relationships : [];
        if (!entities.length) return showToast('JSON has no entities.');
        const res = await daApi('import_json', { entities, relationships }, 'POST');
        showToast(`JSON imported: ${res.inserted || 0} inserted, ${res.updated || 0} merged.`);
        showImportConflictReport(res);
        initDataArchitecture();
    } catch (e) {
        showToast('JSON import error: ' + e.message);
    } finally {
        input.value = '';
    }
}

function showImportConflictReport(res) {
    const conflicts = Array.isArray(res?.conflicts) ? res.conflicts : [];
    if (!conflicts.length) return;
    const items = conflicts.map(c => {
        const changed = (c.changed_fields || []).map(f => `${f.field}: "${f.old}" -> "${f.new}"`).join('<br>');
        const added = (c.attributes_added || []).join(', ');
        const removed = (c.attributes_removed || []).join(', ');
        const modified = (c.attributes_modified || []).map(m => `${m.name} [${(m.fields || []).join(', ')}]`).join(', ');
        return `<div style="padding:8px 0; border-bottom:1px solid #eee;"><strong>${daEsc(c.entity_name || 'Unknown')}</strong><div><small>${changed || 'No scalar changes'}</small></div><div><small>Attr +: ${daEsc(added || '-')}</small></div><div><small>Attr -: ${daEsc(removed || '-')}</small></div><div><small>Attr ~: ${daEsc(modified || '-')}</small></div></div>`;
    }).join('');
    showModal('Import Merge Conflict Report', `<div style="max-height:420px; overflow:auto;">${items}</div>`);
}

function exportEntityJson() {
    const payload = {
        exported_at: new Date().toISOString(),
        entities: __daState.entities || [],
        relationships: __daState.relationships || []
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `data_architecture_${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
}

function exportDataDictionaryTxt() {
    const entities = __daState.entities || [];
    let text = 'DATA DICTIONARY\n\n';
    entities.forEach(e => {
        text += `Entity: ${e.name} [${e.entity_type || 'master'}]\nSource: ${e.data_source || '-'}\nDescription: ${e.description || '-'}\n`;
        (e.attributes_detail || []).forEach(a => {
            text += ` - ${a.name} (${a.data_type}) PK:${a.is_primary_key ? 'Y' : 'N'} FK:${a.is_foreign_key ? 'Y' : 'N'} Nullable:${a.is_nullable ? 'Y' : 'N'}\n`;
        });
        text += '\n';
    });
    const blob = new Blob([text], { type: 'text/plain;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `data_dictionary_${Date.now()}.txt`;
    a.click();
    URL.revokeObjectURL(a.href);
}

function showAiGenerateEntitiesModal() {
    const html = `
        <div class="form-group full">
            <label>Natural description</label>
            <textarea id="da-ai-input" rows="8" placeholder="Describe business data model and entities..."></textarea>
        </div>
        <div style="text-align:right; margin-top:14px;">
            <button class="btn btn-outline" onclick="closeMainModal()">Cancel</button>
            <button class="btn btn-primary" onclick="runAiGenerateEntities()">Generate</button>
        </div>
    `;
    showModal('AI Generate Entities', html);
}

async function runAiGenerateEntities() {
    const content = (document.getElementById('da-ai-input')?.value || '').trim();
    if (!content) return showToast('Please input description for AI generation.');
    const res = await daApi('ai_generate', { content }, 'POST');
    const entities = Array.isArray(res.entities) ? res.entities : [];
    const relationships = Array.isArray(res.relationships) ? res.relationships : [];
    if (!entities.length) return showToast('AI returned no entities.');

    const nameToId = {};
    for (const e of entities) {
        const attrs = Array.isArray(e.attributes) ? e.attributes : [];
        if (!e.name || attrs.length === 0) continue;
        const saved = await daApi('save_entity', {
            entity: {
                name: e.name,
                entity_type: e.entity_type || 'master',
                description: e.description || 'Generated by AI',
                data_source: e.data_source || '',
                attributes: attrs.map(a => ({
                    name: a.name,
                    data_type: a.data_type || 'string',
                    is_primary_key: a.is_primary_key ? 1 : 0,
                    is_foreign_key: a.is_foreign_key ? 1 : 0,
                    is_nullable: a.is_nullable === 0 ? 0 : 1,
                    reference_entity: a.reference_entity || '',
                    description: a.description || ''
                }))
            }
        }, 'POST');
        if (saved.entity_id) {
            nameToId[String(e.name).trim().toLowerCase()] = Number(saved.entity_id);
        }
    }

    for (const r of relationships) {
        const fromName = String(r.from || r.entity_from || '').trim();
        const toName = String(r.to || r.entity_to || '').trim();
        const fromId = nameToId[fromName.toLowerCase()] || (__daState.entities || []).find(x => String(x.name || '').trim().toLowerCase() === fromName.toLowerCase())?.id;
        const toId = nameToId[toName.toLowerCase()] || (__daState.entities || []).find(x => String(x.name || '').trim().toLowerCase() === toName.toLowerCase())?.id;
        if (!fromId || !toId) continue;
        await daApi('save_relationship', {
            relationship: {
                entity_from_id: Number(fromId),
                entity_to_id: Number(toId),
                relationship_type: r.relationship_type || 'one-to-many',
                foreign_key: r.foreign_key || '',
                description: r.description || 'Generated by AI'
            }
        }, 'POST');
    }

    closeMainModal();
    showToast(`AI generated ${entities.length} entities and ${relationships.length} relationships.`);
    initDataArchitecture();
}

const DA_TEMPLATES = {};

const MODULE_TEMPLATE_CACHE = {};

function getModuleTemplateGridId(moduleKey) {
    return `${moduleKey}-template-grid`;
}

function getModuleTemplatePanelId(moduleKey) {
    if (moduleKey === 'data_architecture') return 'da-template-panel';
    return `${moduleKey}-template-panel`;
}

async function fetchModuleTemplates(moduleKey, force = false) {
    if (!force && MODULE_TEMPLATE_CACHE[moduleKey]) return MODULE_TEMPLATE_CACHE[moduleKey];
    const response = await fetch(`api/templates.php?module=${encodeURIComponent(moduleKey)}`);
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.error || 'Cannot load templates');
    const items = Array.isArray(data.items) ? data.items : [];
    MODULE_TEMPLATE_CACHE[moduleKey] = items;
    return items;
}

async function fetchTemplateDetail(moduleKey, templateKey) {
    const response = await fetch(`api/templates.php?module=${encodeURIComponent(moduleKey)}&key=${encodeURIComponent(templateKey)}`);
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.error || 'Cannot load template detail');
    return data;
}

function renderModuleTemplateCards(moduleKey, items) {
    const grid = document.getElementById(getModuleTemplateGridId(moduleKey));
    if (!grid) return;
    if (!Array.isArray(items) || items.length === 0) {
        grid.innerHTML = '<div class="da-empty-state"><i class="fas fa-layer-group"></i><h4>Chưa có template</h4><p>Thêm template mới trong thư mục templates/ để mở rộng hệ thống.</p></div>';
        return;
    }

    grid.innerHTML = items.map((item) => `
        <div class="da-template-card" onclick="applyModuleTemplate('${moduleKey}', '${String(item.key).replace(/'/g, "\\'")}')">
            <i class="fas ${item.icon || 'fa-layer-group'}"></i>
            <div class="name">${daEsc(item.name || item.key)}</div>
            <div class="desc">${daEsc(item.description || '')}</div>
            <div class="da-muted-meta" style="margin-top:8px;">v${daEsc(item.version || '1.0.0')} • ${daEsc(item.author || 'Community')}</div>
        </div>
    `).join('');
}

async function showModuleTemplates(moduleKey) {
    const panel = document.getElementById(getModuleTemplatePanelId(moduleKey));
    if (!panel) return;
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try {
        const items = await fetchModuleTemplates(moduleKey);
        renderModuleTemplateCards(moduleKey, items);
    } catch (e) {
        showToast('Load template error: ' + e.message);
    }
}

function hideModuleTemplates(moduleKey) {
    const panel = document.getElementById(getModuleTemplatePanelId(moduleKey));
    if (panel) panel.style.display = 'none';
}

function showDaTemplates() {
    showModuleTemplates('data_architecture');
}

async function applyModuleTemplate(moduleKey, templateKey) {
    try {
        const template = await fetchTemplateDetail(moduleKey, templateKey);
        const confirmMsg = `Áp dụng template "${template.name}"?`;
        if (!confirm(confirmMsg)) return;

        if (moduleKey === 'organization') {
            const departments = Array.isArray(template.payload?.departments) ? template.payload.departments : [];
            let count = 0;
            for (const item of departments) {
                if (!item?.name) continue;
                await createNewDept(item.name, item.sponsor || '', { refresh: false, toast: false });
                count++;
            }
            hideModuleTemplates(moduleKey);
            await initOrganization();
            showToast(`Đã áp dụng ${count} department từ template "${template.name}"`);
            return;
        }

        if (moduleKey === 'process_mapping') {
            const items = Array.isArray(template.payload?.items) ? template.payload.items : [];
            for (const item of items) {
                await manageData('processes', 'save', {
                    name: item.name || '',
                    type: item.type || 'AS-IS',
                    steps: item.steps || ''
                });
            }
            hideModuleTemplates(moduleKey);
            await initProcessMapping();
            showToast(`Đã áp dụng template "${template.name}"`);
            return;
        }

        if (moduleKey === 'integration') {
            const items = Array.isArray(template.payload?.items) ? template.payload.items : [];
            for (const item of items) {
                await manageData('integrations', 'save', {
                    system_name: item.system_name || '',
                    interface_type: item.interface_type || ''
                });
            }
            hideModuleTemplates(moduleKey);
            await initIntegration();
            showToast(`Đã áp dụng template "${template.name}"`);
            return;
        }

        if (moduleKey === 'backlog') {
            const items = Array.isArray(template.payload?.items) ? template.payload.items : [];
            for (const item of items) {
                await manageData('backlog', 'save', {
                    requirement: item.requirement || '',
                    priority: item.priority || 'Medium',
                    status: item.status || 'New'
                });
            }
            hideModuleTemplates(moduleKey);
            await initBacklog();
            showToast(`Đã áp dụng template "${template.name}"`);
            return;
        }

        if (moduleKey === 'data_architecture') {
            return applyDaTemplate(template);
        }

        if (moduleKey === 'reports') {
            const sections = Array.isArray(template.payload?.sections) ? template.payload.sections : [];
            const html = `
                <div style="display:grid; gap:10px;">
                    <p><strong>${daEsc(template.name)}</strong></p>
                    <p>${daEsc(template.description || '')}</p>
                    <ol style="margin-left:18px;">
                        ${sections.map((section) => `<li>${daEsc(section)}</li>`).join('')}
                    </ol>
                    <div style="text-align:right;">
                        <button class="btn btn-primary" type="button" onclick="closeMainModal()">Đóng</button>
                    </div>
                </div>
            `;
            showModal('Report Template Preview', html);
            return;
        }

        showToast(`Module "${moduleKey}" chưa có handler áp dụng template.`);
    } catch (e) {
        showToast('Apply template error: ' + e.message);
    }
}

async function applyDaTemplate(template) {
    if (!template || !template.payload) return showToast('Template không tồn tại');

    const nameToId = {};
    let entityCount = 0;
    const entities = Array.isArray(template.payload.entities) ? template.payload.entities : [];
    const relationships = Array.isArray(template.payload.relationships) ? template.payload.relationships : [];

    for (const e of entities) {
        try {
            const saved = await daApi('save_entity', {
                entity: {
                    name: e.name,
                    entity_type: e.entity_type || 'master',
                    description: e.description || '',
                    data_source: e.data_source || '',
                    attributes: e.attributes || []
                }
            }, 'POST');

            if (saved.entity_id) {
                nameToId[String(e.name).trim().toLowerCase()] = Number(saved.entity_id);
                entityCount++;
            }
        } catch (err) {
            console.error('Error saving entity:', e.name, err);
        }
    }

    const relTypeMap = { 'one-to-one': 'one-to-one', 'one-to-many': 'one-to-many', 'many-to-one': 'one-to-many', 'many-to-many': 'many-to-many' };

    for (const r of relationships) {
        const fromId = nameToId[String(r.from || '').trim().toLowerCase()];
        const toId = nameToId[String(r.to || '').trim().toLowerCase()];
        if (!fromId || !toId) continue;

        try {
            await daApi('save_relationship', {
                relationship: {
                    entity_from_id: fromId,
                    entity_to_id: toId,
                    relationship_type: relTypeMap[r.type] || 'one-to-many',
                    foreign_key: r.fk || '',
                    description: r.description || ''
                }
            }, 'POST');
        } catch (err) {
            console.error('Error saving relationship:', r, err);
        }
    }

    hideModuleTemplates('data_architecture');
    showToast(`Đã thêm ${entityCount} entity từ template "${template.name}"`);
    initDataArchitecture();
}

function updateDaHelpTip(text) {
    const tipEl = document.getElementById('da-help-tip');
    const textEl = document.getElementById('da-help-text');
    if (tipEl && textEl) {
        textEl.textContent = text;
    }
}

async function initIntegration() {
    const data = await manageData('integrations');
    const list = document.getElementById('integration-list');
    list.innerHTML = data.map(d => `
        <tr>
            <td>${d.system_name}</td>
            <td><span class="tag">${d.interface_type}</span></td>
            <td>
                <button class="btn-rm" onclick="deleteModuleData('integrations', ${d.id}, initIntegration)"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="3" style="text-align:center; padding:20px;">No integrations defined.</td></tr>';
}

function showAddIntegrationModal() {
    const html = `
        <div class="form-grid">
            <div class="form-group full">
                <label>${i18n('system_name')}</label>
                <input type="text" id="i_sys">
            </div>
            <div class="form-group full">
                <label>${i18n('interface_type')}</label>
                <input type="text" id="i_type" placeholder="API, DB Link, File...">
            </div>
        </div>
        <div style="text-align:right; margin-top:20px;">
            <button class="btn btn-outline" onclick="closeMainModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveIntegration()">Save</button>
        </div>
    `;
    showModal(i18n('add_integration'), html);
}

async function saveIntegration() {
    const data = {
        system_name: document.getElementById('i_sys').value,
        interface_type: document.getElementById('i_type').value
    };
    await manageData('integrations', 'save', data);
    closeMainModal();
    initIntegration();
}

async function initBacklog() {
    const data = await manageData('backlog');
    const list = document.getElementById('backlog-list');
    list.innerHTML = data.map(d => `
        <tr>
            <td>${d.requirement}</td>
            <td><span class="tag" style="background:${d.priority === 'High' ? '#ffebee' : '#e8f5e9'}; color:${d.priority === 'High' ? '#c62828' : '#2e7d32'};">${d.priority}</span></td>
            <td>${d.status}</td>
            <td>
                <button class="btn-rm" onclick="deleteModuleData('backlog', ${d.id}, initBacklog)"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="4" style="text-align:center; padding:20px;">No requirements in backlog.</td></tr>';
}

function showAddBacklogModal() {
    const html = `
        <div class="form-grid">
            <div class="form-group full">
                <label>${i18n('requirement')}</label>
                <textarea id="b_req" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>${i18n('priority')}</label>
                <select id="b_prio">
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>
            </div>
             <div class="form-group">
                <label>${i18n('status')}</label>
                <select id="b_status">
                    <option value="New">New</option>
                    <option value="Analyze">Analyzing</option>
                    <option value="Done">Done</option>
                </select>
            </div>
        </div>
        <div style="text-align:right; margin-top:20px;">
            <button class="btn btn-outline" onclick="closeMainModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveBacklog()">Save</button>
        </div>
    `;
    showModal(i18n('add_backlog'), html);
}

async function saveBacklog() {
    const data = {
        requirement: document.getElementById('b_req').value,
        priority: document.getElementById('b_prio').value,
        status: document.getElementById('b_status').value
    };
    await manageData('backlog', 'save', data);
    closeMainModal();
    initBacklog();
}

async function deleteModuleData(type, id, callback) {
    if (!confirm(i18n('delete') + '?')) return;
    await manageData(type, 'delete', { id });
    callback();
}

async function initOrganization() {
    const response = await fetch('api/departments.php');
    const depts = await response.json();
    const tbody = document.getElementById('dept-table-body');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr class="inline-add-row" id="app-add-dept-row">
            <td>*</td>
            <td><input type="text" id="new-dept-name" placeholder="${i18n('dept_name')}..." style="width:100%;"></td>
            <td><input type="text" id="new-dept-sponsor" placeholder="${i18n('sponsor')}..." style="width:100%;"></td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="saveNewDeptInline()">${i18n('save')}</button>
            </td>
        </tr>
    ` + depts.map(d => `
        <tr id="dept-row-${d.id}">
            <td style="padding:12px; border-bottom:1px solid #eee;">${d.id}</td>
            <td style="padding:12px; border-bottom:1px solid #eee;" class="dept-name-cell"><strong>${d.name}</strong></td>
            <td style="padding:12px; border-bottom:1px solid #eee;" class="dept-sponsor-cell">${d.sponsor || '-'}</td>
            <td style="padding:12px; border-bottom:1px solid #eee;">
                <button class="btn btn-primary" onclick="startSurvey(${d.id}, '${d.name.replace(/'/g, "\\'")}')">${i18n('edit_survey')}</button>
                <button class="btn btn-outline" onclick="editDeptInline(${d.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-outline" style="color:var(--danger); border-color:var(--danger);" onclick="deleteDept(${d.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="4" style="text-align:center; padding:20px;">No departments found.</td></tr>';
}

function editDeptInline(id) {
    const row = document.getElementById(`dept-row-${id}`);
    const nameCell = row.querySelector('.dept-name-cell');
    const sponsorCell = row.querySelector('.dept-sponsor-cell');
    const name = nameCell.innerText;
    const sponsor = sponsorCell.innerText === '-' ? '' : sponsorCell.innerText;

    nameCell.innerHTML = `<input type="text" value="${name}" class="edit-dept-name" style="width:100%;">`;
    sponsorCell.innerHTML = `<input type="text" value="${sponsor}" class="edit-dept-sponsor" style="width:100%;">`;

    const actionsCell = row.cells[3];
    actionsCell.innerHTML = `
        <button class="btn btn-primary" onclick="saveDeptUpdate(${id})">${i18n('save')}</button>
        <button class="btn btn-outline" onclick="initOrganization()">${i18n('cancel')}</button>
    `;
}

async function saveDeptUpdate(id) {
    const row = document.getElementById(`dept-row-${id}`);
    const name = row.querySelector('.edit-dept-name').value;
    const sponsor = row.querySelector('.edit-dept-sponsor').value;

    if (!name) return showToast('Name is required');

    try {
        const res = await fetch('api/departments.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name, sponsor })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Department updated!');
            initOrganization();
        } else {
            showToast('Error: ' + (data.error || 'Failed to update.'));
        }
    } catch (e) {
        showToast('Error: ' + e.message);
    }
}

function showAddDeptModal() {
    // Scroll to the end of the table or just focus the inline input
    const input = document.getElementById('new-dept-name');
    if (input) input.focus();
}

async function saveNewDeptInline() {
    const name = document.getElementById('new-dept-name').value;
    const sponsor = document.getElementById('new-dept-sponsor').value;
    if (!name) return showToast('Please enter a department name.');

    await createNewDept(name, sponsor);

    // Clear inputs
    document.getElementById('new-dept-name').value = '';
    document.getElementById('new-dept-sponsor').value = '';
}

async function createNewDept(name, sponsor, options = {}) {
    const refresh = options.refresh !== false;
    const toast = options.toast !== false;
    try {
        const res = await fetch('api/departments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, sponsor })
        });
        const data = await res.json();
        if (data.status === 'success') {
            if (toast) showToast('Department added!');
            if (refresh) initOrganization();
        } else {
            showToast('Error: ' + (data.error || 'Failed to save.'));
        }
    } catch (e) {
        showToast('Error: ' + e.message);
    }
}

async function deleteDept(id) {
    if (!confirm('Are you sure you want to delete this department? All survey data will be lost.')) return;
    const res = await fetch(`api/departments.php?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (data.status === 'success') {
        showToast('Department deleted.');
        initOrganization();
    }
}
async function initAiToolkit() {
    clearHeaderActions();
    try {
        const response = await fetch('api/settings.php');
        if (!response.ok) throw new Error('Failed to load settings');
        const settings = await response.json();
        console.log('[AI Toolkit] Settings loaded:', settings);

        // 1. Determine provider (from DB or default to groq)
        const currentProvider = settings.ai_provider || 'groq';

        // 2. Load Provider-specific UI defaults first
        selectAIProvider(currentProvider, true);

        // 3. Overwrite with user-saved data specifically
        const aiMappings = {
            'set-ai-endpoint': settings.ai_endpoint,
            'set-ai-model': settings.ai_model,
            'set-ai-timeout': settings.ai_timeout_sec,
            'set-ai-ssl-verify': settings.ai_ssl_verify,
            'set-ai-ssl-verify-host': settings.ai_ssl_verify_host,
            'set-ai-report-model': settings.ai_report_model
        };

        Object.entries(aiMappings).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el && val !== undefined && val !== null && val !== '') {
                console.log(`[AI Toolkit] Restoring field ${id} with value:`, val);
                el.value = val;
            }
        });

        // 4. Update Key Status specifically
        const keyStatus = document.getElementById('set-ai-key-status');
        if (keyStatus) {
            if (settings.ai_api_key_set) {
                keyStatus.innerText = `Stored key: ${settings.ai_api_key_masked}. Leave blank to keep current.`;
            } else {
                keyStatus.innerText = 'No API key configured.';
            }
        }

        loadAiTelemetry();
    } catch (err) {
        console.error('initAiToolkit failed:', err);
        showToast('Error loading AI configuration');
    }
}

function selectAIProvider(provider, updateInputs = true) {
    document.getElementById('set-ai-provider').value = provider;

    // UI Highlighting
    document.querySelectorAll('.provider-option').forEach(el => {
        el.classList.remove('active');
        if (el.dataset.provider === provider) el.classList.add('active');
    });

    // Dynamic Field Toggling
    const keyField = document.querySelector('.ai-field-key');
    const endpointField = document.querySelector('.ai-field-endpoint');
    const sslField = document.querySelector('.ai-field-ssl');

    // Rule 1: Local providers usually don't need a key in this simple setup
    if (['ollama', 'lmstudio'].includes(provider)) {
        keyField.classList.add('hidden');
    } else {
        keyField.classList.remove('hidden');
    }

    // Rule 2: SSL settings usually only relevant for cloud providers or specific custom setups
    if (['openai', 'gemini', 'groq'].includes(provider)) {
        sslField.classList.remove('hidden');
    } else {
        sslField.classList.add('hidden');
    }

    // Rule 3: Load Models button visibility
    const loadBtn = document.getElementById('btn-load-models');
    if (['ollama', 'lmstudio'].includes(provider)) {
        loadBtn.classList.remove('hidden');
    } else {
        loadBtn.classList.add('hidden');
    }

    if (updateInputs) {
        const defaults = {
            openai: { endpoint: 'https://api.openai.com/v1/chat/completions', model: 'gpt-4o' },
            gemini: { endpoint: 'https://generativelanguage.googleapis.com/v1beta/models', model: 'gemini-1.5-pro' },
            groq: { endpoint: 'https://api.groq.com/openai/v1/chat/completions', model: 'llama-3.3-70b-versatile' },
            ollama: { endpoint: 'http://localhost:11434/api/chat', model: 'llama3' },
            lmstudio: { endpoint: 'http://localhost:1234/v1/chat/completions', model: 'model-identifier' }
        };

        const config = defaults[provider];
        if (config) {
            document.getElementById('set-ai-endpoint').value = config.endpoint;
            document.getElementById('set-ai-model').value = config.model;
            document.getElementById('set-ai-key').value = ''; // Clear key input on switch for safety
        }
    }
}

async function loadProviderModels() {
    const provider = document.getElementById('set-ai-provider').value;
    const endpoint = document.getElementById('set-ai-endpoint').value;
    const datalist = document.getElementById('ai-model-list');
    const loadBtn = document.getElementById('btn-load-models');

    if (!endpoint) return showToast('Please enter an endpoint first.');

    try {
        loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        loadBtn.disabled = true;

        const response = await fetch(`api/ai_tools.php?action=list_models&provider=${provider}&endpoint=${encodeURIComponent(endpoint)}`);
        const res = await response.json();

        if (res.success && res.models) {
            datalist.innerHTML = '';
            res.models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                datalist.appendChild(opt);
            });
            showToast(`Loaded ${res.models.length} models for ${provider}. Click output to select.`);

            // Auto select first if empty
            const modelInput = document.getElementById('set-ai-model');
            if (res.models.length > 0 && !modelInput.value) {
                modelInput.value = res.models[0];
            }
        } else {
            showToast('Error: ' + (res.error || 'Failed to load models.'));
        }
    } catch (e) {
        showToast('Network error: ' + e.message);
    } finally {
        loadBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Load';
        loadBtn.disabled = false;
    }
}

async function saveAISettings() {
    const aiFields = [
        ['ai_provider', 'set-ai-provider'],
        ['ai_endpoint', 'set-ai-endpoint'],
        ['ai_api_key', 'set-ai-key'],
        ['ai_model', 'set-ai-model'],
        ['ai_timeout_sec', 'set-ai-timeout'],
        ['ai_ssl_verify', 'set-ai-ssl-verify'],
        ['ai_ssl_verify_host', 'set-ai-ssl-verify-host'],
        ['ai_report_model', 'set-ai-report-model']
    ];

    const data = {};
    aiFields.forEach(([key, id]) => {
        const el = document.getElementById(id);
        if (el) {
            const val = el.value.trim();
            // Only add if not empty, except for provider which is always needed
            if (key === 'ai_provider' || val !== '') {
                data[key] = val;
            }
        }
    });

    // Special rule for key: if empty in input, don't send (keeps server-side key)
    if (data.ai_api_key === '') delete data.ai_api_key;

    try {
        const res = await fetch('api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            showToast('AI Settings saved successfully!');
            const keyInput = document.getElementById('set-ai-key');
            if (keyInput) keyInput.value = '';
            initAiToolkit();
        } else {
            showToast('Error saving: ' + (result.error || 'Unknown server error'));
        }
    } catch (e) {
        showToast('Network error: ' + e.message);
    }
}

async function loadAiTelemetry() {
    const tbody = document.getElementById('ai-telemetry-body');
    if (!tbody) return;

    try {
        const response = await fetch('api/ai_tools.php?action=telemetry');
        const data = await response.json();
        if (data.runs) {
            tbody.innerHTML = data.runs.map(r => `
                <tr>
                    <td style="font-size:0.85rem;">${new Date(r.created_at).toLocaleString()}</td>
                    <td><span class="tag">${r.provider || 'unknown'}</span></td>
                    <td><small>${r.model}</small></td>
                    <td>${r.action_name || '-'}</td>
                    <td>${r.latency_ms}ms</td>
                    <td><span class="status-dot" style="background:${r.status === 'success' ? '#4caf50' : '#f44336'}"></span> ${r.status}</td>
                </tr>
            `).join('') || '<tr><td colspan="6" style="text-align:center;">No runs yet.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Failed to load telemetry.</td></tr>';
    }
}

async function testAIConnection() {
    const provider = document.getElementById('set-ai-provider').value;
    const payload = {
        ai_provider: provider,
        ai_endpoint: document.getElementById('set-ai-endpoint').value,
        ai_api_key: document.getElementById('set-ai-key').value,
        ai_model: document.getElementById('set-ai-model').value
    };

    showToast(`Testing ${provider} connection...`);
    try {
        const response = await fetch('api/ai_tools.php?action=test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.success) {
            showToast(`Connection Successful! Latency: ${data.latency_ms}ms`);
            loadAiTelemetry();
        } else {
            showToast('Connection Failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        showToast('Request failed: ' + e.message);
    }
}

// SETTINGS & AI LOGIC
async function initSettings() {
    try {
        const response = await fetch('api/settings.php');
        const settings = await response.json();

        const mappings = {
            'set-groq-endpoint': settings.groq_endpoint,
            'set-groq-model': settings.groq_model,
            'set-ai-report-model': settings.ai_report_model,
            'set-ai-timeout': settings.ai_timeout_sec,
            'set-app-env': settings.app_env,
            'set-ai-ssl-verify': settings.ai_ssl_verify,
            'set-ai-ssl-verify-host': settings.ai_ssl_verify_host,
            'set-google-client-id': settings.google_client_id,
            'set-storage-driver': settings.storage_driver,
            'set-storage-local-root': settings.storage_local_root,
            'set-s3-bucket': settings.s3_bucket,
            'set-s3-region': settings.s3_region,
            'set-s3-endpoint': settings.s3_endpoint,
            'set-s3-prefix': settings.s3_prefix,
            'set-upload-max-mb': settings.upload_max_mb,
            'set-upload-max-width': settings.upload_max_width,
            'set-upload-max-height': settings.upload_max_height,
            'set-upload-jpeg-quality': settings.upload_jpeg_quality,
            'set-upload-require-av': settings.upload_require_av,
            'set-smtp-host': settings.smtp_host,
            'set-smtp-port': settings.smtp_port,
            'set-smtp-username': settings.smtp_username,
            'set-smtp-encryption': settings.smtp_encryption,
            'set-smtp-from-email': settings.smtp_from_email,
            'set-smtp-from-name': settings.smtp_from_name,
            'set-footer-copyright-text': settings.footer_copyright_text,
            'set-footer-brand-name': settings.footer_brand_name,
            'set-footer-contact-email': settings.footer_contact_email
        };

        for (const [id, value] of Object.entries(mappings)) {
            const el = document.getElementById(id);
            if (el && typeof value !== 'undefined' && value !== null) {
                el.value = value;
            }
        }

        const keyStatus = document.getElementById('set-groq-key-status');
        if (keyStatus) {
            keyStatus.innerText = settings.groq_api_key_set
                ? `Stored key: ${settings.groq_api_key_masked || 'configured'}. Leave blank to keep current key.`
                : 'No API key configured yet.';
        }

        const smtpKeyStatus = document.getElementById('set-smtp-password-status');
        if (smtpKeyStatus) {
            smtpKeyStatus.innerText = settings.smtp_password_set
                ? 'SMTP password is stored on server. Leave blank to keep current password.'
                : 'No SMTP password configured yet.';
        }

        updateAppFooter(settings);
    } catch (e) {
        console.error('Failed to init settings:', e);
    }
}

async function saveSettings() {
    const data = {};
    const mappings = {
        'set-groq-endpoint': 'groq_endpoint',
        'set-groq-key': 'groq_api_key',
        'set-groq-model': 'groq_model',
        'set-ai-report-model': 'ai_report_model',
        'set-ai-timeout': 'ai_timeout_sec',
        'set-app-env': 'app_env',
        'set-ai-ssl-verify': 'ai_ssl_verify',
        'set-ai-ssl-verify-host': 'ai_ssl_verify_host',
        'set-google-client-id': 'google_client_id',
        'set-storage-driver': 'storage_driver',
        'set-storage-local-root': 'storage_local_root',
        'set-s3-bucket': 's3_bucket',
        'set-s3-region': 's3_region',
        'set-s3-endpoint': 's3_endpoint',
        'set-s3-prefix': 's3_prefix',
        'set-upload-max-mb': 'upload_max_mb',
        'set-upload-max-width': 'upload_max_width',
        'set-upload-max-height': 'upload_max_height',
        'set-upload-jpeg-quality': 'upload_jpeg_quality',
        'set-upload-require-av': 'upload_require_av',
        'set-smtp-host': 'smtp_host',
        'set-smtp-port': 'smtp_port',
        'set-smtp-username': 'smtp_username',
        'set-smtp-encryption': 'smtp_encryption',
        'set-smtp-from-email': 'smtp_from_email',
        'set-smtp-from-name': 'smtp_from_name',
        'set-smtp-password': 'smtp_password',
        'set-footer-copyright-text': 'footer_copyright_text',
        'set-footer-brand-name': 'footer_brand_name',
        'set-footer-contact-email': 'footer_contact_email'
    };

    for (const [id, key] of Object.entries(mappings)) {
        const el = document.getElementById(id);
        if (el) {
            const val = el.value.trim();
            // Special case: don't overwrite passwords with empty string if user left them blank
            if ((id === 'set-groq-key' || id === 'set-smtp-password') && !val) {
                continue;
            }
            data[key] = val;
        }
    }

    if (Object.keys(data).length === 0) return;

    try {
        const response = await fetch('api/settings.php', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            showToast('Settings saved successfully!');
            // Clear password fields if saved
            if (document.getElementById('set-groq-key')) document.getElementById('set-groq-key').value = '';
            if (document.getElementById('set-smtp-password')) document.getElementById('set-smtp-password').value = '';
            initSettings();
            loadPublicConfig();
        } else {
            showToast('Error saving settings: ' + (result.error || 'Unknown error'));
        }
    } catch (e) {
        showToast('Network error: ' + e.message);
    }
}

async function loadPublicConfig() {
    try {
        const response = await fetch('api/public_config.php');
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'Failed to load public config');
        updateAppFooter(data);
    } catch (e) {
        console.error('Failed to load public config:', e);
    }
}

function updateAppFooter(config = {}) {
    const footerText = document.getElementById('app-footer-text');
    const footerLink = document.getElementById('app-footer-link');
    if (!footerText || !footerLink) return;

    const brand = String(config.footer_brand_name || 'vannamdigital').trim() || 'vannamdigital';
    const email = String(config.footer_contact_email || 'namxp2@gmail.com').trim() || 'namxp2@gmail.com';
    const text = String(config.footer_copyright_text || `Powered by ${brand}`).trim() || `Powered by ${brand}`;

    footerText.innerHTML = `${daEsc(text)}${text.toLowerCase().includes(brand.toLowerCase()) ? '' : ` <strong>${daEsc(brand)}</strong>`}`;
    footerLink.textContent = email;
    footerLink.href = `mailto:${email}`;
}

const SECTION_PROMPTS = {
    1: "You are a professional Business Analyst. Normalize the 'Department Role' description. Use professional, concise language. Consider the Department Name provided in the context.",
    2: "Normalize stakeholder information. Format as a clean list of roles and responsibilities. Use the Department context to refine titles.",
    3: "Standardize Business Goals and Pain Points. Use bullet points. Distinguish clearly between goals and pains. Align with the department's role if provided.",
    4: `CRITICAL: You are a process mapping assistant. Convert this description into a professional process script that supports branching and phases. 
    Strictly follow this format for your output (one item per line):
    - PHASE: [Phase Name] -> To group related steps.
    - X. [Step Description] -> For standard sequential steps.
    - DECISION: [Question]? | Yes -> [Step Number/Name] | No -> [Step Number/Name] -> For branching logic.
    - GOTO: [Step Number/Name] -> To jump to a specific step.
    
    Example output:
    PHASE: Intake
    1. Receive Request
    DECISION: Valid? | Yes -> 2 | No -> 3
    2. Process Request
    PHASE: Finalization
    3. Send Notification`,
    5: "Normalize user roles and permissions into a clear matrix or list. Ensure consistency with the identified stakeholders.",
    6: "Standardize data characteristics (volume, source, frequency). Professionalize terminology.",
    7: "Normalize system integration requirements into technical bullet points. Reference existing systems if mentioned in context.",
    8: "Structure non-functional requirements (security, performance) into industry standards.",
    11: "CRITICAL: Extract and normalize key entities and their main attributes. Format strictly as: 'EntityName: Attribute1, Attribute2, Attribute3'. One entity per line. Do not include any other text as the output will be used to automatically generate an ER diagram. Example:\nUser: ID, UserName, Email\nOrder: ID, UserID, Date",
    12: "Standardize automation opportunities. Focus on ROI, feasibility, and alignment with business goals.",
    13: "Format risk assessments into 'Risk - Mitigation' structure. Identify risks specific to the department context.",
    14: "Standardize the backlog items into clear user stories or requirement statements."
};

async function normalizeSection(sectionId, fieldKey) {
    const sectionEl = document.getElementById(`section-${sectionId}`);
    if (!sectionEl) return;

    const input = sectionEl.querySelector(`[data-key="${fieldKey}"]`) || sectionEl.querySelector('textarea');
    if (!input || !input.value) return showToast('Please enter some content first.');

    const originalText = input.value;
    const deptName = localStorage.getItem('current_dept_name') || 'Unknown';

    // Collect context
    let context = `--- CONTEXT ---
- Department: ${deptName}`;

    const deptRoleInput = document.querySelector('[data-key="dept_role"]');
    if (deptRoleInput && deptRoleInput.value && sectionId != 1) {
        context += `\n- Department Role: ${deptRoleInput.value}`;
    }

    // Include other fields in the same section for context
    const otherFields = sectionEl.querySelectorAll('[data-key]');
    let otherData = "";
    otherFields.forEach(f => {
        if (f.getAttribute('data-key') !== fieldKey && f.value) {
            const label = f.previousElementSibling?.innerText || f.closest('.form-group')?.querySelector('label')?.innerText || f.getAttribute('data-key');
            otherData += `\n- ${label}: ${f.value}`;
        }
    });

    if (otherData) {
        context += `\n- Related information in this section:${otherData}`;
    }

    const fullContent = `${context}\n\n--- CONTENT TO NORMALIZE ---\n${originalText}`;

    input.value = 'AI is working...';
    input.disabled = true;

    try {
        const prompt = SECTION_PROMPTS[sectionId] || "Normalize and professionalize this business requirement content.";
        const response = await fetch('api/ai_proxy.php', {
            method: 'POST',
            body: JSON.stringify({ prompt, content: fullContent }),
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await response.json();
        if (!response.ok || data.error) {
            if (handleAiApiMissingKey(data)) {
                input.value = originalText;
                delete input.dataset.isNormalized;
                return;
            }
            throw new Error(data.error || 'AI normalize failed');
        }

        input.value = data.content;
        input.dataset.rawValue = originalText;
        input.dataset.isNormalized = '1';

        // Auto-render diagram if applicable
        if (['process_asis', 'process_tobe', 'key_entities'].includes(fieldKey)) {
            renderSurveyDiagram(sectionId, fieldKey, data.content);
        }
    } catch (e) {
        showToast('AI Error: ' + e.message);
        input.value = originalText;
        delete input.dataset.isNormalized;
    } finally {
        input.disabled = false;
    }
}

function collectFieldCatalogFromForm() {
    const catalog = [];
    document.querySelectorAll('#surveyForm .section').forEach(sectionEl => {
        const sectionId = (sectionEl.id || '').replace('section-', '');
        const sectionName = sectionEl.querySelector('h2')?.innerText || `Section ${sectionId}`;
        sectionEl.querySelectorAll('[data-key]').forEach(input => {
            const fieldKey = input.getAttribute('data-key');
            const label = input.closest('.form-group')?.querySelector('label')?.innerText || fieldKey;
            catalog.push({
                section_id: sectionId,
                section_name: sectionName,
                field_key: fieldKey,
                field_label: label,
                value: input.value || ''
            });
        });
    });
    return catalog;
}

function openAiIntakeModal(scope = 'full', sectionId = null) {
    const deptId = localStorage.getItem('current_dept_id') || '0';
    const draftKey = `ai_intake_draft_${deptId}_${scope}_${sectionId || 'all'}`;
    const draftHtml = localStorage.getItem(draftKey) || '';
    const html = `
        <div class="form-grid">
            <div class="form-group full">
                <label>AI Intake Notes (word-style)</label>
                <div class="ai-note-toolbar">
                    <select class="ai-note-select" onchange="setAiIntakeHeading(this.value)">
                        <option value="">Paragraph</option>
                        <option value="H2">Heading 2</option>
                        <option value="H3">Heading 3</option>
                        <option value="H4">Heading 4</option>
                    </select>
                    <select class="ai-note-select" onchange="setAiIntakeFontSize(this.value)">
                        <option value="">Font Size</option>
                        <option value="2">Small</option>
                        <option value="3">Normal</option>
                        <option value="5">Large</option>
                        <option value="6">XL</option>
                    </select>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('bold')"><b>B</b></button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('italic')"><i>I</i></button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('underline')"><u>U</u></button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('insertUnorderedList')">• List</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('insertOrderedList')">1. List</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="insertAiIntakeLink()">Link</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="insertAiIntakeMiniTable()">Table</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('undo')">Undo</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="formatAiIntake('redo')">Redo</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="copyAiIntakeNote()">Copy</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="clearAiIntakeNote()">Clear</button>
                </div>
                <div id="ai-intake-content" class="ai-note-editor" contenteditable="true">${draftHtml}</div>
                <small class="ai-note-help">Draft auto-saved locally for this department and scope.</small>
            </div>
        </div>
        <div class="ai-note-footer">
            <small class="ai-note-help">Scope current: <strong>${scope}</strong>${sectionId ? ` (section ${sectionId})` : ''}</small>
            <div class="ai-note-actions">
                <button class="btn btn-outline" onclick="toggleAiIntakeFullscreen()">Fullscreen</button>
                <button class="btn btn-outline" onclick="saveAiIntakeDraft('${draftKey}')">Save Draft</button>
                <button class="btn btn-outline" onclick="closeMainModal()">Cancel</button>
                <button class="btn btn-primary" onclick="runAiIntakeRequest('${scope}', ${sectionId ? `'${sectionId}'` : 'null'})">
                    <i class="fas fa-bolt"></i> Normalize
                </button>
            </div>
        </div>
    `;
    showModal('AI Natural Language Intake', html);

    const editor = document.getElementById('ai-intake-content');
    if (editor) {
        editor.addEventListener('input', () => {
            localStorage.setItem(draftKey, editor.innerHTML);
        });
    }
}
async function runAiIntakeRequest(scope = 'full', sectionId = null) {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId) return showToast('Please select a department first.');

    const content = (document.getElementById('ai-intake-content')?.innerText || '').trim();
    if (!content.trim()) return showToast('Please enter natural input content.');

    const payload = {
        department_id: Number(deptId),
        scope,
        section_id: sectionId,
        content,
        field_catalog: collectFieldCatalogFromForm(),
        apply_immediately: false
    };

    try {
        const response = await fetch('api/ai_normalize.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || data.error) {
            if (handleAiApiMissingKey(data)) return;
            throw new Error(data.error || 'AI normalize failed');
        }

        const changes = Array.isArray(data.changes) ? data.changes : [];
        if (changes.length === 0) {
            showToast('AI did not detect any changes to apply.');
            return;
        }

        const rows = changes.map((c, idx) => `
            <tr>
                <td style="padding:8px; border-bottom:1px solid #eee;">${idx + 1}</td>
                <td style="padding:8px; border-bottom:1px solid #eee;">${c.field_label}</td>
                <td style="padding:8px; border-bottom:1px solid #eee; max-width:220px; color:#666;">${(c.old_value || '').replace(/</g, '&lt;')}</td>
                <td style="padding:8px; border-bottom:1px solid #eee; max-width:260px;"><strong>${(c.new_value || '').replace(/</g, '&lt;')}</strong></td>
            </tr>
        `).join('');

        const html = `
            <p style="margin-bottom:12px;">AI đề xuất <strong>${changes.length}</strong> thay đổi. Xác nhận để áp dụng vào form.</p>
            <div style="max-height:360px; overflow:auto; border:1px solid #eee; border-radius:8px;">
                <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                    <thead style="background:#f5f6fa;">
                        <tr>
                            <th style="padding:8px; text-align:left;">#</th>
                            <th style="padding:8px; text-align:left;">Field</th>
                            <th style="padding:8px; text-align:left;">Old</th>
                            <th style="padding:8px; text-align:left;">New</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div style="text-align:right; margin-top:16px; display:flex; justify-content:flex-end; gap:10px;">
                <button class="btn btn-outline" onclick="closeMainModal()">Cancel</button>
                <button class="btn btn-success" onclick="applyAiChanges(window.__lastAiChanges)">
                    <i class="fas fa-check"></i> Apply Changes
                </button>
            </div>
        `;

        window.__lastAiChanges = changes;
        showModal('AI Changes Preview', html);
    } catch (e) {
        showToast('AI Intake Error: ' + e.message);
    }
}

function formatAiIntake(cmd) {
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    editor.focus();
    document.execCommand(cmd, false);
}

function setAiIntakeHeading(tag) {
    if (!tag) return;
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    editor.focus();
    document.execCommand('formatBlock', false, tag);
}

function setAiIntakeFontSize(size) {
    if (!size) return;
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    editor.focus();
    document.execCommand('fontSize', false, size);
}

function insertAiIntakeLink() {
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    editor.focus();
    const url = window.prompt('Enter URL (https://...)');
    if (!url) return;
    document.execCommand('createLink', false, url.trim());
}

function insertAiIntakeMiniTable() {
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    editor.focus();

    const rows = Math.min(8, Math.max(1, Number(window.prompt('Rows (1-8)', '2')) || 2));
    const cols = Math.min(8, Math.max(1, Number(window.prompt('Columns (1-8)', '2')) || 2));

    let html = '<table class="ai-note-table"><tbody>';
    for (let r = 0; r < rows; r++) {
        html += '<tr>';
        for (let c = 0; c < cols; c++) {
            html += `<td>${r === 0 ? `Header ${c + 1}` : '&nbsp;'}</td>`;
        }
        html += '</tr>';
    }
    html += '</tbody></table><p></p>';

    document.execCommand('insertHTML', false, html);
}
function copyAiIntakeNote() {
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    const text = (editor.innerText || '').trim();
    if (!text) return showToast('Nothing to copy.');
    navigator.clipboard.writeText(text).then(() => showToast('Note copied.'));
}

function clearAiIntakeNote() {
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    editor.innerHTML = '';
}

function saveAiIntakeDraft(key) {
    const editor = document.getElementById('ai-intake-content');
    if (!editor) return;
    localStorage.setItem(key, editor.innerHTML || '');
    showToast('Draft saved.');
}
function applyAiChanges(changes) {
    let applied = 0;

    (changes || []).forEach(change => {
        const sectionId = String(change.section_id);
        const fieldKey = change.field_key;
        const target = document.querySelector(`#section-${sectionId} [data-key="${fieldKey}"]`);
        if (!target) return;

        target.dataset.rawValue = change.old_value || target.value || '';
        target.dataset.isNormalized = '1';
        target.value = change.new_value || '';
        applied++;

        if (['process_asis', 'process_tobe', 'key_entities'].includes(fieldKey)) {
            renderSurveyDiagram(sectionId, fieldKey, target.value);
        }
    });

    closeMainModal();
    showToast(`Applied ${applied} AI changes.`);
}

async function generateAIReport() {
    const deptId = localStorage.getItem('current_dept_id');
    if (!deptId) return showToast('Please select a department first.');

    try {
        // Ensure report is generated from latest form state
        const saveOk = await saveSurveyData();
        if (!saveOk) return;
        showToast('Generating AI report...');
        const response = await fetch('api/ai_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                department_id: Number(deptId),
                report_type: 'ai_full'
            })
        });
        const data = await response.json();
        if (!response.ok || data.error) {
            if (handleAiApiMissingKey(data)) return;
            throw new Error(data.error || 'Failed to generate AI report');
        }

        document.getElementById('reportContent').innerHTML = data.html || '<p>No report content.</p>';
        document.getElementById('reportModal').style.display = 'flex';
    } catch (e) {
        showToast('AI Report Error: ' + e.message);
    }
}

/**
 * Helper to generate Mermaid code string with support for Phases, Decisions and GOTO
 */
function generateMermaidCode(fieldKey, text) {
    const lines = String(text).split('\n').filter(l => l.trim().length > 0);
    if (lines.length === 0) return '';

    if (fieldKey !== 'process_asis' && fieldKey !== 'process_tobe' && fieldKey !== 'key_entities') return '';

    if (fieldKey === 'key_entities') {
        let code = 'erDiagram\n';
        lines.forEach(line => {
            const parts = line.split(':');
            if (parts.length >= 2) {
                const entityName = parts[0].trim().replace(/\s+/g, '_');
                const attrs = parts[1].split(',').map(a => a.trim().replace(/\s+/g, '_')).filter(a => a);
                code += `    ${entityName} {\n`;
                attrs.forEach(attr => code += `        string ${attr}\n`);
                code += `    }\n`;
            }
        });
        return code;
    }

    // Process Mapping (asis or tobe)
    let code = 'graph TD\n';
    let subgraphActive = false;
    let nodeMap = {}; // Maps step numbers to internal IDs
    let connections = [];
    let subgraphCounter = 0;
    let lastNodeIdInSubgraph = null; // Track the last node added to the current subgraph

    // First pass: identify nodes and subgraphs
    lines.forEach((line, i) => {
        const trimmed = line.trim();
        if (trimmed.startsWith('PHASE:')) {
            if (subgraphActive) code += '    end\n';
            const phaseName = trimmed.replace('PHASE:', '').trim();
            code += `    subgraph SG${subgraphCounter++} ["${phaseName}"]\n`;
            subgraphActive = true;
            lastNodeIdInSubgraph = null; // Reset for new subgraph
        } else if (trimmed.startsWith('DECISION:')) {
            const content = trimmed.replace('DECISION:', '').trim();
            const parts = content.split('|');
            const label = parts[0].trim();
            const nodeId = `D${i}`;
            const wrappedLabel = wrapMermaidText(label, 25);
            code += `        ${nodeId}{{"${wrappedLabel}"}}\n`;
            nodeMap[`DECISION_${i}`] = nodeId; // Map decision to its ID

            // Parse branches
            for (let j = 1; j < parts.length; j++) {
                const branch = parts[j].trim();
                const branchParts = branch.split('->');
                if (branchParts.length === 2) {
                    const branchName = branchParts[0].trim();
                    const target = branchParts[1].trim();
                    connections.push({ from: nodeId, to: target, label: branchName });
                }
            }
            if (lastNodeIdInSubgraph) {
                connections.push({ from: lastNodeIdInSubgraph, to: `DECISION_${i}` });
            }
            lastNodeIdInSubgraph = nodeId;
        } else if (trimmed.startsWith('GOTO:')) {
            const target = trimmed.replace('GOTO:', '').trim();
            if (lastNodeIdInSubgraph) {
                connections.push({ from: lastNodeIdInSubgraph, to: target });
            }
            // GOTO itself doesn't create a node, it's a connection
        } else {
            // Standard step: "1. Do something" or just "Do something"
            const match = trimmed.match(/^(\d+)\.\s*(.*)/);
            const stepNum = match ? match[1] : (i + 1).toString();
            const label = match ? match[2] : trimmed;
            const nodeId = `N${stepNum.replace(/\D/g, '_')}_${i}`;
            const wrappedLabel = wrapMermaidText(label, 30);

            code += `        ${nodeId}["${wrappedLabel}"]\n`;
            nodeMap[stepNum] = nodeId;
            nodeMap[`STEP_${i}`] = nodeId; // Also map by index for sequential flow

            if (lastNodeIdInSubgraph) {
                // Only add sequential connection if the previous item wasn't a GOTO
                const prevLineTrimmed = lines[i - 1]?.trim();
                if (!prevLineTrimmed || !prevLineTrimmed.startsWith('GOTO:')) {
                    connections.push({ from: lastNodeIdInSubgraph, to: `STEP_${i}` });
                }
            }
            lastNodeIdInSubgraph = nodeId;
        }
    });

    if (subgraphActive) code += '    end\n';

    // Second pass: resolve connections
    const finalConnections = [];
    connections.forEach(conn => {
        let fromId = conn.from;
        let toId = conn.to;

        // Resolve 'from' node if it's a mapped step number or decision index
        if (typeof fromId === 'string' && fromId.startsWith('STEP_')) {
            fromId = nodeMap[fromId];
        } else if (typeof fromId === 'string' && fromId.startsWith('DECISION_')) {
            fromId = nodeMap[fromId];
        } else if (nodeMap[fromId]) { // If it's a step number like "1"
            fromId = nodeMap[fromId];
        }

        // Resolve 'to' node if it's a mapped step number
        if (nodeMap[toId]) {
            toId = nodeMap[toId];
        } else if (typeof toId === 'string' && toId.startsWith('STEP_')) {
            toId = nodeMap[toId];
        } else if (typeof toId === 'string' && toId.startsWith('DECISION_')) {
            toId = nodeMap[toId];
        } else {
            // If 'to' is a raw step number (e.g., "3" in GOTO: 3), try to find its node ID
            const targetNodeId = Object.keys(nodeMap).find(key => key === toId && !key.startsWith('STEP_') && !key.startsWith('DECISION_'));
            if (targetNodeId) {
                toId = nodeMap[targetNodeId];
            } else {
                // If target is not found, it might be an invalid reference or a future step.
                // For now, we'll just use the raw target string. Mermaid might error or ignore.
            }
        }

        if (fromId && toId) {
            const arrow = conn.label ? ` -- "${conn.label}" --> ` : ' --> ';
            finalConnections.push(`    ${fromId}${arrow}${toId}\n`);
        }
    });

    // Filter out duplicate connections
    const uniqueConnections = [...new Set(finalConnections)];
    code += uniqueConnections.join('');

    return code;
}

function wrapMermaidText(text, limit) {
    if (text.length <= limit) return text;
    return text.match(new RegExp('.{1,' + limit + '}(\\s|$)', 'g')).join('<br/>');
}

// These helper functions are not directly used by the new generateMermaidCode,
// as the new logic handles connections in a single pass with nodeMap and connections array.
// Keeping them as they were in the original instruction, though they might be redundant now.
function findPreviousNodeId(lines, currentIndex) {
    for (let i = currentIndex - 1; i >= 0; i--) {
        const line = lines[i].trim();
        if (line.match(/^\d+\./)) return line.match(/^(\d+)\./)[1];
        if (line.startsWith('DECISION:')) return `D${i}`; // This ID format is specific to the old logic
    }
    return null;
}

function getPreviousStepInfo(lines, currentIndex, nodeMap) {
    for (let i = currentIndex - 1; i >= 0; i--) {
        const line = lines[i].trim();
        if (line.match(/^\d+\./)) {
            const num = line.match(/^(\d+)\./)[1];
            return { id: num, isSpecial: false };
        }
        if (line.startsWith('DECISION:') || line.startsWith('GOTO:') || line.startsWith('PHASE:')) {
            return { id: null, isSpecial: true };
        }
    }
    return null;
}

function renderSurveyDiagram(sectionId, fieldKey, text) {
    const containerId = `survey-diagram-${fieldKey}`;
    const outerId = `diag-outer-${fieldKey}`;
    const container = document.getElementById(containerId);
    if (!container || !text) {
        if (document.getElementById(outerId)) document.getElementById(outerId).style.display = 'none';
        return;
    }

    const code = generateMermaidCode(fieldKey, text);

    if (code) {
        container.innerHTML = `<div class="mermaid">${code}</div>`;
        if (document.getElementById(outerId)) document.getElementById(outerId).style.display = 'block';

        const lineCount = String(text).split('\n').filter(l => l.trim().length > 0).length;
        const scale = lineCount > 18 ? 0.85 : 1.0;
        container.style.transform = `scale(${scale})`;
        container.dataset.zoom = scale;

        if (window.mermaid) {
            mermaid.run({ nodes: [container.querySelector('.mermaid')] });
        }
    } else {
        if (document.getElementById(outerId)) document.getElementById(outerId).style.display = 'none';
    }
}

function zoomSurveyDiagram(fieldKey, factor) {
    const container = document.getElementById(`survey-diagram-${fieldKey}`);
    if (!container) return;

    let currentZoom = parseFloat(container.dataset.zoom || 1.0);

    if (factor === 1.0) {
        currentZoom = 1.0; // Reset
    } else if (factor > 1.0) {
        currentZoom = Math.min(currentZoom + 0.1, 2.0); // Zoom in
    } else {
        currentZoom = Math.max(currentZoom - 0.1, 0.4); // Zoom out
    }

    container.style.transform = `scale(${currentZoom})`;
    container.dataset.zoom = currentZoom;
}







