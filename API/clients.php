// ====== API/clients.php ======
<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

class ClientManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function searchClients($searchTerm) {
        try {
            $term = '%' . $searchTerm . '%';
            
            $stmt = $this->db->prepare("
                SELECT DISTINCT 
                    c.id_client, c.nom, c.prenom, c.email, c.telephone,
                    co.num_compte, co.type_compte, co.solde, co.statut
                FROM clients c
                LEFT JOIN comptes co ON c.id_client = co.id_client
                WHERE c.nom LIKE ? 
                   OR c.prenom LIKE ?
                   OR c.email LIKE ?
                   OR c.telephone LIKE ?
                   OR co.num_compte LIKE ?
                LIMIT 10
            ");
            
            $stmt->execute([$term, $term, $term, $term, $term]);
            $results = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getClientDetails($idClient) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, 
                       COUNT(co.id_compte) as nb_comptes,
                       SUM(co.solde) as solde_total
                FROM clients c
                LEFT JOIN comptes co ON c.id_client = co.id_client
                WHERE c.id_client = ?
                GROUP BY c.id_client
            ");
            
            $stmt->execute([$idClient]);
            $client = $stmt->fetch();
            
            if (!$client) {
                throw new Exception('Client non trouvé');
            }
            
            // Récupérer les comptes du client
            $stmt = $this->db->prepare("
                SELECT * FROM comptes WHERE id_client = ? ORDER BY date_creation DESC
            ");
            $stmt->execute([$idClient]);
            $client['comptes'] = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $client
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function createClient($data) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO clients (nom, prenom, email, telephone, revenu_mensuel)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'] ?? null,
                $data['telephone'] ?? null,
                $data['revenu_mensuel'] ?? 0
            ]);
            
            $clientId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Client créé avec succès',
                'client_id' => $clientId
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $manager = new ClientManager();
    
    switch ($action) {
        case 'search':
            $term = $_GET['term'] ?? '';
            echo json_encode($manager->searchClients($term));
            break;
        case 'details':
            $id = $_GET['id'] ?? 0;
            echo json_encode($manager->getClientDetails($id));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $manager = new ClientManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            echo json_encode($manager->createClient($input));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}
?>