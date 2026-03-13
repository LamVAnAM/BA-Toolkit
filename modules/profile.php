<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();
?>
<div class="module-container">
    <div class="section-header">
        <h1><i class="fas fa-user-circle"></i> User Profile</h1>
    </div>

    <div class="grid-layout">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-id-badge"></i> Personal Information</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="profile-username" disabled>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" id="profile-role" disabled>
                    </div>
                    <div class="form-group full">
                        <label>Full Name</label>
                        <input type="text" id="profile-full-name" placeholder="Your full name">
                    </div>
                    <div class="form-group full">
                        <label>Email</label>
                        <input type="email" id="profile-email" placeholder="you@example.com">
                    </div>
                    <div class="form-group">
                        <label>Sign-in Provider</label>
                        <input type="text" id="profile-provider" disabled>
                    </div>
                    <div class="form-group">
                        <label>Created At</label>
                        <input type="text" id="profile-created-at" disabled>
                    </div>
                </div>
                <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                    <button class="btn btn-primary" type="button" onclick="saveUserProfile()">
                        <i class="fas fa-save"></i> Save Profile
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
            </div>
            <div class="card-body">
                <div id="profile-password-note" class="status-text" style="margin-bottom:12px;"></div>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Current Password</label>
                        <input type="password" id="profile-current-password" placeholder="Current password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" id="profile-new-password" placeholder="At least 8 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="profile-confirm-password" placeholder="Repeat new password">
                    </div>
                </div>
                <div style="margin-top:16px; display:flex; justify-content:flex-end;">
                    <button class="btn btn-primary" type="button" onclick="changeUserPassword()">
                        <i class="fas fa-lock"></i> Update Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
