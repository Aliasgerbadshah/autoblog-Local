<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIGNUP - AutoBlog & Marketing SaaS</title>
    <!-- Import Google Font: Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600;1,800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* LIGHT THEME SIGNUP: EMERALD / TEAL DISTINCT ACCENTS */
            --bg-gradient: linear-gradient(135deg, #a7f3d0 0%, #bae6fd 40%, #e0e7ff 70%, #fbcfe8 100%);
            --card-bg: rgba(255, 255, 255, 0.12);
            --inner-card-bg: #ffffff;
            --primary-emerald: #10b981; /* Distinct Emerald Green for Signup */
            --primary-hover: #059669;
            --text-dark: #0f172a;
            --text-muted: #475569;
            --input-bg: #f8fafc;
            --border-light: #e2e8f0;
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-color: rgba(15, 23, 42, 0.08);
            --badge-bg: rgba(16, 185, 129, 0.1);
            --badge-color: #059669;
            --badge-border: rgba(16, 185, 129, 0.25);
        }

        [data-theme="dark"] {
            /* DARK THEME SIGNUP VARIABLES */
            --bg-gradient: linear-gradient(135deg, #022c22 0%, #0f172a 35%, #064e3b 70%, #1e1b4b 100%);
            --card-bg: rgba(15, 23, 42, 0.15);
            --inner-card-bg: #1e293b;
            --primary-emerald: #34d399;
            --primary-hover: #059669;
            --text-dark: #f8fafc;
            --text-muted: #cbd5e1;
            --input-bg: #0f172a;
            --border-light: #334155;
            --glass-border: rgba(255, 255, 255, 0.12);
            --shadow-color: rgba(0, 0, 0, 0.5);
            --badge-bg: rgba(52, 211, 153, 0.15);
            --badge-color: #34d399;
            --badge-border: rgba(52, 211, 153, 0.3);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', -apple-system, sans-serif;
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }

        body {
            background: var(--bg-gradient);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite alternate;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
            color: var(--text-dark);
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .abstract-bg-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .abstract-shape {
            position: absolute;
            opacity: 0.8;
            filter: drop-shadow(0 15px 30px rgba(16, 185, 129, 0.35));
        }

        .shape-ring-1 {
            top: -8%;
            left: -5%;
            width: clamp(240px, 30vw, 380px);
            height: clamp(240px, 30vw, 380px);
            animation: floatRotate1 22s ease-in-out infinite alternate;
        }

        .shape-ring-2 {
            bottom: -12%;
            right: -8%;
            width: clamp(260px, 35vw, 420px);
            height: clamp(260px, 35vw, 420px);
            animation: floatRotate2 26s ease-in-out infinite alternate;
        }

        @keyframes floatRotate1 {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            50% { transform: translate(40px, 30px) rotate(180deg) scale(1.1); }
            100% { transform: translate(-20px, 50px) rotate(360deg) scale(0.95); }
        }

        @keyframes floatRotate2 {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            50% { transform: translate(-40px, -40px) rotate(-180deg) scale(1.15); }
            100% { transform: translate(30px, -20px) rotate(-360deg) scale(0.9); }
        }

        .outer-card {
            background: var(--card-bg);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            box-shadow: 0 30px 60px -12px var(--shadow-color), inset 0 0 0 1px rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 1040px;
            min-height: 640px;
            display: flex;
            overflow: hidden;
            position: relative;
            z-index: 10;
        }

        .theme-toggle-floating {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: var(--inner-card-bg);
            border: 1px solid var(--border-light);
            padding: 8px 14px;
            border-radius: 20px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.825rem;
            font-weight: 700;
            color: var(--text-dark);
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        .left-section {
            flex: 1.1;
            padding: 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .hero-title {
            font-family: 'Montserrat', sans-serif;
            font-size: clamp(2.4rem, 4.5vw, 3.4rem);
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.18;
            letter-spacing: -0.025em;
            margin-bottom: 16px;
        }

        .hero-subtitle {
            font-size: clamp(0.95rem, 1.8vw, 1.05rem);
            font-weight: 600;
            color: var(--text-muted);
            line-height: 1.6;
            max-width: 400px;
            margin-bottom: 24px;
        }

        .left-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 600;
            gap: 16px;
            flex-wrap: wrap;
        }

        .lang-selector {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--inner-card-bg);
            padding: 7px 14px;
            border-radius: 20px;
            border: 1px solid var(--border-light);
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
        }

        .footer-links {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
            font-weight: 700;
        }

        .footer-links a:hover {
            color: var(--text-dark);
        }

        .right-section {
            flex: 0.9;
            padding: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-card {
            background: var(--inner-card-bg);
            border-radius: 24px;
            padding: 36px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 15px 35px var(--shadow-color);
            border: 1px solid var(--border-light);
            border-top: 4px solid var(--primary-emerald); /* Distinct Emerald Top Border Accent for Signup */
        }

        .auth-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--badge-bg);
            color: var(--badge-color);
            border: 1px solid var(--badge-border);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }

        .form-header {
            margin-bottom: 20px;
        }

        .form-header h2 {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: 0.02em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .form-header p {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 11px 16px;
            background: var(--input-bg);
            border: 1px solid var(--border-light);
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
        }

        input:focus {
            background: #ffffff;
            border-color: var(--primary-emerald);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .checkbox-group a {
            color: var(--primary-emerald);
            text-decoration: none;
            font-weight: 700;
        }

        .social-divider {
            display: flex;
            align-items: center;
            margin: 18px 0;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .social-divider::before,
        .social-divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--border-light);
        }

        .social-divider span {
            padding: 0 12px;
        }

        .social-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .social-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            background: var(--inner-card-bg);
            border: 1px solid var(--border-light);
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-dark);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .social-btn:hover {
            background: var(--input-bg);
            transform: translateY(-1px);
        }

        .btn-emerald {
            width: 100%;
            padding: 13px;
            background: var(--primary-emerald);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.28);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-emerald:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .auth-footer-text {
            text-align: center;
            margin-top: 16px;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .auth-footer-text a {
            color: var(--primary-emerald);
            font-weight: 800;
            text-decoration: none;
            letter-spacing: 0.03em;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            display: none;
        }

        @media (max-width: 900px) {
            body {
                padding: 12px;
                align-items: flex-start;
            }

            .outer-card {
                flex-direction: column;
                min-height: auto;
                border-radius: 24px;
            }

            .left-section {
                padding: 32px 24px 20px 24px;
                align-items: center;
                text-align: center;
            }

            .hero-title {
                text-align: center;
                font-size: 2.8rem;
            }

            .hero-subtitle {
                text-align: center;
                margin: 0 auto 20px auto;
            }

            .left-footer {
                flex-direction: column;
                gap: 12px;
                align-items: center;
                justify-content: center;
                width: 100%;
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid var(--border-light);
            }

            .right-section {
                padding: 12px 20px 32px 20px;
                width: 100%;
            }

            .form-card {
                padding: 28px 22px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <button type="button" class="theme-toggle-floating" onclick="toggleTheme()">
        <span id="theme-icon">🌙</span> <span id="theme-text">Dark Mode</span>
    </button>

    <div class="abstract-bg-container">
        <svg class="abstract-shape shape-ring-1" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="100" cy="100" r="80" stroke="url(#grad1)" stroke-width="12" stroke-dasharray="10 15" />
            <circle cx="100" cy="100" r="55" stroke="url(#grad2)" stroke-width="6" />
            <defs>
                <linearGradient id="grad1" x1="0" y1="0" x2="200" y2="200" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#34d399"/>
                    <stop offset="1" stop-color="#38bdf8"/>
                </linearGradient>
                <linearGradient id="grad2" x1="0" y1="200" x2="200" y2="0" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#818cf8"/>
                    <stop offset="1" stop-color="#34d399"/>
                </linearGradient>
            </defs>
        </svg>

        <svg class="abstract-shape shape-ring-2" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="100" cy="100" r="75" stroke="url(#grad3)" stroke-width="16" stroke-dasharray="20 10" />
            <circle cx="100" cy="100" r="40" stroke="url(#grad4)" stroke-width="8" />
            <defs>
                <linearGradient id="grad3" x1="0" y1="0" x2="200" y2="200" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#f472b6"/>
                    <stop offset="1" stop-color="#34d399"/>
                </linearGradient>
                <linearGradient id="grad4" x1="200" y1="0" x2="0" y2="200" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#38bdf8"/>
                    <stop offset="1" stop-color="#a855f7"/>
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="outer-card">
        <!-- Left Section -->
        <div class="left-section">
            <div>
                <h1 class="hero-title">Fast, Efficient<br>& Productive</h1>
                <p class="hero-subtitle">Automate content generation, social campaigns & backlink distribution effortlessly.</p>
            </div>

            <div class="left-footer">
                <div class="lang-selector">
                    <span>🇺🇸 English</span> ▾
                </div>
                <div class="footer-links">
                    <a href="#">Terms</a>
                    <a href="#">Plans</a>
                    <a href="#">Contact Us</a>
                </div>
            </div>
        </div>

        <!-- Right Section Form -->
        <div class="right-section">
            <div class="form-card">
                <!-- DISTINCT NEW REGISTRATION BADGE & EMERALD TOP ACCENT FOR SIGNUP -->
                <span class="auth-pill-badge">✨ NEW REGISTRATION</span>

                <div class="form-header">
                    <h2>SIGNUP</h2>
                    <p>Create your new account to get started</p>
                </div>

                <div id="error-box" class="alert-error"></div>

                <form onsubmit="handleRegister(event)">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="username" placeholder="johndoe" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="email" placeholder="john@example.com" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="password" placeholder="••••••••" required>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="terms" required checked>
                        <label for="terms" style="margin:0; font-weight:normal;">I accept the <a href="#">Terms & Conditions</a></label>
                    </div>

                    <div class="social-divider">
                        <span>Or with</span>
                    </div>

                    <div class="social-buttons">
                        <button type="button" class="social-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                            Google
                        </button>
                        <button type="button" class="social-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.85c.67-.82 1.13-1.96.99-3.1-.98.04-2.18.66-2.88 1.48-.62.73-1.17 1.9-.1 1 3.02 1.08-.04 2.23-.63 2.89-1.4z"/></svg>
                            Apple
                        </button>
                    </div>

                    <button type="submit" class="btn-emerald">SIGNUP</button>
                </form>

                <div class="auth-footer-text">
                    Already have an account? <a href="/login.php">LOGIN</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeButton(newTheme);
        }

        function updateThemeButton(theme) {
            document.getElementById('theme-icon').innerText = theme === 'dark' ? '☀️' : '🌙';
            document.getElementById('theme-text').innerText = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }

        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeButton(savedTheme);

        async function handleRegister(e) {
            e.preventDefault();
            const errorBox = document.getElementById('error-box');
            errorBox.style.display = 'none';

            const res = await fetch('/api/auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: document.getElementById('username').value,
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                alert('Account created! Redirecting to login...');
                window.location.href = '/login.php';
            } else {
                errorBox.innerText = data.error || 'Registration failed.';
                errorBox.style.display = 'block';
            }
        }
    </script>
</body>
</html>
