// ====== API/echeances.php ======
<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

class EcheanceManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function encaisserCheque($data) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO echeancecredit (
                    id_dossier, numero_echeance, date_echeance, montant_capital, 
                    montant_interet, montant_total, statut, date_paiement
                ) VALUES (?, ?, ?, ?, ?, ?, 'A payer', NULL)
            ");
            
            $stmt->execute([
                $data['id_dossier'] ?? 1,
                $data['numero'],
                $data['date'],
                $data['montant'],
                0,
                $data['montant'],
            ]);
            
            $echeanceId = $this->db->lastInsertId();
            
            // Enregistrer la transaction
            $stmt = $this->db->prepare("
                INSERT INTO transaction1 (num_compte, id_agent, type_transaction, montant, statut, date_heure, description)
                VALUES (?, ?, 'Dépôt', ?, 'Terminée', NOW(), ?)
            ");
            
            $stmt->execute([
                $data['beneficiaire'],
                $_SESSION['agent_id'],
                $data['montant'],
                "Encaissement chèque n°{$data['numero']} - Banque: {$data['banque']}"
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Chèque n°{$data['numero']} encaissé avec succès",
                'echeance_id' => $echeanceId
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function payerEcheance($echeanceId) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                UPDATE echeancecredit 
                SET statut = 'Payé', date_paiement = NOW()
                WHERE id_echeance = ?
            ");
            $stmt->execute([$echeanceId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Échéance payée avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getEcheances($filtres = []) {
        try {
            $query = "
                SELECT ec.*, d.id_demande, d.montant as montant_demande
                FROM echeancecredit ec
                LEFT JOIN dossiercredit d ON ec.id_dossier = d.id_dossier
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filtres['statut'])) {
                $query .= " AND ec.statut = ?";
                $params[] = $filtres['statut'];
            }
            
            if (!empty($filtres['date_debut'])) {
                $query .= " AND DATE(ec.date_echeance) >= ?";
                $params[] = $filtres['date_debut'];
            }
            
            if (!empty($filtres['date_fin'])) {
                $query .= " AND DATE(ec.date_echeance) <= ?";
                $params[] = $filtres['date_fin'];
            }
            
            $query .= " ORDER BY ec.date_echeance DESC LIMIT 100";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $manager = new EcheanceManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'encaisser':
            echo json_encode($manager->encaisserCheque($input));
            break;
        case 'payer':
            echo json_encode($manager->payerEcheance($input['id']));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $manager = new EcheanceManager();
    
    if ($action === 'list') {
        $filtres = [
            'statut' => $_GET['statut'] ?? '',
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? ''
        ];
        echo json_encode($manager->getEcheances($filtres));
    }
}
?>