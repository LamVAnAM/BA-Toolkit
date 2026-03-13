<?php
// modules/settings.php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
?>
<div class="view-header">
    <div class="header-main">
        <h1><?= __('configuration') ?></h1>
        <p class="subtitle">Manage system infrastructure, security, and global application parameters.</p>
    </div>
</div>

<div class="grid-layout">
    <!-- General & Security -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-halved"></i> General & Security</h3>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>App Environment</label>
                    <select id="set-app-env">
                        <option value="local">local</option>
                        <option value="staging">staging</option>
                        <option value="production">production</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Google Client ID</label>
                    <input type="text" id="set-google-client-id" placeholder="xxxxxxxxxxxx-xxxxx.apps.googleusercontent.com">
                    <small>Used for Single Sign-On across the platform.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Storage Configuration -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-database"></i> Storage Path & Driver</h3>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Storage Driver</label>
                    <select id="set-storage-driver">
                        <option value="local">Local Filesystem</option>
                        <option value="s3">Amazon S3 / Compatible</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Local Root Path</label>
                    <input type="text" id="set-storage-local-root" placeholder="storage/uploads">
                </div>
                <div class="form-group">
                    <label>S3 Bucket</label>
                    <input type="text" id="set-s3-bucket" placeholder="my-bucket">
                </div>
                <div class="form-group">
                    <label>S3 Region</label>
                    <input type="text" id="set-s3-region" placeholder="ap-southeast-1">
                </div>
                <div class="form-group full">
                    <label>S3 Endpoint (Optional)</label>
                    <input type="text" id="set-s3-endpoint" placeholder="https://s3.amazonaws.com">
                </div>
                <div class="form-group full">
                    <label>S3 Prefix (Optional)</label>
                    <input type="text" id="set-s3-prefix" placeholder="uploads/project-a">
                </div>
            </div>
        </div>
    </div>

    <!-- Image Processing -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-image"></i> Image Upload & Processing</h3>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Max File Size (MB)</label>
                    <input type="number" id="set-upload-max-mb" value="5">
                </div>
                <div class="form-group">
                    <label>JPEG Quality</label>
                    <input type="number" id="set-upload-jpeg-quality" value="82">
                </div>
                <div class="form-group">
                    <label>Max Width (px)</label>
                    <input type="number" id="set-upload-max-width" value="1920">
                </div>
                <div class="form-group">
                    <label>Max Height (px)</label>
                    <input type="number" id="set-upload-max-height" value="1920">
                </div>
                <div class="form-group full">
                    <label>Antivirus Scan on Upload</label>
                    <select id="set-upload-require-av">
                        <option value="0">Disabled (Fast)</option>
                        <option value="1">Enabled (Secure)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Email (SMTP) -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-envelope"></i> SMTP Email Server</h3>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>SMTP Host</label>
                    <input type="text" id="set-smtp-host" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" id="set-smtp-port" value="587">
                </div>
                <div class="form-group">
                    <label>Encryption</label>
                    <select id="set-smtp-encryption">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="set-smtp-username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="set-smtp-password" placeholder="••••••••">
                    <small id="set-smtp-password-status" class="status-text"></small>
                </div>
                <div class="form-group">
                    <label>From Email</label>
                    <input type="email" id="set-smtp-from-email">
                </div>
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" id="set-smtp-from-name" value="BA Toolkit">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-copyright"></i> Footer Copyright</h3>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Footer Text</label>
                    <input type="text" id="set-footer-copyright-text" placeholder="Powered by vannamdigital">
                    <small>Displayed in the app footer for all authenticated users.</small>
                </div>
                <div class="form-group">
                    <label>Brand Name</label>
                    <input type="text" id="set-footer-brand-name" placeholder="vannamdigital">
                </div>
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" id="set-footer-contact-email" placeholder="namxp2@gmail.com">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="actions-bar sticky-bottom">
    <div class="container">
        <button class="btn btn-primary btn-lg" onclick="saveSettings()">
            <i class="fas fa-save"></i> Save All Settings
        </button>
    </div>
</div>

<style>
.sticky-bottom {
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
    padding: 15px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 30px;
    z-index: 100;
    box-shadow: 0 -5px 15px rgba(0,0,0,0.05);
}
.actions-bar {
    display: flex;
    justify-content: flex-end;
}
</style>
