// ====== API/caisse.php ======
<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

class CaisseManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function ouvrirCaisse($fonds) {
        try {
            $_SESSION['caisse_ouverte'] = true;
            $_SESSION['solde_caisse_initial'] = floatval($fonds);
            $_SESSION['solde_caisse_courant'] = floatval($fonds);
            $_SESSION['heure_ouverture'] = date('Y-m-d H:i:s');
            
            return [
                'success' => true,
                'message' => 'Caisse ouverte avec succès',
                'solde' => floatval($fonds)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function fermerCaisse($montantCompte) {
        try {
            if (!isset($_SESSION['caisse_ouverte']) || !$_SESSION['caisse_ouverte']) {
                throw new Exception('Aucune caisse ouverte');
            }
            
            $this->db->beginTransaction();
            
            $soldeTheorique = $_SESSION['solde_caisse_courant'];
            $montantCompte = floatval($montantCompte);
            $ecart = $montantCompte - $soldeTheorique;
            
            // Enregistrer la clôture de caisse
            $stmt = $this->db->prepare("
                INSERT INTO operations_caisse (id_agent, heure_ouverture, heure_fermeture, solde_initial, solde_final, montant_compte, ecart)
                VALUES (?, ?, NOW(), ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['agent_id'],
                $_SESSION['heure_ouverture'],
                $_SESSION['solde_caisse_initial'],
                $montantCompte,
                $ecart
            ]);
            
            $this->db->commit();
            
            // Fermer la session caisse
            $_SESSION['caisse_ouverte'] = false;
            
            return [
                'success' => true,
                'message' => 'Caisse fermée avec succès',
                'solde_theorique' => $soldeTheorique,
                'solde_compte' => $montantCompte,
                'ecart' => $ecart
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getCaissStatus() {
        return [
            'success' => true,
            'caisse_ouverte' => $_SESSION['caisse_ouverte'] ?? false,
            'solde_courant' => $_SESSION['solde_caisse_courant'] ?? 0
        ];
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $manager = new CaisseManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'ouvrir':
            echo json_encode($manager->ouvrirCaisse($input['fonds']));
            break;
        case 'fermer':
            echo json_encode($manager->fermerCaisse($input['montant']));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $manager = new CaisseManager();
    
    if ($action === 'status') {
        echo json_encode($manager->getCaissStatus());
    }
}
?>
