<?php
// config.php - Configuration de la base de données et sécurité
session_start();

class DatabaseConfig {
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'fenacobu1';
    private const DB_USER = 'root';
    private const DB_PASS = '00000000';
    
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO(
                    "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=utf8mb4",
                    self::DB_USER,
                    self::DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_FOUND_ROWS => true
                    ]
                );
            } catch(PDOException $e) {
                error_log("Erreur de connexion à la base de données: " . $e->getMessage());
                die("Erreur de connexion à la base de données");
            }
        }
        return self::$connection;
    }
}

class SecurityManager {
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Fonction pour créer les utilisateurs de test
function createTestUsers() {
    try {
        $db = DatabaseConfig::getConnection();
        
        // Utilisateurs de test à créer
        $users = [
            ['admin', 'admin123', 'admin@fenacobu.bi', 'Administrateur', 'Admin', 'FENACOBU'],
            ['caissier', 'caiss123', 'caissier@fenacobu.bi', 'Caissier', 'Jean', 'Dupont'],
            ['conseiller', 'cons123', 'conseiller@fenacobu.bi', 'Conseiller', 'Marie', 'Martin']
        ];
        
        foreach ($users as $user) {
            // Vérifier si l'utilisateur existe déjà
            $stmt = $db->prepare("SELECT id FROM agents WHERE username = ?");
            $stmt->execute([$user[0]]);
            
            if (!$stmt->fetch()) {
                $hashedPassword = SecurityManager::hashPassword($user[1]);
                
                $stmt = $db->prepare("
                    INSERT INTO agents (username, password, email, role, first_name, last_name, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $user[0], $hashedPassword, $user[2], $user[3], $user[4], $user[5]
                ]);
                
                echo "Utilisateur {$user[0]} créé (mot de passe: {$user[1]})<br>";
            } else {
                echo "L'utilisateur {$user[0]} existe déjà.<br>";
            }
        }
        
    } catch(Exception $e) {
        echo "Erreur lors de la création des utilisateurs: " . $e->getMessage();
    }
}

// Fonction pour initialiser toutes les tables nécessaires
function initializeDatabase() {
    try {
        $db = DatabaseConfig::getConnection();
        
        // Table agents (structure corrigée selon votre base)
        $sql = "CREATE TABLE IF NOT EXISTS agents (
            id_agents INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('Administrateur', 'Caissier', 'Conseiller') NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            failed_attempts INT(11) DEFAULT 0,
            locked_until TIMESTAMP NULL,
            password_reset_token VARCHAR(255) NULL,
            password_reset_expires TIMESTAMP NULL,
            remember_token VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            id_agence INT(11) NULL,
            telephone VARCHAR(20) NULL
        )";
        $db->exec($sql);
        echo "Table 'agents' créée avec succès!<br>";
        
        // Table clients (structure basique)
        $sql = "CREATE TABLE IF NOT EXISTS clients (
            id_client INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(50) NOT NULL,
            prenom VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE,
            telephone VARCHAR(20),
            adresse TEXT,
            revenu_mensuel DECIMAL(15,2) DEFAULT 0,
            score_credit INT DEFAULT 0,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $db->exec($sql);
        echo "Table 'clients' créée avec succès!<br>";
        
        // Table agences
        $sql = "CREATE TABLE IF NOT EXISTS agences (
            id_agence INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            adresse TEXT,
            telephone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $db->exec($sql);
        echo "Table 'agences' créée avec succès!<br>";
        
        // Table comptes
        $sql = "CREATE TABLE IF NOT EXISTS comptes (
            id_compte INT AUTO_INCREMENT PRIMARY KEY,
            num_compte VARCHAR(50) UNIQUE NOT NULL,
            id_client INT NOT NULL,
            type_compte ENUM('Épargne', 'Courant', 'Crédit') NOT NULL,
            solde DECIMAL(15,2) DEFAULT 0,
            statut ENUM('Actif', 'Suspendu', 'Fermé') DEFAULT 'Actif',
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_client) REFERENCES clients(id_client) ON DELETE CASCADE
        )";
        $db->exec($sql);
        echo "Table 'comptes' créée avec succès!<br>";
        
        // Table types_credit
        $sql = "CREATE TABLE IF NOT EXISTS types_credit (
            id_type_credit INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            taux_interet DECIMAL(5,2) NOT NULL,
            duree_max_mois INT NOT NULL,
            montant_min DECIMAL(15,2) NOT NULL,
            montant_max DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $db->exec($sql);
        echo "Table 'types_credit' créée avec succès!<br>";
        
        // Table demandes_credit
        $sql = "CREATE TABLE IF NOT EXISTS demandes_credit (
            id_demande INT AUTO_INCREMENT PRIMARY KEY,
            id_client INT NOT NULL,
            id_type_credit INT NOT NULL,
            montant DECIMAL(15,2) NOT NULL,
            duree_mois INT NOT NULL,
            statut ENUM('En attente', 'En étude', 'Approuvé', 'Rejeté') DEFAULT 'En attente',
            date_demande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_traitement TIMESTAMP NULL,
            commentaires TEXT,
            FOREIGN KEY (id_client) REFERENCES clients(id_client) ON DELETE CASCADE,
            FOREIGN KEY (id_type_credit) REFERENCES types_credit(id_type_credit)
        )";
        $db->exec($sql);
        echo "Table 'demandes_credit' créée avec succès!<br>";
        
        // Table guichets
        $sql = "CREATE TABLE IF NOT EXISTS guichets (
            id_guichet INT AUTO_INCREMENT PRIMARY KEY,
            id_agence INT NOT NULL,
            type_guichet ENUM('DAB', 'Caisse', 'Conseil') NOT NULL,
            statut ENUM('Actif', 'Maintenance', 'Hors service') DEFAULT 'Actif',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_agence) REFERENCES agences(id_agence)
        )";
        $db->exec($sql);
        echo "Table 'guichets' créée avec succès!<br>";
        
        // Table transactions
        $sql = "CREATE TABLE IF NOT EXISTS transactions (
            id_transaction INT AUTO_INCREMENT PRIMARY KEY,
            num_compte VARCHAR(50) NOT NULL,
            id_agent INT NOT NULL,
            type_transaction ENUM('Dépôt', 'Retrait', 'Virement') NOT NULL,
            montant DECIMAL(15,2) NOT NULL,
            statut ENUM('En cours', 'Terminée', 'Annulée') DEFAULT 'En cours',
            date_heure TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description TEXT,
            FOREIGN KEY (id_agent) REFERENCES agents(id) ON DELETE CASCADE
        )";
        $db->exec($sql);
        echo "Table 'transactions' créée avec succès!<br>";
        
        // Table log_operations
        $sql = "CREATE TABLE IF NOT EXISTS log_operations (
            id_log INT AUTO_INCREMENT PRIMARY KEY,
            table_name VARCHAR(50) NOT NULL,
            operation_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
            record_id INT NOT NULL,
            id_agent INT NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_agent) REFERENCES agents(id) ON DELETE SET NULL
        )";
        $db->exec($sql);
        echo "Table 'log_operations' créée avec succès!<br>";
        
        // Table login_attempts
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(50),
            success BOOLEAN NOT NULL,
            user_agent TEXT,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip_address, attempted_at),
            INDEX idx_username_time (username, attempted_at)
        )";
        $db->exec($sql);
        echo "Table 'login_attempts' créée avec succès!<br>";
        
        // Table sessions (corrigée pour référencer id_agents)
        $sql = "CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(255) PRIMARY KEY,
            agent_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agents(id_agents) ON DELETE CASCADE
        )";
        $db->exec($sql);
        echo "Table 'sessions' créée avec succès!<br>";
        
        echo "<br><strong>Toutes les tables ont été créées avec succès!</strong><br><br>";
        
        // Créer les utilisateurs de test
        createTestUsers();
        
        // Insérer quelques données de test
        insertTestData();
        
    } catch(Exception $e) {
        echo "Erreur lors de l'initialisation: " . $e->getMessage();
    }
}

