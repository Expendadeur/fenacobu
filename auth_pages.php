<?php
// auth_pages.php - Pages d'authentification pour chaque rôle
require_once 'config.php';
require_once 'session_manager.php';

// admin.php
class AdminPage {
    public static function render() {
        $sessionManager = new SessionManager();
        $sessionManager->requireRole('Administrateur');
        
        echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FENACOBU - Administration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: linear-gradient(135deg, #e74c3c, #27ae60); }
        .admin-container { background: white; padding: 30px; border-radius: 15px; max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; color: #e74c3c; margin-bottom: 30px; }
        .user-info { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; float: right; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>🏛️ FENACOBU - Administration</h1>
            <button class="logout-btn" onclick="logout()">Déconnexion</button>
        </div>
        <div class="user-info">
            <strong>Bienvenue, ' . htmlspecialchars($_SESSION['full_name']) . '</strong><br>
            Rôle: Administrateur | Connexion: ' . date('d/m/Y H:i', $_SESSION['login_time']) . '
        </div>
        <div class="content">
            <h2>Tableau de bord Administrateur</h2>
            <p>Accès complet au système bancaire.</p>
            <!-- Votre contenu dadministration ici -->
        </div>
    </div>
    <script>
        function logout() {
            if(confirm("Êtes-vous sûr de vouloir vous déconnecter ?")) {
                window.location.href = "logout.php";
            }
        }
        
        // Auto-déconnexion après 30 minutes d\'inactivité
        let inactivityTimer = setTimeout(() => {
            alert("Session expirée pour inactivité");
            logout();
        }, 1800000);
        
        // Réinitialiser le timer à chaque activité
        document.addEventListener("click", () => {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                alert("Session expirée pour inactivité");
                logout();
            }, 1800000);
        });
    </script>
</body>
</html>';
    }
}

// caiss.php
class CashierPage {
    public static function render() {
        $sessionManager = new SessionManager();
        $sessionManager->requireRole('Caissier');
        
        echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FENACOBU - Caisse</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: linear-gradient(135deg, #27ae60, #e74c3c); }
        .cashier-container { background: white; padding: 30px; border-radius: 15px; max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; color: #27ae60; margin-bottom: 30px; }
        .user-info { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; float: right; }
    </style>
</head>
<body>
    <div class="cashier-container">
        <div class="header">
            <h1>💰 FENACOBU - Caisse</h1>
            <button class="logout-btn" onclick="logout()">Déconnexion</button>
        </div>
        <div class="user-info">
            <strong>Bienvenue, ' . htmlspecialchars($_SESSION['full_name']) . '</strong><br>
            Rôle: Caissier | Connexion: ' . date('d/m/Y H:i', $_SESSION['login_time']) . '
        </div>
        <div class="content">
            <h2>Interface Caissier</h2>
            <p>Gestion des transactions et opérations de caisse.</p>
            <!-- Votre contenu caissier ici -->
        </div>
    </div>
    <script>
        function logout() {
            if(confirm("Êtes-vous sûr de vouloir vous déconnecter ?")) {
                window.location.href = "logout.php";
            }
        }
    </script>
</body>
</html>';
    }
}

// cons.php  
class ConsultantPage {
    public static function render() {
        $sessionManager = new SessionManager();
        $sessionManager->requireRole('Conseiller');
        
        echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FENACOBU - Conseil</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: linear-gradient(135deg, #3498db, #27ae60); }
        .consultant-container { background: white; padding: 30px; border-radius: 15px; max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; color: #3498db; margin-bottom: 30px; }
        .user-info { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; float: right; }
    </style>
</head>
<body>
    <div class="consultant-container">
        <div class="header">
            <h1>💼 FENACOBU - Conseil</h1>
            <button class="logout-btn" onclick="logout()">Déconnexion</button>
        </div>
        <div class="user-info">
            <strong>Bienvenue, ' . htmlspecialchars($_SESSION['full_name']) . '</strong><br>
            Rôle: Conseiller | Connexion: ' . date('d/m/Y H:i', $_SESSION['login_time']) . '
        </div>
        <div class="content">
            <h2>Interface Conseiller</h2>
            <p>Gestion des clients et conseil bancaire.</p>
            <!-- Votre contenu conseiller ici -->
        </div>
    </div>
    <script>
        function logout() {
            if(confirm("Êtes-vous sûr de vouloir vous déconnecter ?")) {
                window.location.href = "logout.php";
            }
        }
    </script>
</body>
</html>';
    }
}

?>
