<?php
session_start();

$errors = [
    "login" => $_SESSION['login_error'] ?? '',
    "register" => $_SESSION['register_error'] ?? ''
];

$activeForm = $_SESSION['active_form'] ?? 'login';
$success = $_SESSION['success'] ?? '';

// Clear session data but keep flash messages
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['success']);
unset($_SESSION['active_form']);

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VEDARA · Farmer Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 450px;
        }
        
        .logo {
            font-size: 36px;
            font-weight: 700;
            color: #2d5016;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
        }
        
        .card-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            text-align: center;
        }
        
        .card-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 24px;
            text-align: center;
            line-height: 1.5;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .tab {
            flex: 1;
            text-align: center;
            padding: 12px 0;
            font-size: 16px;
            font-weight: 600;
            color: #888;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            border: none;
            outline: none;
        }
        
        .tab.active {
            color: #2d5016;
            border-bottom: 2px solid #2d5016;
        }
        
        .form-container {
            display: none;
        }
        
        .form-container.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.2s, box-shadow 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2d5016;
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }
        
        .input-feedback {
            font-size: 13px;
            margin-top: 4px;
            min-height: 18px;
            color: #c62828;
            transition: 0.1s;
        }
        
        .password-note {
            font-size: 12px;
            color: #5f5f5f;
            margin-top: 4px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background-color: #2d5016;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background-color: #3a6c1e;
        }

        .btn:active {
            transform: scale(0.99);
        }
        
        .action-link {
            text-align: center;
            margin-top: 18px;
            font-size: 14px;
            color: #2d5016;
            cursor: pointer;
            text-decoration: underline;
            background: none;
            border: none;
            display: inline-block;
            width: auto;
            padding: 4px 8px;
        }
        
        .action-link:hover {
            color: #3a6c1e;
            text-decoration: none;
        }

        .feedback-banner {
            background-color: #e8f5e9;
            color: #1f3b0e;
            padding: 10px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            border-left: 4px solid #2d5016;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feedback-banner.error {
            background-color: #ffebee;
            border-left-color: #b71c1c;
            color: #891515;
        }

        .close-feedback {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            padding: 0 6px;
        }

        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        
        /* New styles for password toggle and strength meter */
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
            z-index: 10;
            background: white;
            padding: 0 5px;
        }
        
        .toggle-password:hover {
            color: #2d5016;
        }
        
        .password-strength {
            margin-top: 5px;
            height: 5px;
            width: 100%;
            background-color: #ddd;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        
        .strength-weak { background-color: #ff4757; width: 25%; }
        .strength-fair { background-color: #ffa502; width: 50%; }
        .strength-good { background-color: #2ed573; width: 75%; }
        .strength-strong { background-color: #2d5016; width: 100%; }
        
        .strength-text {
            font-size: 12px;
            margin-top: 3px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">VEDARA</div>
        
        <div class="card">
            <h1 class="card-title">Farmer Dashboard Access</h1>
            <p class="card-subtitle">Sign in to manage your farms and crops</p>

            <!-- Unified feedback area -->
            <div id="liveFeedback" class="feedback-banner" style="display: none;"></div>
            
            <!-- Success message from PHP -->
            <?php if (!empty($success)): ?>
            <div class="feedback-banner" id="phpSuccess">
                <?= htmlspecialchars($success) ?>
                <button class="close-feedback" onclick="this.parentElement.style.display='none'">✕</button>
            </div>
            <?php endif; ?>
            
            <!-- Error messages from PHP -->
            <?php if (!empty($errors['login'])): ?>
            <div class="feedback-banner error" id="phpLoginError">
                <?= htmlspecialchars($errors['login']) ?>
                <button class="close-feedback" onclick="this.parentElement.style.display='none'">✕</button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errors['register'])): ?>
            <div class="feedback-banner error" id="phpRegError">
                <?= htmlspecialchars($errors['register']) ?>
                <button class="close-feedback" onclick="this.parentElement.style.display='none'">✕</button>
            </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab <?= isActiveForm('login', $activeForm) ?>" data-tab="login">Login</button>
                <button class="tab <?= isActiveForm('register', $activeForm) ?>" data-tab="signup">Sign Up</button>
            </div>
            
            <!-- Login Form -->
            <div id="login-form" class="form-container <?= isActiveForm('login', $activeForm) ?>">
                <form action="login_register.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" id="login-email" placeholder="farmer@example.com" required>
                        <div id="login-email-feedback" class="input-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" class="form-input" id="login-password" placeholder="Enter your password" required>
                            <span class="toggle-password" onclick="togglePassword('login-password', this)">👁️</span>
                        </div>
                        <div id="login-password-feedback" class="input-feedback"></div>
                    </div>
                    
                    <button type="submit" name="login" class="btn" id="login-btn">Sign In</button>
                    
                    <button type="button" class="action-link" id="show-reset" style="margin-top: 16px;">Forgot Password? Reset here</button>
                </form>
            </div>
            
            <!-- Signup Form -->
            <div id="signup-form" class="form-container <?= isActiveForm('register', $activeForm) ?>">
                <form action="login_register.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-input" id="signup-name" placeholder="Graham Bell" required>
                        <div id="signup-name-feedback" class="input-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" id="signup-email" placeholder="farmer@example.com" required>
                        <div id="signup-email-feedback" class="input-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" class="form-input" id="signup-password" placeholder="Enter your password" required onkeyup="checkPasswordStrength(this.value)">
                            <span class="toggle-password" onclick="togglePassword('signup-password', this)">👁️</span>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Password strength</div>
                        <div class="password-note">Min. 6 characters</div>
                        <div id="signup-password-feedback" class="input-feedback"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">User type</label>
                        <select name="role" class="form-input" id="signup-user" required>
                            <option value="">Select user type</option>
                            <option value="farmer">Farmer</option>
                            <option value="contractor">Contractor</option>
                            <option value="company">Company</option>
                        </select>
                        <div id="signup-user-feedback" class="input-feedback"></div>
                    </div>
                    
                    <button type="submit" name="register" class="btn" id="signup-btn">Create Account</button>
                </form>
            </div>
            
            <!-- Reset Password Form -->
            <div id="reset-form" class="form-container">
                <form action="login_register.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="reset_email" class="form-input" id="reset-email" placeholder="farmer@example.com" required>
                        <div id="reset-email-feedback" class="input-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" class="form-input" id="reset-password" placeholder="Enter new password" required>
                            <span class="toggle-password" onclick="togglePassword('reset-password', this)">👁️</span>
                        </div>
                        <div class="password-note">Min. 6 characters</div>
                        <div id="reset-password-feedback" class="input-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" class="form-input" id="reset-confirm-password" placeholder="Confirm new password" required>
                            <span class="toggle-password" onclick="togglePassword('reset-confirm-password', this)">👁️</span>
                        </div>
                        <div id="reset-confirm-feedback" class="input-feedback"></div>
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn" id="reset-btn">Reset Password</button>
                    
                    <button type="button" class="action-link" id="back-to-login">← Back to Login</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password toggle function (defined outside IIFE to be globally accessible)
        function togglePassword(fieldId, element) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
            element.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        }
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            if (!strengthBar || !strengthText) return;
            
            // Remove existing classes
            strengthBar.classList.remove('strength-weak', 'strength-fair', 'strength-good', 'strength-strong');
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = 'Password strength';
                return;
            }
            
            let strength = 0;
            
            // Length check
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            
            // Character checks
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
            
            // Scale down to 0-4 for display
            strength = Math.min(4, Math.floor(strength / 2));
            
            if (strength === 0 || strength === 1) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak';
            } else if (strength === 2) {
                strengthBar.classList.add('strength-fair');
                strengthText.textContent = 'Fair';
            } else if (strength === 3) {
                strengthBar.classList.add('strength-good');
                strengthText.textContent = 'Good';
            } else if (strength >= 4) {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong';
            }
        }

        (function() {
            // UI elements
            const tabs = document.querySelectorAll('.tab');
            const forms = document.querySelectorAll('.form-container');
            const liveFeedback = document.getElementById('liveFeedback');
            
            // Show banner message
            function showBanner(message, isError = false) {
                liveFeedback.style.display = 'flex';
                liveFeedback.innerHTML = '';
                liveFeedback.className = 'feedback-banner' + (isError ? ' error' : '');
                
                const textNode = document.createTextNode(message);
                const closeBtn = document.createElement('span');
                closeBtn.textContent = '✕';
                closeBtn.className = 'close-feedback';
                closeBtn.setAttribute('aria-label', 'Close');
                closeBtn.onclick = function(e) {
                    e.stopPropagation();
                    liveFeedback.style.display = 'none';
                };
                
                liveFeedback.appendChild(textNode);
                liveFeedback.appendChild(closeBtn);
            }

            function hideBanner() {
                liveFeedback.style.display = 'none';
            }

            function clearAllFeedbacks() {
                document.querySelectorAll('.input-feedback').forEach(el => el.innerText = '');
            }

            // Tab switching
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    forms.forEach(form => form.classList.remove('active'));
                    document.getElementById(`${tabId}-form`).classList.add('active');
                    
                    hideBanner();
                    clearAllFeedbacks();
                });
            });
            
            // Show reset form
            document.getElementById('show-reset')?.addEventListener('click', () => {
                forms.forEach(form => form.classList.remove('active'));
                tabs.forEach(t => t.classList.remove('active'));
                document.getElementById('reset-form').classList.add('active');
                hideBanner();
                clearAllFeedbacks();
            });
            
            // Back to login
            document.getElementById('back-to-login')?.addEventListener('click', () => {
                forms.forEach(form => form.classList.remove('active'));
                tabs.forEach(t => t.classList.remove('active'));
                document.querySelector('.tab[data-tab="login"]').classList.add('active');
                document.getElementById('login-form').classList.add('active');
                hideBanner();
                clearAllFeedbacks();
            });
            
            // Client-side validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const formContainer = this.closest('.form-container');
                    if (!formContainer) return;
                    
                    const formId = formContainer.id;
                    
                    if (formId === 'login-form') {
                        if (!validateLogin()) e.preventDefault();
                    } else if (formId === 'signup-form') {
                        if (!validateSignup()) e.preventDefault();
                    } else if (formId === 'reset-form') {
                        if (!validateReset()) e.preventDefault();
                    }
                });
            });
            
            // Validation functions
            function validateLogin() {
                const email = document.getElementById('login-email').value.trim();
                const pass = document.getElementById('login-password').value;
                let isValid = true;

                const emailFb = document.getElementById('login-email-feedback');
                const passFb = document.getElementById('login-password-feedback');
                emailFb.innerText = '';
                passFb.innerText = '';

                if (!email) {
                    emailFb.innerText = 'Email is required.';
                    isValid = false;
                } else if (!email.includes('@') || !email.includes('.')) {
                    emailFb.innerText = 'Enter a valid email.';
                    isValid = false;
                }

                if (!pass) {
                    passFb.innerText = 'Password cannot be empty.';
                    isValid = false;
                }

                return isValid;
            }

            function validateSignup() {
                const name = document.getElementById('signup-name').value.trim();
                const email = document.getElementById('signup-email').value.trim();
                const pass = document.getElementById('signup-password').value;
                const user = document.getElementById('signup-user').value;

                const nameFb = document.getElementById('signup-name-feedback');
                const emailFb = document.getElementById('signup-email-feedback');
                const passFb = document.getElementById('signup-password-feedback');
                const userFb = document.getElementById('signup-user-feedback');
                
                nameFb.innerText = emailFb.innerText = passFb.innerText = userFb.innerText = '';

                let ok = true;
                if (!name) { nameFb.innerText = 'Full name required.'; ok = false; }
                if (!email) { emailFb.innerText = 'Email required.'; ok = false; }
                else if (!email.includes('@')) { emailFb.innerText = 'Invalid email.'; ok = false; }
                if (!pass) { passFb.innerText = 'Password required.'; ok = false; }
                else if (pass.length < 6) { passFb.innerText = 'Minimum 6 characters.'; ok = false; }
                if (!user) { userFb.innerText = 'Please select a user type.'; ok = false; }
                return ok;
            }

            function validateReset() {
                const email = document.getElementById('reset-email').value.trim();
                const pass = document.getElementById('reset-password').value;
                const confirm = document.getElementById('reset-confirm-password').value;

                const emailFb = document.getElementById('reset-email-feedback');
                const passFb = document.getElementById('reset-password-feedback');
                const confFb = document.getElementById('reset-confirm-feedback');

                emailFb.innerText = passFb.innerText = confFb.innerText = '';
                let ok = true;

                if (!email) { emailFb.innerText = 'Email required.'; ok = false; }
                else if (!email.includes('@')) { emailFb.innerText = 'Invalid email.'; ok = false; }
                if (!pass) { passFb.innerText = 'New password required.'; ok = false; }
                else if (pass.length < 6) { passFb.innerText = 'Min 6 characters.'; ok = false; }
                if (pass !== confirm) { confFb.innerText = 'Passwords do not match.'; ok = false; }

                return ok;
            }

            // Clear field feedback on input
            document.querySelectorAll('.form-input').forEach(inp => {
                inp.addEventListener('input', function() {
                    const parent = this.closest('.form-group');
                    if (parent) {
                        const feedback = parent.querySelector('.input-feedback');
                        if (feedback) feedback.innerText = '';
                    }
                });
            });

            // Enter key submission
            document.getElementById('login-password')?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('login-form').querySelector('form').requestSubmit();
                }
            });
            
            document.getElementById('signup-password')?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('signup-form').querySelector('form').requestSubmit();
                }
            });
            
            document.getElementById('reset-confirm-password')?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('reset-form').querySelector('form').requestSubmit();
                }
            });
        })();
    </script>
</body>
</html>