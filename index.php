<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FENACOBU - Connexion Agent</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e74c3c, #27ae60);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 400px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #e74c3c;
            font-size: 2.5em;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo p {
            color: #27ae60;
            font-size: 1.1em;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 10px rgba(39, 174, 96, 0.3);
            transform: translateY(-2px);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: #333;
        }

        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
            cursor: pointer;
        }

        .forgot-password {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            color: #c0392b;
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #e74c3c, #27ae60);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .error-message {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            display: none;
        }

        .success-message {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
            display: none;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #27ae60;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .security-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(39, 174, 96, 0.1);
            border-radius: 8px;
            font-size: 12px;
            color: #27ae60;
            text-align: center;
        }

        /* Modal pour mot de passe oublié */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>FENACOBU</h1>
            <p>Espace Agent Bancaire</p>
        </div>

        <div id="errorMessage" class="error-message"></div>
        <div id="successMessage" class="success-message"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" id="rememberMe" name="rememberMe">
                    Se souvenir de moi
                </label>
                <a href="#" class="forgot-password" id="forgotPasswordLink">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn-login">
                Se connecter
            </button>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Connexion en cours...</p>
            </div>
        </form>

          <!--<div class="security-info">
            🔒 Connexion sécurisée SSL - Vos données sont protégées
        </div>
    </div>-->

    <!-- Modal pour mot de passe oublié -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 style="color: #e74c3c; margin-bottom: 20px;">Réinitialiser le mot de passe</h2>
            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="emailReset">Adresse e-mail</label>
                    <input type="email" id="emailReset" name="email" required>
                </div>
                <button type="submit" class="btn-login">Envoyer le lien de réinitialisation</button>
            </form>
        </div>
    </div>

    <script>
        // Gestion de la sécurité et validation côté client
        class LoginSecurity {
            constructor() {
                this.maxAttempts = 5;
                this.lockoutTime = 15 * 60 * 1000; // 15 minutes
                this.initEventListeners();
                this.checkLockout();
            }

            initEventListeners() {
                document.getElementById('loginForm').addEventListener('submit', this.handleLogin.bind(this));
                document.getElementById('forgotPasswordLink').addEventListener('click', this.showForgotPasswordModal.bind(this));
                document.getElementById('forgotPasswordForm').addEventListener('submit', this.handleForgotPassword.bind(this));
                
                // Modal events
                const modal = document.getElementById('forgotPasswordModal');
                const closeBtn = document.querySelector('.close');
                
                closeBtn.addEventListener('click', () => modal.style.display = 'none');
                window.addEventListener('click', (e) => {
                    if (e.target === modal) modal.style.display = 'none';
                });

                // Gestion du "Remember Me"
                this.loadRememberedUser();
            }

            // Validation des entrées
            validateInput(input) {
                // Nettoyer les caractères potentiellement dangereux
                return input.replace(/[<>\"'%;()&+]/g, '');
            }

            // Vérification du verrouillage
            checkLockout() {
                const lockoutTime = localStorage.getItem('lockoutTime');
                if (lockoutTime && Date.now() < parseInt(lockoutTime)) {
                    const remainingTime = Math.ceil((parseInt(lockoutTime) - Date.now()) / 60000);
                    this.showError(`Compte verrouillé. Réessayez dans ${remainingTime} minutes.`);
                    document.getElementById('loginForm').style.pointerEvents = 'none';
                }
            }

            // Gestion des tentatives de connexion
            handleLoginAttempt(success) {
                let attempts = parseInt(localStorage.getItem('loginAttempts') || '0');
                
                if (success) {
                    localStorage.removeItem('loginAttempts');
                    localStorage.removeItem('lockoutTime');
                } else {
                    attempts++;
                    localStorage.setItem('loginAttempts', attempts.toString());
                    
                    if (attempts >= this.maxAttempts) {
                        const lockoutTime = Date.now() + this.lockoutTime;
                        localStorage.setItem('lockoutTime', lockoutTime.toString());
                        this.showError(`Trop de tentatives. Compte verrouillé pendant 15 minutes.`);
                        document.getElementById('loginForm').style.pointerEvents = 'none';
                        return false;
                    } else {
                        this.showError(`Tentative ${attempts}/${this.maxAttempts}. Identifiants incorrects.`);
                    }
                }
                return true;
            }

            async handleLogin(e) {
                e.preventDefault();
                
                const username = this.validateInput(document.getElementById('username').value);
                const password = document.getElementById('password').value;
                const rememberMe = document.getElementById('rememberMe').checked;

                if (!username || !password) {
                    this.showError('Veuillez remplir tous les champs.');
                    return;
                }

                this.showLoading(true);

                try {
                    const response = await fetch('login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            username: username,
                            password: password,
                            rememberMe: rememberMe,
                            csrf_token: this.generateCSRFToken()
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.handleLoginAttempt(true);
                        this.showSuccess('Connexion réussie! Redirection...');
                        
                        // Sauvegarder les informations si "Remember Me" est coché
                        if (rememberMe) {
                            localStorage.setItem('rememberedUser', username);
                        } else {
                            localStorage.removeItem('rememberedUser');
                        }

                        // Redirection basée sur le rôle
                        setTimeout(() => {
                            switch(data.role) {
                                case 'Administrateur':
                                    window.location.href = 'DashAdmin.php';
                                    break;
                                case 'Caissier':
                                    window.location.href = 'DashCaissier.php';
                                    break;
                                case 'Conseiller':
                                    window.location.href = 'DashConseiller.php';
                                    break;
                                default:
                                    this.showError('Rôle utilisateur non reconnu.');
                            }
                        }, 1500);
                    } else {
                        this.handleLoginAttempt(false);
                    }
                } catch (error) {
                    this.showError('Erreur de connexion. Veuillez réessayer.');
                    console.error('Login error:', error);
                } finally {
                    this.showLoading(false);
                }
            }

            async handleForgotPassword(e) {
                e.preventDefault();
                
                const email = this.validateInput(document.getElementById('emailReset').value);
                
                if (!email) {
                    this.showError('Veuillez entrer votre adresse e-mail.');
                    return;
                }

                try {
                    const response = await fetch('forgot_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: email,
                            csrf_token: this.generateCSRFToken()
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        this.showSuccess('Un lien de réinitialisation a été envoyé à votre e-mail.');
                        document.getElementById('forgotPasswordModal').style.display = 'none';
                    } else {
                        this.showError(data.message || 'Erreur lors de l\'envoi du lien.');
                    }
                } catch (error) {
                    this.showError('Erreur de connexion. Veuillez réessayer.');
                }
            }

            loadRememberedUser() {
                const rememberedUser = localStorage.getItem('rememberedUser');
                if (rememberedUser) {
                    document.getElementById('username').value = rememberedUser;
                    document.getElementById('rememberMe').checked = true;
                }
            }

            showForgotPasswordModal(e) {
                e.preventDefault();
                document.getElementById('forgotPasswordModal').style.display = 'block';
            }

            generateCSRFToken() {
                return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            }

            showError(message) {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                document.getElementById('successMessage').style.display = 'none';
            }

            showSuccess(message) {
                const successDiv = document.getElementById('successMessage');
                successDiv.textContent = message;
                successDiv.style.display = 'block';
                document.getElementById('errorMessage').style.display = 'none';
            }

            showLoading(show) {
                document.getElementById('loading').style.display = show ? 'block' : 'none';
                document.querySelector('.btn-login').style.opacity = show ? '0.7' : '1';
                document.querySelector('.btn-login').disabled = show;
            }
        }

        // Initialiser le système de sécurité
        document.addEventListener('DOMContentLoaded', () => {
            new LoginSecurity();
        });

        // Protection contre les attaques XSS
        window.addEventListener('beforeunload', () => {
            // Nettoyer les données sensibles
            document.getElementById('password').value = '';
        });

        // Désactiver le clic droit et les raccourcis clavier en production
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', (e) => {
            if (e.keyCode === 123 || // F12
                (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I
                (e.ctrlKey && e.shiftKey && e.keyCode === 74) || // Ctrl+Shift+J
                (e.ctrlKey && e.keyCode === 85)) { // Ctrl+U
                e.preventDefault();
            }
        });
    </script>
</body>
</html>