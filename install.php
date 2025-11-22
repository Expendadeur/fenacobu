<?php
// Installation et création des tables
// install.php - Script d'installation (à exécuter une seule fois)
require_once 'config.php';

try {
    $db = DatabaseConfig::getConnection();
    
    // Créer les tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS agents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('Administrateur', 'Caissier', 'Conseiller') NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            failed_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            password_reset_token VARCHAR(255) NULL,
            password_reset_expires TIMESTAMP NULL,
            remember_token VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(50),
            success BOOLEAN NOT NULL,
            user_agent TEXT,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip_address, attempted_at),
            INDEX idx_username_time (username, attempted_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(255) PRIMARY KEY,
            agent_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($tables as $sql) {
        $db->exec($sql);
    }
    
    // Créer un utilisateur administrateur par défaut
    $hashedPassword = SecurityManager::hashPassword('Admin123!');
    $stmt = $db->prepare(
        "INSERT IGNORE INTO agents (username, password, email, role, first_name, last_name) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        'admin',
        $hashedPassword,
        'admin@fenacobu.com',
        'Administrateur',
        'Admin',
        'Système'
    ]);
    
    echo "Installation terminée avec succès!<br>";
    echo "Utilisateur admin créé:<br>";
    echo "- Username: admin<br>";
    echo "- Password: Admin123!<br>";
    echo "- Changez ce mot de passe après votre première connexion.<br>";
    
} catch(Exception $e) {
    echo "Erreur d'installation: " . $e->getMessage();
}

?>