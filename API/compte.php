// ====== API/compte.php ======
<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

class CompteManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function createCompte($data) {
        try {
            $this->db->beginTransaction();
            
            // Générer un numéro de compte unique
            $numeroCompte = $this->generateNumeroCompte();
            
            $stmt = $this->db->prepare("
                INSERT INTO comptes (num_compte, id_client, type_compte, solde, statut)
                VALUES (?, ?, ?, ?, 'Actif')
            ");
            
            $stmt->execute([
                $numeroCompte,
                $data['id_client'],
                $data['type_compte'],
                $data['solde_initial'] ?? 0
            ]);
            
            $compteId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Compte créé avec succès',
                'compte' => [
                    'id_compte' => $compteId,
                    'num_compte' => $numeroCompte,
                    'type_compte' => $data['type_compte'],
                    'solde' => $data['solde_initial'] ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getSolde($numeroCompte) {
        try {
            $stmt = $this->db->prepare("
                SELECT solde FROM comptes WHERE num_compte = ? AND statut = 'Actif'
            ");
            
            $stmt->execute([$numeroCompte]);
            $result = $stmt->fetch();
            
            if (!$result) {
                throw new Exception('Compte non trouvé');
            }
            
            return [
                'success' => true,
                'solde' => $result['solde']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function generateNumeroCompte() {
        $prefix = 'FR76300010079';
        $random = str_pad(mt_rand(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
        $checksum = '89';
        
        return $prefix . substr($random, 0, 11) . $checksum;
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $manager = new CompteManager();
    
    switch ($action) {
        case 'solde':
            $compte = $_GET['compte'] ?? '';
            echo json_encode($manager->getSolde($compte));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $manager = new CompteManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            echo json_encode($manager->createCompte($input));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}
?>
