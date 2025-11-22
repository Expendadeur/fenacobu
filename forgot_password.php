<?php
// forgot_password.php - Gestion de la réinitialisation de mot de passe
require_once 'config.php';

SecurityManager::setSecurityHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = SecurityManager::sanitizeInput($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Adresse e-mail invalide']);
    exit;
}

class PasswordReset {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function initiateReset($email) {
        // Vérifier si l'e-mail existe
        $stmt = $this->db->prepare(
            "SELECT id, username, first_name, last_name FROM agents WHERE email = ? AND is_active = 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Pour des raisons de sécurité, on renvoie toujours un message de succès
            return ['success' => true, 'message' => 'Si cette adresse e-mail existe, vous recevrez un lien de réinitialisation'];
        }
        
        // Générer un token de réinitialisation
        $resetToken = SecurityManager::generateSecureToken();
        $hashedToken = hash('sha256', $resetToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Sauvegarder le token
        $stmt = $this->db->prepare(
            "UPDATE agents SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?"
        );
        $stmt->execute([$hashedToken, $expiresAt, $user['id']]);
        
        // Envoyer l'e-mail (simulation - remplacez par votre système d'e-mail)
        $this->sendResetEmail($email, $user['first_name'] . ' ' . $user['last_name'], $resetToken);
        
        return ['success' => true, 'message' => 'Si cette adresse e-mail existe, vous recevrez un lien de réinitialisation'];
    }
    
    private function sendResetEmail($email, $fullName, $token) {
        $resetLink = "https://votre-domaine.com/reset_password.php?token=" . urlencode($token);
        
        $subject = "FENACOBU - Réinitialisation de mot de passe";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { background-color: #27ae60; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>FENACOBU</h1>
                    <h2>Réinitialisation de mot de passe</h2>
                </div>
                <div class='content'>
                    <p>Bonjour $fullName,</p>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le lien ci-dessous pour procéder à la réinitialisation :</p>
                    <p><a href='$resetLink' class='button'>Réinitialiser mon mot de passe</a></p>
                    <p>Ce lien expire dans 1 heure pour des raisons de sécurité.</p>
                    <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet e-mail.</p>
                </div>
                <div class='footer'>
                    <p>© FENACOBU - Système bancaire sécurisé</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: noreply@fenacobu.com' . "\r\n";
        
        // Utiliser mail() ou votre service d'e-mail préféré
        mail($email, $subject, $message, $headers);
        
        // Log de l'envoi
        error_log("Password reset email sent to: $email");
    }
}

$passwordReset = new PasswordReset();
$result = $passwordReset->initiateReset($email);
echo json_encode($result);

?>