// ====== API/auth.php ======
<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

class AuthManager {
    private $db;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function login($username, $password) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'];
            
            // Vérifier si l'IP est verrouillée
            if ($this->isIpLocked($ip)) {
                throw new Exception('Compte temporairement verrouillé. Veuillez réessayer plus tard.');
            }
            
            // Récupérer l'agent
            $stmt = $this->db->prepare("
                SELECT * FROM agents 
                WHERE username = ? AND is_active = 1
            ");
            $stmt->execute([$username]);
            $agent = $stmt->fetch();
            
            if (!$agent) {
                $this->logLoginAttempt($ip, $username, false);
                throw new Exception('Identifiants incorrects');
            }
            
            // Vérifier le mot de passe
            if (!SecurityManager::verifyPassword($password, $agent['password'])) {
                $this->incrementFailedAttempts($ip, $username);
                $this->logLoginAttempt($ip, $username, false);
                throw new Exception('Identifiants incorrects');
            }
            
            // Connexion réussie
            $this->resetFailedAttempts($ip);
            $this->logLoginAttempt($ip, $username, true);
            
            // Créer la session
            $_SESSION['agent_id'] = $agent['id_agents'];
            $_SESSION['username'] = $agent['username'];
            $_SESSION['role'] = $agent['role'];
            $_SESSION['agent_name'] = $agent['first_name'] . ' ' . $agent['last_name'];
            $_SESSION['agent_email'] = $agent['email'];
            $_SESSION['login_time'] = time();
            
            // Mettre à jour last_login
            $stmt = $this->db->prepare("
                UPDATE agents SET last_login = NOW() WHERE id_agents = ?
            ");
            $stmt->execute([$agent['id_agents']]);
            
            // Générer CSRF token
            $_SESSION['csrf_token'] = SecurityManager::generateSecureToken();
            
            return [
                'success' => true,
                'message' => 'Connexion réussie',
                'agent' => [
                    'id' => $agent['id_agents'],
                    'username' => $agent['username'],
                    'name' => $_SESSION['agent_name'],
                    'role' => $agent['role'],
                    'email' => $agent['email']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function logout() {
        try {
            session_destroy();
            return [
                'success' => true,
                'message' => 'Déconnexion réussie'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function checkAuth() {
        if (!isset($_SESSION['agent_id'])) {
            return [
                'authenticated' => false,
                'message' => 'Non authentifié'
            ];
        }
        
        return [
            'authenticated' => true,
            'agent' => [
                'id' => $_SESSION['agent_id'],
                'username' => $_SESSION['username'],
                'name' => $_SESSION['agent_name'],
                'role' => $_SESSION['role'],
                'email' => $_SESSION['agent_email']
            ]
        ];
    }
    
    public function changePassword($oldPassword, $newPassword) {
        try {
            if (!isset($_SESSION['agent_id'])) {
                throw new Exception('Non authentifié');
            }
            
            $stmt = $this->db->prepare("
                SELECT password FROM agents WHERE id_agents = ?
            ");
            $stmt->execute([$_SESSION['agent_id']]);
            $agent = $stmt->fetch();
            
            if (!SecurityManager::verifyPassword($oldPassword, $agent['password'])) {
                throw new Exception('Ancien mot de passe incorrect');
            }
            
            if (strlen($newPassword) < 8) {
                throw new Exception('Le nouveau mot de passe doit contenir au moins 8 caractères');
            }
            
            $hashedPassword = SecurityManager::hashPassword($newPassword);
            
            $stmt = $this->db->prepare("
                UPDATE agents SET password = ? WHERE id_agents = ?
            ");
            $stmt->execute([$hashedPassword, $_SESSION['agent_id']]);
            
            return [
                'success' => true,
                'message' => 'Mot de passe changé avec succès'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function isIpLocked($ip) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE ip_address = ? 
            AND success = 0 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, self::LOCKOUT_DURATION]);
        
        return $stmt->fetchColumn() >= self::MAX_LOGIN_ATTEMPTS;
    }
    
    private function incrementFailedAttempts($ip, $username) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, username, success, user_agent, attempted_at)
            VALUES (?, ?, 0, ?, NOW())
        ");
        $stmt->execute([$ip, $username, $_SERVER['HTTP_USER_AGENT'] ?? '']);
    }
    
    private function resetFailedAttempts($ip) {
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE ip_address = ? AND success = 0
        ");
        $stmt->execute([$ip]);
    }
    
    private function logLoginAttempt($ip, $username, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, username, success, user_agent, attempted_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$ip, $username, $success ? 1 : 0, $_SERVER['HTTP_USER_AGENT'] ?? '']);
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $manager = new AuthManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'login':
            echo json_encode($manager->login($input['username'] ?? '', $input['password'] ?? ''));
            break;
        case 'logout':
            echo json_encode($manager->logout());
            break;
        case 'change-password':
            echo json_encode($manager->changePassword($input['oldPassword'] ?? '', $input['newPassword'] ?? ''));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $manager = new AuthManager();
    
    if ($action === 'check') {
        echo json_encode($manager->checkAuth());
    }
}
?>
