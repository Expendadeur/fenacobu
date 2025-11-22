<?php
// login.php - Gestion de l'authentification CORRIGÉE
require_once 'config.php';

// Activer le rapport d'erreurs pour le débogage (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher les erreurs au client
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

SecurityManager::setSecurityHeaders();
header('Content-Type: application/json');

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier le Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type de contenu invalide']);
    exit;
}

// Lire les données JSON
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

$username = SecurityManager::sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';
$rememberMe = $input['rememberMe'] ?? false;

// Validation des entrées
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur et mot de passe requis']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

class LoginHandler {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function checkRateLimit($ip) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as attempts FROM login_attempts 
                 WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            $stmt->execute([$ip]);
            $result = $stmt->fetch();
            
            return $result['attempts'] < 10;
        } catch (Exception $e) {
            error_log("Erreur checkRateLimit: " . $e->getMessage());
            return true;
        }
    }
    
    public function logAttempt($ip, $username, $success, $userAgent) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO login_attempts (ip_address, username, success, user_agent) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$ip, $username, $success, $userAgent]);
        } catch (Exception $e) {
            error_log("Erreur logAttempt: " . $e->getMessage());
        }
    }
    
    public function authenticate($username, $password, $rememberMe, $ip, $userAgent) {
        // Vérifier la limitation de taux
        if (!$this->checkRateLimit($ip)) {
            $this->logAttempt($ip, $username, false, $userAgent);
            return ['success' => false, 'message' => 'Trop de tentatives. Réessayez plus tard.'];
        }
        
        // ✅ CORRECTION : Utiliser id_agent (singulier) partout
        try {
            $stmt = $this->db->prepare(
                "SELECT id_agent, username, password, email, role, first_name, last_name, 
                        is_active, failed_attempts, locked_until 
                 FROM agents WHERE username = ?"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Log pour débogage
            error_log("Utilisateur trouvé: " . ($user ? "OUI" : "NON"));
            
            if (!$user) {
                $this->logAttempt($ip, $username, false, $userAgent);
                return ['success' => false, 'message' => 'Identifiants incorrects'];
            }
            
            // Vérifier si l'utilisateur est actif
            if (!$user['is_active']) {
                $this->logAttempt($ip, $username, false, $userAgent);
                return ['success' => false, 'message' => 'Compte inactif'];
            }
            
            // Vérifier si le compte est verrouillé
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $this->logAttempt($ip, $username, false, $userAgent);
                return ['success' => false, 'message' => 'Compte temporairement verrouillé'];
            }
            
            // Vérifier le mot de passe
            error_log("Vérification du mot de passe pour: " . $username);
            if (!SecurityManager::verifyPassword($password, $user['password'])) {
                error_log("Mot de passe incorrect pour: " . $username);
                $this->handleFailedLogin($user['id_agent'], $ip, $username, $userAgent);
                return ['success' => false, 'message' => 'Identifiants incorrects'];
            }
            
            error_log("Authentification réussie pour: " . $username);
            
            // Authentification réussie
            $this->handleSuccessfulLogin($user, $rememberMe, $ip, $userAgent);
            
            return [
                'success' => true, 
                'role' => $user['role'],
                'message' => 'Connexion réussie'
            ];
            
        } catch (Exception $e) {
            error_log("Erreur lors de l'authentification: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Erreur interne du serveur'];
        }
    }
    
    private function handleFailedLogin($userId, $ip, $username, $userAgent) {
        try {
            // Incrémenter les tentatives échouées
            $stmt = $this->db->prepare(
                "UPDATE agents SET failed_attempts = failed_attempts + 1 WHERE id_agent = ?"
            );
            $stmt->execute([$userId]);
            
            // Vérifier si le compte doit être verrouillé
            $stmt = $this->db->prepare("SELECT failed_attempts FROM agents WHERE id_agent = ?");
            $stmt->execute([$userId]);
            $attempts = $stmt->fetch()['failed_attempts'];
            
            if ($attempts >= 5) {
                $stmt = $this->db->prepare(
                    "UPDATE agents SET locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id_agent = ?"
                );
                $stmt->execute([$userId]);
            }
            
            $this->logAttempt($ip, $username, false, $userAgent);
        } catch (Exception $e) {
            error_log("Erreur handleFailedLogin: " . $e->getMessage());
        }
    }
    
    private function handleSuccessfulLogin($user, $rememberMe, $ip, $userAgent) {
        try {
            // ✅ CORRECTION : Utiliser id_agent (singulier) au lieu de id_agents (pluriel)
            $stmt = $this->db->prepare(
                "UPDATE agents SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id_agent = ?"
            );
            $stmt->execute([$user['id_agent']]);
            
            // Créer une session sécurisée
            session_regenerate_id(true);
            
            // ✅ CORRECTION : Utiliser id_agent (singulier)
            $_SESSION['user_id'] = $user['id_agent'];
            $_SESSION['agent_id'] = $user['id_agent'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['login_time'] = time();
            $_SESSION['csrf_token'] = SecurityManager::generateSecureToken();
            
            error_log("Session créée pour: " . $user['username'] . " (ID: " . $user['id_agent'] . ")");
            
            // Enregistrer la session en base
            $sessionId = session_id();
            $stmt = $this->db->prepare(
                "INSERT INTO sessions (id, id_agent, ip_address, user_agent) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_activity = CURRENT_TIMESTAMP"
            );
            $stmt->execute([$sessionId, $user['id_agent'], $ip, $userAgent]);
            
            // Gérer "Remember Me"
            if ($rememberMe) {
                $rememberToken = SecurityManager::generateSecureToken();
                $hashedToken = hash('sha256', $rememberToken);
                
                $stmt = $this->db->prepare(
                    "UPDATE agents SET remember_token = ? WHERE id_agent = ?"
                );
                $stmt->execute([$hashedToken, $user['id_agent']]);
                
                setcookie('remember_token', $rememberToken, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }
            
            $this->logAttempt($ip, $user['username'], true, $userAgent);
            
        } catch (Exception $e) {
            error_log("Erreur handleSuccessfulLogin: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Relancer l'exception pour qu'elle soit capturée par authenticate()
        }
    }
}

// Traitement de la requête de connexion
try {
    $loginHandler = new LoginHandler();
    $result = $loginHandler->authenticate($username, $password, $rememberMe, $ip, $userAgent);
    
    error_log("Résultat de l'authentification: " . json_encode($result));
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Erreur critique: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Erreur système']);
}
?>