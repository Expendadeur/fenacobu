// ====== API/transactions.php ======
<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

class TransactionManager {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }
    
    public function effectuerDepot($data) {
        try {
            $this->db->beginTransaction();
            
            $compte = $this->getCompte($data['compte']);
            if (!$compte) {
                throw new Exception('Compte non trouvé');
            }
            
            $montant = floatval($data['montant']);
            if ($montant <= 0) {
                throw new Exception('Montant invalide');
            }
            
            // Mettre à jour le solde du compte
            $stmt = $this->db->prepare("
                UPDATE comptes 
                SET solde = solde + ? 
                WHERE num_compte = ?
            ");
            $stmt->execute([$montant, $data['compte']]);
            
            // Enregistrer la transaction
            $stmt = $this->db->prepare("
                INSERT INTO transaction1 (num_compte, id_agent, type_transaction, montant, statut, date_heure, description)
                VALUES (?, ?, 'Dépôt', ?, 'Terminée', NOW(), ?)
            ");
            
            $stmt->execute([
                $data['compte'],
                $_SESSION['agent_id'],
                $montant,
                $data['commentaires'] ?? ''
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            // Enregistrer dans le log
            $this->logOperation('comptes', 'UPDATE', $compte['id_compte'], $data);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Dépôt de {$montant}€ effectué avec succès",
                'transaction_id' => $transactionId,
                'nouveau_solde' => $compte['solde'] + $montant
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function effectuerRetrait($data) {
        try {
            $this->db->beginTransaction();
            
            $compte = $this->getCompte($data['compte']);
            if (!$compte) {
                throw new Exception('Compte non trouvé');
            }
            
            $montant = floatval($data['montant']);
            if ($montant <= 0) {
                throw new Exception('Montant invalide');
            }
            
            if ($compte['solde'] < $montant) {
                throw new Exception('Solde insuffisant');
            }
            
            // Vérifier le PIN (simulation)
            if (!$this->verifierPin($data['pin'])) {
                throw new Exception('PIN incorrect');
            }
            
            // Mettre à jour le solde
            $stmt = $this->db->prepare("
                UPDATE comptes 
                SET solde = solde - ? 
                WHERE num_compte = ?
            ");
            $stmt->execute([$montant, $data['compte']]);
            
            // Enregistrer la transaction
            $stmt = $this->db->prepare("
                INSERT INTO transaction1 (num_compte, id_agent, type_transaction, montant, statut, date_heure, description)
                VALUES (?, ?, 'Retrait', ?, 'Terminée', NOW(), ?)
            ");
            
            $stmt->execute([
                $data['compte'],
                $_SESSION['agent_id'],
                -$montant,
                $data['commentaires'] ?? ''
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            $this->logOperation('comptes', 'UPDATE', $compte['id_compte'], $data);
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Retrait de {$montant}€ effectué avec succès",
                'transaction_id' => $transactionId,
                'nouveau_solde' => $compte['solde'] - $montant
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function effectuerVirement($data) {
        try {
            $this->db->beginTransaction();
            
            $compteEmetteur = $this->getCompte($data['emetteur']);
            $compteBeneficiaire = $this->getCompte($data['beneficiaire']);
            
            if (!$compteEmetteur || !$compteBeneficiaire) {
                throw new Exception('Compte émetteur ou bénéficiaire non trouvé');
            }
            
            if ($compteEmetteur['num_compte'] === $compteBeneficiaire['num_compte']) {
                throw new Exception('Les comptes doivent être différents');
            }
            
            $montant = floatval($data['montant']);
            if ($montant <= 0) {
                throw new Exception('Montant invalide');
            }
            
            if ($compteEmetteur['solde'] < $montant) {
                throw new Exception('Solde insuffisant');
            }
            
            // Débiter le compte émetteur
            $stmt = $this->db->prepare("
                UPDATE comptes 
                SET solde = solde - ? 
                WHERE num_compte = ?
            ");
            $stmt->execute([$montant, $data['emetteur']]);
            
            // Créditer le compte bénéficiaire
            $stmt = $this->db->prepare("
                UPDATE comptes 
                SET solde = solde + ? 
                WHERE num_compte = ?
            ");
            $stmt->execute([$montant, $data['beneficiaire']]);
            
            // Enregistrer les deux transactions
            $stmt = $this->db->prepare("
                INSERT INTO transaction1 (num_compte, id_agent, type_transaction, montant, statut, date_heure, description)
                VALUES (?, ?, 'Virement', ?, 'Terminée', NOW(), ?)
            ");
            
            $stmt->execute([
                $data['emetteur'],
                $_SESSION['agent_id'],
                -$montant,
                "Vers {$data['beneficiaire']}: {$data['motif']}"
            ]);
            
            $stmt->execute([
                $data['beneficiaire'],
                $_SESSION['agent_id'],
                $montant,
                "De {$data['emetteur']}: {$data['motif']}"
            ]);
            
            $this->logOperation('comptes', 'UPDATE', $compteEmetteur['id_compte'], $data);
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Virement de {$montant}€ effectué avec succès",
                'nouveau_solde_emetteur' => $compteEmetteur['solde'] - $montant,
                'nouveau_solde_beneficiaire' => $compteBeneficiaire['solde'] + $montant
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getCompte($numeroCompte) {
        $stmt = $this->db->prepare("
            SELECT c.*, cl.nom, cl.prenom, cl.email, cl.telephone
            FROM comptes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.num_compte = ? AND c.statut = 'Actif'
        ");
        $stmt->execute([$numeroCompte]);
        return $stmt->fetch();
    }
    
    public function verifierPin($pin) {
        // Simulation - à remplacer par une vérification réelle
        return strlen($pin) >= 4 && ctype_digit($pin);
    }
    
    public function logOperation($table, $operation, $recordId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO log_operations (table_name, operation_type, record_id, id_agent, new_values, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $table,
            $operation,
            $recordId,
            $_SESSION['agent_id'] ?? null,
            json_encode($data)
        ]);
    }
    
    public function getHistorique($filtres) {
        $query = "
            SELECT t.*, c.nom, c.prenom, a.first_name, a.last_name
            FROM transaction1 t
            JOIN comptes co ON t.num_compte = co.num_compte
            JOIN clients c ON co.id_client = c.id_client
            JOIN agents a ON t.id_agent = a.id_agents
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filtres['dateDebut'])) {
            $query .= " AND DATE(t.date_heure) >= ?";
            $params[] = $filtres['dateDebut'];
        }
        
        if (!empty($filtres['dateFin'])) {
            $query .= " AND DATE(t.date_heure) <= ?";
            $params[] = $filtres['dateFin'];
        }
        
        if (!empty($filtres['type'])) {
            $query .= " AND t.type_transaction = ?";
            $params[] = $filtres['type'];
        }
        
        if (!empty($filtres['compte'])) {
            $query .= " AND t.num_compte LIKE ?";
            $params[] = '%' . $filtres['compte'] . '%';
        }
        
        $query .= " ORDER BY t.date_heure DESC LIMIT 100";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $manager = new TransactionManager();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'depot':
            echo json_encode($manager->effectuerDepot($input));
            break;
        case 'retrait':
            echo json_encode($manager->effectuerRetrait($input));
            break;
        case 'virement':
            echo json_encode($manager->effectuerVirement($input));
            break;
        case 'historique':
            echo json_encode(['success' => true, 'data' => $manager->getHistorique($input)]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
}
?>