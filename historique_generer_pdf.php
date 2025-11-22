<?php
// historique_generer_pdf.php - Version corrigée avec gestion UTF-8
require_once 'config.php';

// Vérification de session et permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Caissier') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérification des paramètres
if (!isset($_GET['compte']) || !isset($_GET['periode'])) {
    die('Paramètres manquants');
}

$numCompte = SecurityManager::sanitizeInput($_GET['compte']);
$period = SecurityManager::sanitizeInput($_GET['periode']);
$transactionId = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;

$db = DatabaseConfig::getConnection();

try {
    // Récupérer les informations du compte
    $stmt = $db->prepare("
        SELECT 
            c.*,
            cl.nom as client_nom,
            cl.prenom as client_prenom,
            tc.libelle as type_compte_libelle,
            ag.nom as agence_nom
        FROM comptes c
        INNER JOIN clients cl ON c.id_client = cl.id_client
        INNER JOIN types_compte tc ON c.id_type_compte = tc.id_type_compte
        INNER JOIN agences ag ON c.id_agence_origine = ag.id_agence
        WHERE c.num_compte = ?
    ");
    $stmt->execute([$numCompte]);
    $compte = $stmt->fetch();

    if (!$compte) {
        die('Compte introuvable');
    }

    // Récupérer les transactions récentes (limité à 20 pour le PDF)
    $stmt = $db->prepare("
        SELECT 
            t.*,
            tt.libelle as type_libelle,
            tt.sens,
            tt.categorie
        FROM transactions t
        JOIN types_transaction tt ON t.id_type_transaction = tt.id_type_transaction
        WHERE t.num_compte = ?
        ORDER BY t.date_heure DESC
        LIMIT 20
    ");
    $stmt->execute([$numCompte]);
    $transactions = $stmt->fetchAll();

    // Informations du caissier
    $stmt = $db->prepare("SELECT * FROM agents WHERE id_agent = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $caissier = $stmt->fetch();

} catch (PDOException $e) {
    die('Erreur base de données: ' . $e->getMessage());
}

// Inclure TCPDF
require_once('tcpdf/tcpdf.php');

// Créer une nouvelle instance de TCPDF avec support UTF-8
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        // Logo
        $image_file = 'assets/images/logo-fenacobu.jpg';
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 25, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Set font
        $this->SetFont('helvetica', 'B', 16);
        
        // Title
        $this->SetY(15);
        $this->Cell(0, 10, 'FENACOBU', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Subtitle
        $this->SetY(25);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'EXTRAT DE COMPTE', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Line break
        $this->Ln(15);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Créer le PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Définir les informations du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('FENACOBU');
$pdf->SetTitle('Historique de Compte - ' . $numCompte);
$pdf->SetSubject('Extrait de compte');
$pdf->SetKeywords('FENACOBU, compte, historique, transactions');

// Marges
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Ajouter une page
$pdf->AddPage();

// Fonction pour nettoyer le texte
function cleanText($text) {
    if ($text === null) return '';
    // Convertir les caractères spéciaux
    $text = str_replace(
        ['à','á','â','ã','ä','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ù','ú','û','ü','ý','ÿ','À','Á','Â','Ã','Ä','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ñ','Ò','Ó','Ô','Õ','Ö','Ù','Ú','Û','Ü','Ý'],
        ['a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','y','A','A','A','A','A','C','E','E','E','E','I','I','I','I','N','O','O','O','O','O','U','U','U','U','Y'],
        $text
    );
    return $text;
}

// ==================== INFORMATIONS DU COMPTE ====================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 10, 'INFORMATIONS DU COMPTE', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);

// Informations de base
$clientName = cleanText($compte['client_nom'] . ' ' . $compte['client_prenom']);
$accountType = cleanText($compte['type_compte_libelle']);
$agenceName = cleanText($compte['agence_nom']);

$infoData = array(
    'Numero de compte: ' . $numCompte,
    'Client: ' . $clientName,
    'Type de compte: ' . $accountType,
    'Agence: ' . $agenceName,
    'Solde actuel: ' . number_format($compte['solde'], 0, ',', ' ') . ' BIF',
    'Solde disponible: ' . number_format($compte['solde_disponible'], 0, ',', ' ') . ' BIF',
    'Date de generation: ' . date('d/m/Y a H:i')
);

foreach ($infoData as $info) {
    $pdf->Cell(0, 6, $info, 0, 1);
}

$pdf->Ln(5);

// Information sur les frais
if ($transactionId) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 6, 'Frais de generation: 1,000 BIF (Transaction #' . $transactionId . ')', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
}

// ==================== RÉSUMÉ STATISTIQUES ====================
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(0, 10, 'RESUME STATISTIQUES', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);

$totalTransactions = count($transactions);
$totalCredits = 0;
$totalDebits = 0;

foreach ($transactions as $transaction) {
    if ($transaction['sens'] === 'CREDIT') {
        $totalCredits += $transaction['montant'];
    } else {
        $totalDebits += $transaction['montant_total'];
    }
}

$stats = array(
    'Nombre total de transactions: ' . $totalTransactions,
    'Total des credits: + ' . number_format($totalCredits, 0, ',', ' ') . ' BIF',
    'Total des debits: - ' . number_format($totalDebits, 0, ',', ' ') . ' BIF',
    'Solde net: ' . number_format($totalCredits - $totalDebits, 0, ',', ' ') . ' BIF',
    'Genere par: ' . cleanText($caissier['first_name'] . ' ' . $caissier['last_name'])
);

foreach ($stats as $stat) {
    $pdf->Cell(0, 6, $stat, 0, 1);
}

$pdf->Ln(8);

// ==================== TABLEAU DES TRANSACTIONS ====================
if (!empty($transactions)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(220, 240, 220);
    $pdf->Cell(0, 10, 'DERNIERES TRANSACTIONS', 0, 1, 'L', true);
    $pdf->Ln(2);
    
    // En-tête du tableau
    $pdf->SetFont('helvetica', 'B', 8);
    
    // Largeurs des colonnes
    $w = array(25, 35, 60, 25, 25, 20);
    
    // En-têtes
    $header = array('Date', 'Type', 'Description', 'Montant', 'Total', 'Sens');
    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Données
    $pdf->SetFont('helvetica', '', 7);
    $fill = false;
    
    foreach($transactions as $row) {
        // Vérifier si on besoin d'une nouvelle page
        if($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Réafficher l'en-tête
            $pdf->SetFont('helvetica', 'B', 8);
            for($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 7);
        }
        
        // Nettoyer les données
        $date = date('d/m/Y H:i', strtotime($row['date_heure']));
        $type = cleanText(substr($row['type_libelle'], 0, 20));
        $desc = cleanText(substr($row['description'] ?: '-', 0, 30));
        $montant = number_format($row['montant'], 0, ',', ' ');
        $total = number_format($row['montant_total'], 0, ',', ' ');
        $sens = $row['sens'];
        
        // Couleur selon le type
        if($sens == 'CREDIT') {
            $pdf->SetTextColor(0, 128, 0);
        } else {
            $pdf->SetTextColor(255, 0, 0);
        }
        
        $pdf->Cell($w[0], 6, $date, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, $type, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $desc, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[3], 6, $montant, 'LR', 0, 'R', $fill);
        $pdf->Cell($w[4], 6, $total, 'LR', 0, 'R', $fill);
        $pdf->Cell($w[5], 6, $sens, 'LR', 0, 'C', $fill);
        $pdf->Ln();
        
        $fill = !$fill;
        $pdf->SetTextColor(0, 0, 0);
    }
    
    // Fermer le tableau
    $pdf->Cell(array_sum($w), 0, '', 'T');
} else {
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Aucune transaction trouvee pour ce compte.', 0, 1, 'C');
}

$pdf->Ln(15);

// ==================== PIED DE PAGE PERSONNALISÉ ====================
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);

$pdf->Cell(0, 5, 'Document genere electroniquement par le systeme FENACOBU', 0, 1, 'C');
$pdf->Cell(0, 5, 'Le present document fait foi et est certifie conforme aux enregistrements du systeme', 0, 1, 'C');

// Signature
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Cachet et signature', 0, 1, 'R');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'FENACOBU - ' . $agenceName, 0, 1, 'R');

// Ligne de signature
$pdf->SetY($pdf->GetY() + 5);
$pdf->Line(120, $pdf->GetY(), 195, $pdf->GetY());

// Générer le PDF
$pdf->Output('historique_compte_' . $numCompte . '.pdf', 'D');
exit;