// Fonction pour insérer des données de test
function insertTestData() {
    try {
        $db = DatabaseConfig::getConnection();
        
        // Insérer une agence de test
        $stmt = $db->prepare("SELECT COUNT(*) FROM agences");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $db->exec("INSERT INTO agences (nom, adresse, telephone) VALUES 
                ('Agence Centrale Bujumbura', 'Avenue de l\'Indépendance, Bujumbura', '+257 22 123 456')");
            echo "Agence de test créée<br>";
        }
        
        // Insérer des types de crédit de test
        $stmt = $db->prepare("SELECT COUNT(*) FROM types_credit");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $db->exec("INSERT INTO types_credit (nom, taux_interet, duree_max_mois, montant_min, montant_max) VALUES 
                ('Crédit Personnel', 12.5, 60, 100000, 5000000),
                ('Crédit Immobilier', 8.5, 240, 1000000, 50000000),
                ('Micro-crédit', 15.0, 24, 50000, 500000)");
            echo "Types de crédit de test créés<br>";
        }
        
        // Insérer des clients de test
        $stmt = $db->prepare("SELECT COUNT(*) FROM clients");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $db->exec("INSERT INTO clients (nom, prenom, email, telephone, revenu_mensuel, score_credit) VALUES 
                ('Nkurunziza', 'Pierre', 'pierre.n@email.com', '+257 79 123 456', 500000, 750),
                ('Ndayishimiye', 'Marie', 'marie.n@email.com', '+257 68 789 012', 300000, 680),
                ('Bukuru', 'Jean', 'jean.b@email.com', '+257 71 345 678', 800000, 820)");
            echo "Clients de test créés<br>";
        }
        
    } catch(Exception $e) {
        echo "Erreur lors de l'insertion des données de test: " . $e->getMessage();
    }
}

// Décommentez la ligne suivante pour initialiser la base de données (à faire une seule fois)
// initializeDatabase();

?>