<?php
require_once __DIR__ . '/../config/bootstrap.php';
$authMode = $_GET['auth'] ?? 'login';
$presetToken = trim((string)($_GET['token'] ?? ''));
?>
<div class="landing-shell">
    <div class="landing-background">
        <div class="glow-sphere glow-1"></div>
        <div class="glow-sphere glow-2"></div>
    </div>

    <section class="landing-hero">
        <div class="landing-content">
            <div class="hero-text">
                <span class="modern-badge">BA Ecosystem v2.0</span>
                <h1>Next-Gen Business Analysis Toolkit</h1>
                <p>
                    Standardize every BA delivery stage from intake and mapping to AI-assisted documentation on one modern, secure workspace.
                </p>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-val">15+</span>
                        <span class="stat-lab">BA Frameworks</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-val">AI</span>
                        <span class="stat-lab">Powered Insights</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-val">Real-time</span>
                        <span class="stat-lab">Architecture</span>
                    </div>
                </div>
            </div>

            <div class="auth-box">
                <div class="auth-nav">
                    <button id="tab-login" class="auth-btn active" onclick="toggleAuthMode('login')">Sign In</button>
                    <button id="tab-register" class="auth-btn" onclick="toggleAuthMode('register')">Register</button>
                </div>

                <div class="auth-body">
                    <div class="auth-intro">
                        <h2 id="auth-title">Welcome Back</h2>
                        <p id="auth-subtitle">Enter your credentials to continue your work.</p>
                    </div>

                    <div id="login-form-div" class="auth-form-wrapper">
                        <div class="input-stack">
                            <div class="field-group">
                                <label>Username or Email</label>
                                <div class="field-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="login-identity" placeholder="yourname@example.com">
                                </div>
                            </div>
                            <div class="field-group">
                                <label>Password</label>
                                <div class="field-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="login-password" placeholder="••••••••">
                                </div>
                            </div>
                        </div>
                        <button class="prime-btn" onclick="handleLogin()">
                            <span>Launch Workspace</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>

                        <div class="auth-divider">
                            <span>Security First Access</span>
                        </div>

                        <div id="google-login-btn" class="google-wrapper"></div>
                        <small id="google-login-hint" class="error-hint"></small>
                    </div>

                    <div id="register-form-div" class="auth-form-wrapper" style="display:none;">
                        <div class="input-stack">
                            <div class="field-group">
                                <label>Account Username</label>
                                <input type="text" id="reg-username" placeholder="johndoe">
                            </div>
                            <div class="field-group">
                                <label>Official Email</label>
                                <input type="email" id="reg-email" placeholder="john.doe@example.com">
                            </div>
                            <div class="field-group">
                                <label>Full Name</label>
                                <input type="text" id="reg-fullname" placeholder="John Doe">
                            </div>
                            <div class="field-group">
                                <label>Access Password</label>
                                <input type="password" id="reg-password" placeholder="Min. 8 characters">
                            </div>
                        </div>
                        <button class="prime-btn success" onclick="handleRegister()">
                            <span>Create Identity</span>
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </div>

                    <div id="forgot-form-div" class="auth-form-wrapper" style="display:none;">
                        <div class="field-group">
                            <label>Recovery Email</label>
                            <input type="email" id="forgot-email" placeholder="john.doe@example.com">
                        </div>
                        <button class="prime-btn" onclick="handleForgotPassword()">Request Access Link</button>
                    </div>

                    <div id="reset-form-div" class="auth-form-wrapper" style="display:none;">
                        <div class="field-group">
                            <label>Access Token</label>
                            <input type="text" id="reset-token" value="<?= htmlspecialchars($presetToken) ?>">
                        </div>
                        <div class="field-group">
                            <label>New Secure Password</label>
                            <input type="password" id="reset-password">
                        </div>
                        <button class="prime-btn" onclick="handleResetPassword()">Update Credentials</button>
                    </div>

                    <div class="auth-links">
                        <a href="#" onclick="toggleAuthMode('forgot'); return false;">Account Recovery</a>
                        <span class="bull">&bull;</span>
                        <a href="#" onclick="handleResendVerification(); return false;">Verification Issues</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
const AUTH_COPY = {
    login: {
        title: 'Welcome back',
        subtitle: 'Sign in with your internal account or Google.'
    },
    register: {
        title: 'Create your BA Toolkit account',
        subtitle: 'Create an account with username, email, and password.'
    },
    forgot: {
        title: 'Forgot your password?',
        subtitle: 'Enter your email to receive a password reset link.'
    },
    reset: {
        title: 'Reset password',
        subtitle: 'Enter the token from email and set a new password.'
    }
};

