<?php
// modules/ai_toolkit.php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();
?>
<div class="view-header">
    <div class="header-main">
        <h1>AI Toolkit & Configuration</h1>
        <p class="subtitle">Personalize your AI experience. Choose a provider and configure your private API keys.</p>
    </div>
</div>

<div class="toolkit-container">
    <!-- 1. Provider Selection (Horizontal) -->
    <div class="card provider-selection-card">
        <div class="card-header">
            <h3><i class="fas fa-robot"></i> 1. Select AI Provider</h3>
        </div>
        <div class="card-body">
            <div class="provider-grid">
                <div class="provider-option" data-provider="openai" onclick="selectAIProvider('openai')">
                    <img src="https://openai.com/favicon.ico" alt="OpenAI">
                    <span>OpenAI</span>
                </div>
                <div class="provider-option" data-provider="gemini" onclick="selectAIProvider('gemini')">
                    <img src="https://www.gstatic.com/lamda/images/favicon_v2_16x16.png" alt="Gemini">
                    <span>Google Gemini</span>
                </div>
                <div class="provider-option" data-provider="groq" onclick="selectAIProvider('groq')">
                    <img src="https://groq.com/favicon.ico" alt="Groq">
                    <span>Groq Cloud</span>
                </div>
                <div class="provider-option" data-provider="ollama" onclick="selectAIProvider('ollama')">
                    <img src="https://ollama.com/favicon.ico" alt="Ollama">
                    <span>Ollama (Local)</span>
                </div>
                <div class="provider-option" data-provider="lmstudio" onclick="selectAIProvider('lmstudio')">
                    <img src="https://lmstudio.ai/favicon.ico" alt="LM Studio">
                    <span>LM Studio</span>
                </div>
            </div>
            <input type="hidden" id="set-ai-provider" value="groq">
        </div>
    </div>

    <!-- 2. Dynamic Configuration (Full Width) -->
    <div class="card config-card">
        <div class="card-header">
            <h3><i class="fas fa-sliders-h"></i> 2. Provider Settings</h3>
        </div>
        <div class="card-body">
            <div id="ai-provider-fields" class="form-grid">
                <div class="form-group full ai-field-endpoint">
                    <label>API Endpoint</label>
                    <input type="text" id="set-ai-endpoint" placeholder="https://api.openai.com/v1">
                    <small class="field-hint">The base URL for the provider's API.</small>
                </div>

                <div class="form-group full ai-field-key">
                    <label>API Key</label>
                    <div class="password-toggle-group">
                        <input type="password" id="set-ai-key" placeholder="sk-...">
                        <button type="button" onclick="togglePassword('set-ai-key')"><i class="fas fa-eye"></i></button>
                    </div>
                    <small id="set-ai-key-status" class="status-text"></small>
                </div>

                <div class="form-group ai-field-model">
                    <label>Primary Model</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="set-ai-model" list="ai-model-list" placeholder="gpt-4o, llama3-8b...">
                        <button type="button" id="btn-load-models" class="btn btn-mini btn-outline hidden" onclick="loadProviderModels()">
                            <i class="fas fa-sync-alt"></i> Load
                        </button>
                    </div>
                    <datalist id="ai-model-list"></datalist>
                </div>

                <div class="form-group ai-field-report-model">
                    <label>Report Model (Optional)</label>
                    <input type="text" id="set-ai-report-model" placeholder="Model for full reports">
                </div>

                <div class="form-group">
                    <label>Timeout (sec)</label>
                    <input type="number" id="set-ai-timeout" value="90">
                </div>
                
                <div class="form-group ai-field-ssl">
                    <label>SSL Verify</label>
                    <select id="set-ai-ssl-verify">
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
                
                <div class="form-group ai-field-ssl-host">
                    <label>Verify Host</label>
                    <select id="set-ai-ssl-verify-host">
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-outline" onclick="testAIConnection()">
                    <i class="fas fa-vial"></i> Test Connection
                </button>
                <button class="btn btn-primary" onclick="saveAISettings()">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </div>
        </div>
    </div>

    <!-- 3. Telemetry Table (Full Width) -->
    <div class="card telemetry-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> 3. Recent AI Activity</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" id="ai-telemetry-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Provider</th>
                            <th>Model</th>
                            <th>Action</th>
                            <th>Latency</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="ai-telemetry-body">
                        <tr><td colspan="6" style="text-align:center; padding: 20px;">Loading telemetry...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.toolkit-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

.provider-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.provider-option {
    flex: 1;
    min-width: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: var(--card-bg);
    text-align: center;
}
.provider-option:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.provider-option.active {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary);
    box-shadow: 0 0 0 1px var(--primary);
}
.provider-option img {
    width: 32px;
    height: 32px;
    margin-bottom: 12px;
    filter: grayscale(0.6);
    transition: filter 0.2s ease;
}
.provider-option.active img {
    filter: none;
}
.provider-option span {
    font-size: 0.85rem;
    font-weight: 700;
}

.field-hint {
    display: block;
    margin-top: 4px;
    font-size: 0.75rem;
    color: var(--text-disabled);
}

.hidden {
    display: none !important;
}

@media (max-width: 768px) {
    .provider-option {
        min-width: calc(50% - 6px);
    }
}
</style>