function toggleAuthMode(mode) {
    const forms = ['login', 'register', 'forgot', 'reset'];
    forms.forEach((item) => {
        const form = document.getElementById(`${item}-form-div`);
        const tab = document.getElementById(`tab-${item}`);
        if (form) form.style.display = item === mode ? 'block' : 'none';
        if (tab) tab.classList.toggle('active', item === mode);
    });

    const copy = AUTH_COPY[mode] || AUTH_COPY.login;
    document.getElementById('auth-title').innerText = copy.title;
    document.getElementById('auth-subtitle').innerText = copy.subtitle;
}

async function handleLogin() {
    const identity = document.getElementById('login-identity').value.trim();
    const password = document.getElementById('login-password').value;
    if (!identity || !password) return showToast('Please enter username/email and password.');

    try {
        const response = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identity, password })
        });
        const result = await response.json();
        if (result.success) {
            if (result.csrf_token) {
                window.CSRF_TOKEN = result.csrf_token;
            }
            location.reload();
            return;
        }
        if (result.error_code === 'EMAIL_NOT_VERIFIED') {
            showToast('Email is not verified yet. Please use Resend verification.');
            return;
        }
        showToast(result.error || 'Login failed.');
    } catch (e) {
        showToast('Error connecting to authentication API.');
    }
}

async function handleRegister() {
    const payload = {
        username: document.getElementById('reg-username').value.trim(),
        email: document.getElementById('reg-email').value.trim(),
        full_name: document.getElementById('reg-fullname').value.trim(),
        password: document.getElementById('reg-password').value
    };

    if (!payload.username || !payload.email || !payload.password) {
        return showToast('Username, email and password are required.');
    }

    try {
        const response = await fetch('api/auth.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Registration failed.');
        showToast(result.message || 'Registration successful.');
        toggleAuthMode('login');
    } catch (e) {
        showToast(e.message);
    }
}

async function handleForgotPassword() {
    const email = document.getElementById('forgot-email').value.trim();
    if (!email) return showToast('Please enter your email.');
    try {
        const response = await fetch('api/auth.php?action=forgot_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Request failed.');
        showToast(result.message || 'Reset email sent.');
    } catch (e) {
        showToast(e.message);
    }
}

async function handleResetPassword() {
    const token = document.getElementById('reset-token').value.trim();
    const password = document.getElementById('reset-password').value;
    if (!token || !password) return showToast('Please enter token and new password.');
    try {
        const response = await fetch('api/auth.php?action=reset_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, password })
        });
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Reset failed.');
        showToast(result.message || 'Password reset successful.');
        toggleAuthMode('login');
    } catch (e) {
        showToast(e.message);
    }
}

async function handleResendVerification() {
    const email = document.getElementById('reg-email')?.value.trim() || document.getElementById('forgot-email')?.value.trim() || '';
    if (!email) return showToast('Enter your email first.');
    try {
        const response = await fetch('api/auth.php?action=resend_verification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Resend failed.');
        showToast(result.message || 'Verification email sent.');
    } catch (e) {
        showToast(e.message);
    }
}

async function handleGoogleCredential(response) {
    try {
        const res = await fetch('api/auth_google.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_token: response.credential })
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            throw new Error(data.error || 'Google login failed.');
        }
        location.reload();
    } catch (e) {
        showToast('Google Login Error: ' + e.message);
    }
}

async function initGoogleLogin() {
    const hintEl = document.getElementById('google-login-hint');
    let clientId = window.GOOGLE_CLIENT_ID || '';
    if (!clientId) {
        try {
            const res = await fetch('api/public_config.php');
            const cfg = await res.json();
            clientId = cfg.google_client_id || '';
        } catch (e) {}
    }

    if (!clientId) {
        if (hintEl) hintEl.innerText = 'Google Login is not configured. Set Google Client ID in Settings.';
        return;
    }
    if (!window.google || !google.accounts || !google.accounts.id) {
        if (hintEl) hintEl.innerText = 'Google SDK is not loaded.';
        return;
    }

    google.accounts.id.initialize({
        client_id: clientId,
        callback: handleGoogleCredential
    });
    google.accounts.id.renderButton(
        document.getElementById('google-login-btn'),
        { theme: 'outline', size: 'large', text: 'signin_with', width: 320, locale: 'en' }
    );
}

document.addEventListener('DOMContentLoaded', () => {
    toggleAuthMode('<?= in_array($authMode, ['login', 'register', 'forgot', 'reset'], true) ? $authMode : 'login' ?>');
    setTimeout(initGoogleLogin, 150);
});

<?php if ($authMode === 'verify' && $presetToken !== ''): ?>
fetch('api/auth.php?action=verify_email', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: <?= json_encode($presetToken) ?> })
}).then(r => r.json()).then((result) => {
    if (result.success) {
        showToast(result.message || 'Email verified.');
        toggleAuthMode('login');
    } else {
        showToast(result.error || 'Verification failed.');
    }
});
<?php endif; ?>
</script>
