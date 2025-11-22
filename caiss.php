<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interface Caissier - Guichet Bancaire</title>
    <style>
        :root {
            --rouge-principal: #dc143c;
            --rouge-fonce: #b91c3c;
            --rouge-clair: #f87171;
            --vert-principal: #228b22;
            --vert-fonce: #1a6e1a;
            --vert-clair: #4ade80;
            --blanc: #ffffff;
            --blanc-casse: #f8f8f8;
            --gris-clair: #f5f5f5;
            --text-dark: #1a1a1a;
            --shadow: rgba(220, 20, 60, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--rouge-principal) 0%, var(--rouge-fonce) 50%, var(--vert-principal) 100%);
            min-height: 100vh;
            padding: 15px;
            color: var(--text-dark);
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
            background: var(--blanc);
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            border: 3px solid var(--rouge-principal);
        }

        .header {
            background: linear-gradient(135deg, var(--rouge-principal) 0%, var(--rouge-fonce) 100%);
            color: var(--blanc);
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px var(--shadow);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .agent-info {
            font-size: 1rem;
            opacity: 0.95;
            background: rgba(255,255,255,0.1);
            padding: 10px 15px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .main-content {
            display: grid;
            grid-template-columns: 320px 1fr;
            min-height: calc(100vh - 160px);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--gris-clair) 0%, var(--blanc-casse) 100%);
            padding: 25px;
            border-right: 3px solid var(--rouge-principal);
            box-shadow: inset -5px 0 15px rgba(0,0,0,0.05);
        }

        .nav-item {
            display: block;
            width: 100%;
            padding: 18px 20px;
            margin-bottom: 12px;
            background: var(--blanc);
            border: 2px solid var(--rouge-principal);
            border-radius: 15px;
            text-align: left;
            cursor: pointer;
            transition: all 0.4s ease;
            font-size: 1rem;
            font-weight: 600;
            color: var(--rouge-principal);
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--rouge-principal), var(--vert-principal));
            transition: left 0.4s ease;
            z-index: 1;
        }

        .nav-item span {
            position: relative;
            z-index: 2;
        }

        .nav-item:hover::before,
        .nav-item.active::before {
            left: 0;
        }

        .nav-item:hover,
        .nav-item.active {
            color: var(--blanc);
            transform: translateX(8px);
            box-shadow: 0 8px 25px var(--shadow);
        }

        .nav-item:hover {
            border-color: var(--vert-principal);
        }

        .content-area {
            padding: 40px;
            background: var(--blanc);
            overflow-y: auto;
        }

        .section {
            display: none;
            animation: fadeInUp 0.6s ease;
        }

        .section.active {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section h2 {
            color: var(--rouge-principal);
            margin-bottom: 30px;
            font-size: 2rem;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }

        .section h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, var(--rouge-principal), var(--vert-principal));
            border-radius: 2px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--rouge-principal);
            font-size: 1.1rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--rouge-principal);
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: var(--blanc);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--vert-principal);
            box-shadow: 0 0 0 3px rgba(34, 139, 34, 0.1);
            transform: translateY(-2px);
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 15px;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            transition: left 0.3s ease;
        }

        .btn:hover::before {
            left: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--rouge-principal), var(--rouge-fonce));
            color: var(--blanc);
            border: 2px solid var(--rouge-principal);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--rouge-fonce), var(--rouge-principal));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 20, 60, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--vert-principal), var(--vert-fonce));
            color: var(--blanc);
            border: 2px solid var(--vert-principal);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, var(--vert-fonce), var(--vert-principal));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(34, 139, 34, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--rouge-principal), #ff1744);
            color: var(--blanc);
            border: 2px solid var(--rouge-principal);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #ff1744, var(--rouge-principal));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 23, 68, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--blanc), var(--blanc-casse));
            color: var(--rouge-principal);
            border: 2px solid var(--rouge-principal);
        }

        .btn-warning:hover {
            background: var(--rouge-principal);
            color: var(--blanc);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 20, 60, 0.3);
        }

        .card {
            background: var(--blanc);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(220, 20, 60, 0.1);
            margin-bottom: 25px;
            border: 2px solid var(--rouge-principal);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, var(--rouge-principal), var(--vert-principal));
        }

        .card h3 {
            color: var(--rouge-principal);
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .transaction-history {
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid var(--rouge-principal);
            border-radius: 15px;
            background: var(--blanc);
        }

        .transaction-item {
            padding: 20px;
            border-bottom: 2px solid var(--gris-clair);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .transaction-item:hover {
            background: var(--blanc-casse);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .amount-positive {
            color: var(--vert-principal);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .amount-negative {
            color: var(--rouge-principal);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .status-bar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 20px 30px;
            border-radius: 15px;
            display: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 1000;
            font-weight: 600;
            font-size: 1.1rem;
            border: 2px solid;
            min-width: 300px;
        }

        .status-bar.success {
            background: var(--vert-principal);
            color: var(--blanc);
            border-color: var(--vert-fonce);
        }

        .status-bar.error {
            background: var(--rouge-principal);
            color: var(--blanc);
            border-color: var(--rouge-fonce);
        }

        .status-bar.info {
            background: var(--blanc);
            color: var(--rouge-principal);
            border-color: var(--rouge-principal);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2000;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 3px solid var(--rouge-principal);
        }

        .spinner {
            border: 5px solid var(--gris-clair);
            border-top: 5px solid var(--rouge-principal);
            border-right: 5px solid var(--vert-principal);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .client-search {
            position: relative;
            margin-bottom: 25px;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--blanc);
            border: 2px solid var(--rouge-principal);
            border-top: none;
            border-radius: 0 0 12px 12px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 25px rgba(220, 20, 60, 0.2);
        }

        .search-result-item {
            padding: 15px 20px;
            cursor: pointer;
            border-bottom: 1px solid var(--gris-clair);
            transition: background 0.3s ease;
            color: var(--text-dark);
        }

        .search-result-item:hover {
            background: linear-gradient(135deg, var(--rouge-clair), var(--vert-clair));
            color: var(--blanc);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .balance-display {
            font-size: 2rem;
            font-weight: 900;
            text-align: center;
            padding: 25px;
            background: linear-gradient(135deg, var(--rouge-principal), var(--vert-principal));
            color: var(--blanc);
            border-radius: 20px;
            margin: 25px 0;
            box-shadow: 0 10px 30px rgba(220, 20, 60, 0.3);
            border: 3px solid var(--blanc);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .info-item {
            background: var(--blanc-casse);
            padding: 15px;
            border-radius: 10px;
            border-left: 5px solid var(--rouge-principal);
        }

        .info-item strong {
            color: var(--rouge-principal);
            font-weight: 700;
        }

        .caisse-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--vert-principal);
            color: var(--blanc);
            padding: 15px 25px;
            border-radius: 15px;
            font-weight: 700;
            box-shadow: 0 5px 15px rgba(34, 139, 34, 0.3);
            z-index: 1000;
            border: 2px solid var(--vert-fonce);
        }

        .error-message {
            background: linear-gradient(135deg, var(--rouge-clair), var(--rouge-principal));
            color: var(--blanc);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 15px 0;
            font-weight: 600;
            border: 2px solid var(--rouge-fonce);
        }

        .success-message {
            background: linear-gradient(135deg, var(--vert-clair), var(--vert-principal));
            color: var(--blanc);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 15px 0;
            font-weight: 600;
            border: 2px solid var(--vert-fonce);
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }

            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .content-area {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .btn {
                width: 100%;
                margin-right: 0;
            }
        }

        /* Animations supplémentaires */
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(220, 20, 60, 0.15);
        }

        .form-group input:valid,
        .form-group select:valid {
            border-color: var(--vert-principal);
        }

        .form-group input:invalid:not(:focus):not(:placeholder-shown) {
            border-color: var(--rouge-principal);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }

        /* Styles pour les tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: var(--blanc);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(220, 20, 60, 0.1);
            border: 2px solid var(--rouge-principal);
        }

        .data-table th {
            background: linear-gradient(135deg, var(--rouge-principal), var(--rouge-fonce));
            color: var(--blanc);
            padding: 15px;
            text-align: left;
            font-weight: 700;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--gris-clair);
        }

        .data-table tr:hover {
            background: var(--blanc-casse);
        }

        /* Indicateurs visuels */
        .status-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--vert-principal);
            color: var(--blanc);
        }

        .status-pending {
            background: var(--rouge-principal);
            color: var(--blanc);
        }

        .status-completed {
            background: var(--vert-clair);
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <div class="caisse-status" id="caisseStatus">
        Caisse: Fermée
    </div>

    <div class="container">
        <div class="header">
            <h1>🏦 Interface Caissier - Guichet Bancaire</h1>
            <div class="agent-info">
                <span id="agentName">👤 Agent: Jean Dupont</span> | 
                <span id="currentDate"></span>
            </div>
        </div>

        <div class="main-content">
            <div class="sidebar">
                <button class="nav-item active" onclick="showSection('accueil')">
                    <span>🏠 Accueil Client</span>
                </button>
                <button class="nav-item" onclick="showSection('depot')">
                    <span>💰 Dépôts</span>
                </button>
                <button class="nav-item" onclick="showSection('retrait')">
                    <span>💳 Retraits</span>
                </button>
                <button class="nav-item" onclick="showSection('virement')">
                    <span>🔄 Virements</span>
                </button>
                <button class="nav-item" onclick="showSection('encaissement')">
                    <span>📋 Encaissement Chèques</span>
                </button>
                <button class="nav-item" onclick="showSection('paiement')">
                    <span>💸 Paiement Factures</span>
                </button>
                <button class="nav-item" onclick="showSection('caisse')">
                    <span>🏦 Gestion Caisse</span>
                </button>
                <button class="nav-item" onclick="showSection('services')">
                    <span>🛍️ Vente Services</span>
                </button>
                <button class="nav-item" onclick="showSection('historique')">
                    <span>📊 Historique</span>
                </button>
            </div>

            <div class="content-area">
                <!-- Section Accueil Client -->
                <div id="accueil" class="section active">
                    <h2>👥 Accueil et Orientation des Clients</h2>
                    <div class="card">
                        <div class="client-search">
                            <label>🔍 Rechercher un client :</label>
                            <input type="text" id="searchClient" placeholder="Nom, numéro de compte ou téléphone..." onkeyup="searchClients()">
                            <div id="searchResults" class="search-results"></div>
                        </div>
                        
                        <div id="clientInfo" style="display: none;">
                            <h3>📋 Informations Client</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <p><strong>Nom:</strong> <span id="clientName"></span></p>
                                </div>
                                <div class="info-item">
                                    <p><strong>Compte:</strong> <span id="clientAccount"></span></p>
                                </div>
                                <div class="info-item">
                                    <p><strong>Type:</strong> <span id="clientType"></span></p>
                                </div>
                                <div class="info-item">
                                    <p><strong>Téléphone:</strong> <span id="clientPhone"></span></p>
                                </div>
                                <div class="info-item">
                                    <p><strong>Email:</strong> <span id="clientEmail"></span></p>
                                </div>
                            </div>
                            <div class="balance-display">
                                💰 Solde: <span id="clientBalance">0.00</span> €
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Dépôts -->
                <div id="depot" class="section">
                    <h2>💰 Dépôts d'Argent</h2>
                    <div class="card">
                        <form id="depotForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>📄 Numéro de Compte:</label>
                                    <input type="text" id="depotCompte" required pattern="FR[0-9]{25}" title="Format IBAN français requis">
                                </div>
                                <div class="form-group">
                                    <label>💵 Montant (€):</label>
                                    <input type="number" id="depotMontant" step="0.01" min="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>📝 Type de Dépôt:</label>
                                    <select id="depotType" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="especes">💵 Espèces</option>
                                        <option value="cheque">📄 Chèque</option>
                                        <option value="virement">🔄 Virement</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>💬 Commentaires:</label>
                                    <textarea id="depotCommentaires" rows="3" placeholder="Informations supplémentaires..."></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">✅ Effectuer le Dépôt</button>
                            <button type="reset" class="btn btn-warning">❌ Annuler</button>
                        </form>
                    </div>
                </div>

                <!-- Section Retraits -->
                <div id="retrait" class="section">
                    <h2>💳 Retraits d'Argent</h2>
                    <div class="card">
                        <form id="retraitForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>📄 Numéro de Compte:</label>
                                    <input type="text" id="retraitCompte" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>💵 Montant (€):</label>
                                    <input type="number" id="retraitMontant" step="0.01" min="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>🔒 Code PIN/Autorisation:</label>
                                    <input type="password" id="retraitPin" required minlength="4" maxlength="6">
                                </div>
                                <div class="form-group">
                                    <label>💬 Commentaires:</label>
                                    <textarea id="retraitCommentaires" rows="3" placeholder="Motif du retrait..."></textarea>
                                </div>
                            </div>
                            <div id="retraitSolde" class="balance-display" style="display: none;">
                                💰 Solde disponible: <span id="soldeDisponible">0.00</span> €
                            </div>
                            <button type="button" class="btn btn-primary" onclick="verifierSolde()">🔍 Vérifier Solde</button>
                            <button type="submit" class="btn btn-danger">💸 Effectuer le Retrait</button>
                            <button type="reset" class="btn btn-warning">❌ Annuler</button>
                        </form>
                    </div>
                </div>

                <!-- Section Virements -->
                <div id="virement" class="section">
                    <h2>🔄 Virements entre Comptes</h2>
                    <div class="card">
                        <form id="virementForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>📤 Compte Émetteur:</label>
                                    <input type="text" id="virementEmetteur" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>📥 Compte Bénéficiaire:</label>
                                    <input type="text" id="virementBeneficiaire" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>💵 Montant (€):</label>
                                    <input type="number" id="virementMontant" step="0.01" min="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>📝 Motif du Virement:</label>
                                    <input type="text" id="virementMotif" required placeholder="Ex: Paiement facture, Virement familial...">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">🔄 Effectuer le Virement</button>
                            <button type="reset" class="btn btn-warning">❌ Annuler</button>
                        </form>
                    </div>
                </div>

                <!-- Section Encaissement Chèques -->
                <div id="encaissement" class="section">
                    <h2>📋 Encaissement de Chèques</h2>
                    <div class="card">
                        <form id="encaissementForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>🔢 Numéro de Chèque:</label>
                                    <input type="text" id="chequeNumero" required pattern="[0-9]{7,10}">
                                </div>
                                <div class="form-group">
                                    <label>👤 Compte Bénéficiaire:</label>
                                    <input type="text" id="chequeBeneficiaire" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>💵 Montant (€):</label>
                                    <input type="number" id="chequeMontant" step="0.01" min="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>🏦 Banque Émettrice:</label>
                                    <input type="text" id="chequeBanque" required>
                                </div>
                                <div class="form-group">
                                    <label>📅 Date d'Émission:</label>
                                    <input type="date" id="chequeDate" required>
                                </div>
                                <div class="form-group">
                                    <label>📊 Statut:</label>
                                    <select id="chequeStatut" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="A payer">⏳ À payer</option>
                                        <option value="Paye">✅ Payé</option>
                                        <option value="En retard">⚠️ En retard</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">✅ Encaisser le Chèque</button>
                            <button type="reset" class="btn btn-warning">❌ Annuler</button>
                        </form>
                    </div>
                </div>

                <!-- Section Paiement Factures -->
                <div id="paiement" class="section">
                    <h2>💸 Paiement de Factures</h2>
                    <div class="card">
                        <form id="paiementForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>📄 Compte Débiteur:</label>
                                    <input type="text" id="paiementCompte" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>⚡ Type de Facture:</label>
                                    <select id="paiementType" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="electricite">⚡ Électricité</option>
                                        <option value="eau">💧 Eau</option>
                                        <option value="telephone">📞 Téléphone</option>
                                        <option value="internet">🌐 Internet</option>
                                        <option value="assurance">🛡️ Assurance</option>
                                        <option value="autre">📋 Autre</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>🔢 Numéro de Facture:</label>
                                    <input type="text" id="paiementNumero" required>
                                </div>
                                <div class="form-group">
                                    <label>💵 Montant (€):</label>
                                    <input type="number" id="paiementMontant" step="0.01" min="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>🏢 Bénéficiaire:</label>
                                    <input type="text" id="paiementBeneficiaire" required>
                                </div>
                                <div class="form-group">
                                    <label>📅 Date d'Échéance:</label>
                                    <input type="date" id="paiementEcheance">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">💸 Effectuer le Paiement</button>
                            <button type="reset" class="btn btn-warning">❌ Annuler</button>
                        </form>
                    </div>
                </div>

                <!-- Section Gestion Caisse -->
                <div id="caisse" class="section">
                    <h2>🏦 Gestion de la Caisse</h2>
                    <div class="form-grid">
                        <div class="card">
                            <h3>🌅 Ouverture de Caisse</h3>
                            <div class="form-group">
                                <label>💰 Fonds de Caisse Initial (€):</label>
                                <input type="number" id="fondsCaisse" step="0.01" min="0" value="5000.00">
                            </div>
                            <div class="balance-display">
                                🏦 Caisse Actuelle: <span id="caisseActuelle">5000.00</span> €
                            </div>
                            <button class="btn btn-success" onclick="ouvrirCaisse()">🔓 Ouvrir la Caisse</button>
                        </div>

                        <div class="card">
                            <h3>🌙 Clôture de Caisse</h3>
                            <div class="form-group">
                                <label>🧮 Montant Compté (€):</label>
                                <input type="number" id="montantCompte" step="0.01" min="0">
                            </div>
                            <div id="ecartCaisse" style="display: none;">
                                <div class="balance-display">
                                    📊 Écart: <span id="ecartMontant">0.00</span> €
                                </div>
                            </div>
                            <button class="btn btn-primary" onclick="calculerEcart()">📊 Calculer Écart</button>
                            <button class="btn btn-danger" onclick="fermerCaisse()">🔒 Fermer la Caisse</button>
                        </div>
                    </div>
                </div>

                <!-- Section Vente Services -->
                <div id="services" class="section">
                    <h2>🛍️ Vente de Services Bancaires</h2>
                    <div class="form-grid">
                        <div class="card">
                            <h3>💳 Demande de Carte Bancaire</h3>
                            <form id="carteForm">
                                <div class="form-group">
                                    <label>📄 Numéro de Compte:</label>
                                    <input type="text" id="carteCompte" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>💳 Type de Carte:</label>
                                    <select id="carteType" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="debit">💳 Carte de Débit</option>
                                        <option value="credit">💎 Carte de Crédit</option>
                                        <option value="prepayee">🎯 Carte Prépayée</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">📄 Demander une Carte</button>
                            </form>
                        </div>

                        <div class="card">
                            <h3>📋 Demande de Chéquier</h3>
                            <form id="chequierForm">
                                <div class="form-group">
                                    <label>📄 Numéro de Compte:</label>
                                    <input type="text" id="chequierCompte" required pattern="FR[0-9]{25}">
                                </div>
                                <div class="form-group">
                                    <label>📊 Nombre de Chèques:</label>
                                    <select id="chequierNombre" required>
                                        <option value="25">25 chèques</option>
                                        <option value="50">50 chèques</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">📋 Demander un Chéquier</button>
                            </form>
                        </div>

                        <div class="card">
                            <h3>💎 Ouverture Compte Épargne</h3>
                            <form id="epargneForm">
                                <div class="form-group">
                                    <label>👤 Client Existant:</label>
                                    <input type="text" id="epargneClient" required>
                                </div>
                                <div class="form-group">
                                    <label>💰 Dépôt Initial (€):</label>
                                    <input type="number" id="epargneDepot" step="0.01" min="100" required>
                                </div>
                                <div class="form-group">
                                    <label>📈 Type d'Épargne:</label>
                                    <select id="epargneType" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="livret">📖 Livret A</option>
                                        <option value="pel">🏠 PEL</option>
                                        <option value="cel">🏡 CEL</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success">💎 Ouvrir Compte Épargne</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Section Historique -->
                <div id="historique" class="section">
                    <h2>📊 Historique des Transactions</h2>
                    <div class="card">
                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                            <div class="form-group">
                                <label>📅 Date Début:</label>
                                <input type="date" id="dateDebut">
                            </div>
                            <div class="form-group">
                                <label>📅 Date Fin:</label>
                                <input type="date" id="dateFin">
                            </div>
                            <div class="form-group">
                                <label>📋 Type Transaction:</label>
                                <select id="typeTransaction">
                                    <option value="">Tous</option>
                                    <option value="Depot">💰 Dépôt</option>
                                    <option value="Retrait">💳 Retrait</option>
                                    <option value="Virement">🔄 Virement</option>
                                    <option value="Paiement">💸 Paiement</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>📄 Compte:</label>
                                <input type="text" id="compteFiltre" placeholder="Numéro de compte">
                            </div>
                        </div>
                        <button class="btn btn-primary" onclick="chargerHistorique()">🔍 Rechercher</button>
                        <button class="btn btn-success" onclick="exporterHistorique()">📄 Exporter PDF</button>
                    </div>

                    <div class="transaction-history">
                        <div id="historiqueList">
                            <div class="transaction-item">
                                <div>
                                    <strong>💰 Dépôt</strong> - Compte: FR7630001007941234567890189<br>
                                    <small>15/03/2024 - 14:30 - Agent: Jean Dupont</small>
                                </div>
                                <div class="amount-positive">+500.00 €</div>
                            </div>
                            <div class="transaction-item">
                                <div>
                                    <strong>💳 Retrait</strong> - Compte: FR7630001007941234567890190<br>
                                    <small>15/03/2024 - 15:45 - Agent: Jean Dupont</small>
                                </div>
                                <div class="amount-negative">-200.00 €</div>
                            </div>
                            <div class="transaction-item">
                                <div>
                                    <strong>🔄 Virement</strong> - Compte: FR7630001007941234567890191<br>
                                    <small>15/03/2024 - 16:00 - Vers: FR7630001007941234567890192</small>
                                </div>
                                <div class="amount-negative">-150.00 €</div>
                            </div>
                            <div class="transaction-item">
                                <div>
                                    <strong>💸 Paiement</strong> - Compte: FR7630001007941234567890189<br>
                                    <small>15/03/2024 - 16:15 - Facture électricité</small>
                                </div>
                                <div class="amount-negative">-75.50 €</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="statusBar" class="status-bar">
        <span id="statusMessage"></span>
    </div>

    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p><strong>Traitement en cours...</strong></p>
    </div>

    <script>
        // Variables globales
        let caisseOuverte = false;
        let soldeInitialCaisse = 5000.00;
        let soldeCourantCaisse = 5000.00;
        let clientActuel = null;
        let transactionsJour = [];

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Affichage de la date actuelle
            const today = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            document.getElementById('currentDate').textContent = today.toLocaleDateString('fr-FR', options);
            
            // Gestionnaires d'événements pour les formulaires
            setupFormHandlers();
            
            // Initialisation des dates dans les filtres
            const dateDebut = document.getElementById('dateDebut');
            const dateFin = document.getElementById('dateFin');
            dateDebut.value = today.toISOString().split('T')[0];
            dateFin.value = today.toISOString().split('T')[0];
        });

        // Fonction de navigation
        function showSection(sectionId) {
            // Masquer toutes les sections
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => section.classList.remove('active'));
            
            // Masquer tous les boutons actifs
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));
            
            // Afficher la section sélectionnée
            document.getElementById(sectionId).classList.add('active');
            
            // Activer le bouton correspondant
            event.target.classList.add('active');
        }

        // Configuration des gestionnaires de formulaires
        function setupFormHandlers() {
            // Formulaire dépôt
            document.getElementById('depotForm').addEventListener('submit', function(e) {
                e.preventDefault();
                effectuerDepot();
            });

            // Formulaire retrait
            document.getElementById('retraitForm').addEventListener('submit', function(e) {
                e.preventDefault();
                effectuerRetrait();
            });

            // Formulaire virement
            document.getElementById('virementForm').addEventListener('submit', function(e) {
                e.preventDefault();
                effectuerVirement();
            });

            // Formulaire encaissement
            document.getElementById('encaissementForm').addEventListener('submit', function(e) {
                e.preventDefault();
                encaisserCheque();
            });

            // Formulaire paiement
            document.getElementById('paiementForm').addEventListener('submit', function(e) {
                e.preventDefault();
                effectuerPaiement();
            });

            // Formulaires services
            document.getElementById('carteForm').addEventListener('submit', function(e) {
                e.preventDefault();
                demanderCarte();
            });

            document.getElementById('chequierForm').addEventListener('submit', function(e) {
                e.preventDefault();
                demanderChequier();
            });

            document.getElementById('epargneForm').addEventListener('submit', function(e) {
                e.preventDefault();
                ouvrirCompteEpargne();
            });

            // Validation en temps réel des formats IBAN
            const ibanFields = ['depotCompte', 'retraitCompte', 'virementEmetteur', 'virementBeneficiaire', 'chequeBeneficiaire', 'paiementCompte', 'carteCompte', 'chequierCompte'];
            ibanFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', function() {
                        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                        if (this.value.length > 2 && !this.value.startsWith('FR')) {
                            this.value = 'FR' + this.value.substring(2);
                        }
                    });
                }
            });
        }

        // Recherche de clients avec AJAX
        function searchClients() {
            const searchTerm = document.getElementById('searchClient').value;
            const resultsDiv = document.getElementById('searchResults');
            
            if (searchTerm.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            // Simulation d'une recherche AJAX
            showLoading();
            
            setTimeout(() => {
                const mockResults = [
                    { 
                        id: 1, 
                        nom: 'Martin Dubois', 
                        compte: 'FR7630001007941234567890189', 
                        type: 'Courant', 
                        telephone: '01.23.45.67.89', 
                        email: 'martin.dubois@email.com', 
                        solde: 2450.75 
                    },
                    { 
                        id: 2, 
                        nom: 'Sophie Bernard', 
                        compte: 'FR7630001007941234567890190', 
                        type: 'Épargne', 
                        telephone: '01.98.76.54.32', 
                        email: 'sophie.bernard@email.com', 
                        solde: 15230.50 
                    },
                    { 
                        id: 3, 
                        nom: 'Jean Durand', 
                        compte: 'FR7630001007941234567890191', 
                        type: 'Entreprise', 
                        telephone: '01.11.22.33.44', 
                        email: 'jean.durand@entreprise.com', 
                        solde: 45600.00 
                    },
                    { 
                        id: 4, 
                        nom: 'Marie Legrand', 
                        compte: 'FR7630001007941234567890192', 
                        type: 'Courant', 
                        telephone: '01.55.66.77.88', 
                        email: 'marie.legrand@email.com', 
                        solde: 890.25 
                    }
                ];

                const filteredResults = mockResults.filter(client => 
                    client.nom.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    client.compte.includes(searchTerm) ||
                    client.telephone.includes(searchTerm) ||
                    client.email.toLowerCase().includes(searchTerm.toLowerCase())
                );

                displaySearchResults(filteredResults);
                hideLoading();
            }, 800);
        }

        function displaySearchResults(results) {
            const resultsDiv = document.getElementById('searchResults');
            resultsDiv.innerHTML = '';

            if (results.length === 0) {
                resultsDiv.innerHTML = '<div class="search-result-item">❌ Aucun client trouvé</div>';
            } else {
                results.forEach(client => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.innerHTML = `
                        <strong>${client.nom}</strong><br>
                        <small>📄 ${client.compte} - ${client.type} | 💰 ${client.solde.toFixed(2)} €</small>
                    `;
                    item.onclick = () => selectClient(client);
                    resultsDiv.appendChild(item);
                });
            }

            resultsDiv.style.display = 'block';
        }

        function selectClient(client) {
            clientActuel = client;
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('searchClient').value = client.nom;
            
            // Afficher les informations du client
            document.getElementById('clientName').textContent = client.nom;
            document.getElementById('clientAccount').textContent = client.compte;
            document.getElementById('clientType').textContent = client.type;
            document.getElementById('clientPhone').textContent = client.telephone;
            document.getElementById('clientEmail').textContent = client.email;
            document.getElementById('clientBalance').textContent = client.solde.toFixed(2);
            
            document.getElementById('clientInfo').style.display = 'block';
            
            // Auto-complétion des champs de compte dans les autres sections
            const compteFields = ['depotCompte', 'retraitCompte', 'virementEmetteur', 'paiementCompte'];
            compteFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && field.value === '') {
                    field.value = client.compte;
                }
            });
        }

        // Fonctions pour les transactions
        function effectuerDepot() {
            if (!validerCaisseOuverte()) return;
            
            showLoading();
            const formData = {
                compte: document.getElementById('depotCompte').value,
                montant: parseFloat(document.getElementById('depotMontant').value),
                type: document.getElementById('depotType').value,
                commentaires: document.getElementById('depotCommentaires').value
            };

            if (!validerMontant(formData.montant)) {
                showStatus('Montant invalide', 'error');
                hideLoading();
                return;
            }

            if (!validerCompte(formData.compte)) {
                showStatus('Format de compte invalide', 'error');
                hideLoading();
                return;
            }

            // Simulation d'appel AJAX
            setTimeout(() => {
                // Mise à jour de la caisse
                soldeCourantCaisse += formData.montant;
                document.getElementById('caisseActuelle').textContent = soldeCourantCaisse.toFixed(2);
                
                // Mise à jour du solde client si sélectionné
                if (clientActuel && clientActuel.compte === formData.compte) {
                    clientActuel.solde += formData.montant;
                    document.getElementById('clientBalance').textContent = clientActuel.solde.toFixed(2);
                }
                
                showStatus(`Dépôt de ${formData.montant.toFixed(2)}€ effectué avec succès`, 'success');
                document.getElementById('depotForm').reset();
                hideLoading();
                
                // Enregistrement en base (simulation)
                enregistrerTransaction('Depot', formData.compte, formData.montant, formData.commentaires);
            }, 1200);
        }

        function effectuerRetrait() {
            if (!validerCaisseOuverte()) return;
            
            showLoading();
            const formData = {
                compte: document.getElementById('retraitCompte').value,
                montant: parseFloat(document.getElementById('retraitMontant').value),
                pin: document.getElementById('retraitPin').value,
                commentaires: document.getElementById('retraitCommentaires').value
            };

            if (!validerMontant(formData.montant)) {
                showStatus('Montant invalide', 'error');
                hideLoading();
                return;
            }

            if (!validerCompte(formData.compte)) {
                showStatus('Format de compte invalide', 'error');
                hideLoading();
                return;
            }

            // Vérification du solde disponible
            setTimeout(() => {
                if (formData.montant > soldeCourantCaisse) {
                    showStatus('Fonds insuffisants en caisse', 'error');
                    hideLoading();
                    return;
                }

                // Vérification du solde du compte client
                if (clientActuel && clientActuel.compte === formData.compte && formData.montant > clientActuel.solde) {
                    showStatus('Solde client insuffisant', 'error');
                    hideLoading();
                    return;
                }

                // Mise à jour de la caisse
                soldeCourantCaisse -= formData.montant;
                document.getElementById('caisseActuelle').textContent = soldeCourantCaisse.toFixed(2);
                
                // Mise à jour du solde client si sélectionné
                if (clientActuel && clientActuel.compte === formData.compte) {
                    clientActuel.solde -= formData.montant;
                    document.getElementById('clientBalance').textContent = clientActuel.solde.toFixed(2);
                }
                
                showStatus(`Retrait de ${formData.montant.toFixed(2)}€ effectué avec succès`, 'success');
                document.getElementById('retraitForm').reset();
                document.getElementById('retraitSolde').style.display = 'none';
                hideLoading();
                
                // Enregistrement en base (simulation)
                enregistrerTransaction('Retrait', formData.compte, -formData.montant, formData.commentaires);
            }, 1200);
        }

        function verifierSolde() {
            const compte = document.getElementById('retraitCompte').value;
            if (!compte) {
                showStatus('Veuillez saisir un numéro de compte', 'error');
                return;
            }

            if (!validerCompte(compte)) {
                showStatus('Format de compte invalide', 'error');
                return;
            }

            showLoading();
            
            // Simulation de vérification de solde
            setTimeout(() => {
                let soldeSimule;
                if (clientActuel && clientActuel.compte === compte) {
                    soldeSimule = clientActuel.solde;
                } else {
                    soldeSimule = Math.random() * 5000 + 100; // Solde aléatoire entre 100 et 5100
                }
                
                document.getElementById('soldeDisponible').textContent = soldeSimule.toFixed(2);
                document.getElementById('retraitSolde').style.display = 'block';
                hideLoading();
                showStatus('Solde vérifié avec succès', 'info');
            }, 800);
        }

        function effectuerVirement() {
            showLoading();
            const formData = {
                emetteur: document.getElementById('virementEmetteur').value,
                beneficiaire: document.getElementById('virementBeneficiaire').value,
                montant: parseFloat(document.getElementById('virementMontant').value),
                motif: document.getElementById('virementMotif').value
            };

            if (!validerMontant(formData.montant)) {
                showStatus('Montant invalide', 'error');
                hideLoading();
                return;
            }

            if (!validerCompte(formData.emetteur) || !validerCompte(formData.beneficiaire)) {
                showStatus('Format de compte invalide', 'error');
                hideLoading();
                return;
            }

            if (formData.emetteur === formData.beneficiaire) {
                showStatus('Les comptes émetteur et bénéficiaire doivent être différents', 'error');
                hideLoading();
                return;
            }

            setTimeout(() => {
                showStatus(`Virement de ${formData.montant.toFixed(2)}€ effectué avec succès`, 'success');
                document.getElementById('virementForm').reset();
                hideLoading();
                
                // Enregistrement des deux mouvements
                enregistrerTransaction('Virement', formData.emetteur, -formData.montant, `Vers ${formData.beneficiaire}: ${formData.motif}`);
                enregistrerTransaction('Virement', formData.beneficiaire, formData.montant, `De ${formData.emetteur}: ${formData.motif}`);
            }, 1200);
        }

        function encaisserCheque() {
            showLoading();
            const formData = {
                numero: document.getElementById('chequeNumero').value,
                beneficiaire: document.getElementById('chequeBeneficiaire').value,
                montant: parseFloat(document.getElementById('chequeMontant').value),
                banque: document.getElementById('chequeBanque').value,
                date: document.getElementById('chequeDate').value,
                statut: document.getElementById('chequeStatut').value
            };

            if (!validerMontant(formData.montant)) {
                showStatus('Montant invalide', 'error');
                hideLoading();
                return;
            }

            if (!validerCompte(formData.beneficiaire)) {
                showStatus('Format de compte bénéficiaire invalide', 'error');
                hideLoading();
                return;
            }

            setTimeout(() => {
                showStatus(`Chèque n°${formData.numero} encaissé avec succès`, 'success');
                document.getElementById('encaissementForm').reset();
                hideLoading();
                
                // Enregistrement de l'échéance
                enregistrerEcheance(formData);
            }, 1200);
        }

        function effectuerPaiement() {
            showLoading();
            const formData = {
                compte: document.getElementById('paiementCompte').value,
                type: document.getElementById('paiementType').value,
                numero: document.getElementById('paiementNumero').value,
                montant: parseFloat(document.getElementById('paiementMontant').value),
                beneficiaire: document.getElementById('paiementBeneficiaire').value,
                echeance: document.getElementById('paiementEcheance').value
            };

            if (!validerMontant(formData.montant)) {
                showStatus('Montant invalide', 'error');
                hideLoading();
                return;
            }

            if (!validerCompte(formData.compte)) {
                showStatus('Format de compte invalide', 'error');
                hideLoading();
                return;
            }

            setTimeout(() => {
                showStatus(`Paiement de ${formData.montant.toFixed(2)}€ effectué avec succès`, 'success');
                document.getElementById('paiementForm').reset();
                hideLoading();
                
                enregistrerTransaction('Paiement', formData.compte, -formData.montant, `${formData.type} - ${formData.numero} - ${formData.beneficiaire}`);
            }, 1200);
        }

        // Gestion de la caisse
        function ouvrirCaisse() {
            const fonds = parseFloat(document.getElementById('fondsCaisse').value);
            
            if (!validerMontant(fonds)) {
                showStatus('Montant des fonds invalide', 'error');
                return;
            }

            soldeInitialCaisse = fonds;
            soldeCourantCaisse = fonds;
            caisseOuverte = true;
            
            document.getElementById('caisseActuelle').textContent = fonds.toFixed(2);
            document.getElementById('caisseStatus').textContent = 'Caisse: Ouverte';
            document.getElementById('caisseStatus').style.background = 'var(--vert-principal)';
            
            showStatus('Caisse ouverte avec succès', 'success');
        }

        function calculerEcart() {
            if (!caisseOuverte) {
                showStatus('Veuillez d\'abord ouvrir la caisse', 'error');
                return;
            }

            const montantCompte = parseFloat(document.getElementById('montantCompte').value);
            
            if (isNaN(montantCompte)) {
                showStatus('Veuillez saisir un montant valide', 'error');
                return;
            }

            const ecart = montantCompte - soldeCourantCaisse;
            
            document.getElementById('ecartMontant').textContent = ecart.toFixed(2);
            document.getElementById('ecartMontant').style.color = ecart >= 0 ? 'var(--vert-principal)' : 'var(--rouge-principal)';
            document.getElementById('ecartCaisse').style.display = 'block';
            
            const statusMessage = ecart === 0 ? 'Caisse équilibrée' : 
                                 ecart > 0 ? `Excédent de ${ecart.toFixed(2)}€` : 
                                 `Manquant de ${Math.abs(ecart).toFixed(2)}€`;
            
            showStatus(statusMessage, ecart === 0 ? 'success' : 'error');
        }

        function fermerCaisse() {
            if (!caisseOuverte) {
                showStatus('Aucune caisse ouverte', 'error');
                return;
            }

            showLoading();
            
            setTimeout(() => {
                caisseOuverte = false;
                document.getElementById('caisseStatus').textContent = 'Caisse: Fermée';
                document.getElementById('caisseStatus').style.background = 'var(--rouge-principal)';
                
                showStatus('Caisse fermée avec succès', 'success');
                hideLoading();
                
                // Réinitialisation des valeurs
                document.getElementById('fondsCaisse').value = '5000.00';
                document.getElementById('montantCompte').value = '';
                document.getElementById('ecartCaisse').style.display = 'none';
                document.getElementById('caisseActuelle').textContent = '0.00';
                soldeCourantCaisse = 0;
                soldeInitialCaisse = 0;
                
                // Génération du rapport de caisse
                genererRapportCaisse();
            }, 1200);
        }

        // Services bancaires
        function demanderCarte() {
            showLoading();
            const formData = {
                compte: document.getElementById('carteCompte').value,
                type: document.getElementById('carteType').value
            };

            if (!validerCompte(formData.compte)) {
                showStatus('Format de compte invalide', 'error');
                hideLoading();
                return;
            }

            setTimeout(() => {
                showStatus(`Demande de carte ${formData.type} enregistrée`, 'success');
                document.getElementById('carteForm').reset();
                hideLoading();
                
                // Enregistrement de la demande
                enregistrerDemandeService('carte', formData);
            }, 1000);
        }

        function demanderChequier() {
            showLoading();
            const formData = {
                compte: document.getElementById('chequierCompte').value,
                nombre: document.getElementById('chequierNombre').value
            };

            if (!validerCompte(formData.compte)) {
                showStatus('Format de compte invalide', 'error');
                hideLoading();
                return;
            }

            setTimeout(() => {
                showStatus(`Demande de chéquier (${formData.nombre} chèques) enregistrée`, 'success');
                document.getElementById('chequierForm').reset();
                hideLoading();
                
                // Enregistrement de la demande
                enregistrerDemandeService('chequier', formData);
            }, 1000);
        }

        function ouvrirCompteEpargne() {
            showLoading();
            const formData = {
                client: document.getElementById('epargneClient').value,
                depot: parseFloat(document.getElementById('epargneDepot').value),
                type: document.getElementById('epargneType').value
            };

            if (!validerMontant(formData.depot)) {
                showStatus('Montant de dépôt invalide', 'error');
                hideLoading();
                return;
            }

            if (formData.depot < 100) {
                showStatus('Le dépôt initial minimum est de 100€', 'error');
                hideLoading();
                return;
            }

            setTimeout(() => {
                showStatus(`Compte épargne ${formData.type} ouvert avec succès`, 'success');
                document.getElementById('epargneForm').reset();
                hideLoading();
                
                // Création du nouveau compte
                creerCompte(formData);
                
                // Mise à jour de la caisse si dépôt en espèces
                if (caisseOuverte) {
                    soldeCourantCaisse += formData.depot;
                    document.getElementById('caisseActuelle').textContent = soldeCourantCaisse.toFixed(2);
                }
            }, 1200);
        }

        // Historique et rapports
        function chargerHistorique() {
            showLoading();
            const filtres = {
                dateDebut: document.getElementById('dateDebut').value,
                dateFin: document.getElementById('dateFin').value,
                type: document.getElementById('typeTransaction').value,
                compte: document.getElementById('compteFiltre').value
            };

            setTimeout(() => {
                // Simulation de chargement d'historique avec données plus réalistes
                let mockHistorique = [
                    { 
                        type: 'Dépôt', 
                        compte: 'FR7630001007941234567890189', 
                        montant: 500.00, 
                        date: '2024-03-15 14:30', 
                        commentaires: 'Dépôt espèces',
                        agent: 'Jean Dupont'
                    },
                    { 
                        type: 'Retrait', 
                        compte: 'FR7630001007941234567890190', 
                        montant: -200.00, 
                        date: '2024-03-15 15:45', 
                        commentaires: 'Retrait DAB',
                        agent: 'Jean Dupont'
                    },
                    { 
                        type: 'Virement', 
                        compte: 'FR7630001007941234567890191', 
                        montant: -150.00, 
                        date: '2024-03-15 16:00', 
                        commentaires: 'Vers FR7630001007941234567890192: Loyer',
                        agent: 'Jean Dupont'
                    },
                    { 
                        type: 'Paiement', 
                        compte: 'FR7630001007941234567890189', 
                        montant: -75.50, 
                        date: '2024-03-15 16:15', 
                        commentaires: 'electricite - FAC-2024-001 - EDF',
                        agent: 'Jean Dupont'
                    },
                    { 
                        type: 'Dépôt', 
                        compte: 'FR7630001007941234567890192', 
                        montant: 1200.00, 
                        date: '2024-03-15 16:30', 
                        commentaires: 'Virement salaire',
                        agent: 'Marie Durand'
                    }
                ];

                // Filtrage selon les critères
                if (filtres.type) {
                    mockHistorique = mockHistorique.filter(t => t.type === filtres.type);
                }
                if (filtres.compte) {
                    mockHistorique = mockHistorique.filter(t => t.compte.includes(filtres.compte));
                }

                displayHistorique(mockHistorique);
                hideLoading();
                showStatus(`${mockHistorique.length} transaction(s) trouvée(s)`, 'info');
            }, 1200);
        }

        function displayHistorique(transactions) {
            const container = document.getElementById('historiqueList');
            container.innerHTML = '';

            if (transactions.length === 0) {
                container.innerHTML = '<div class="transaction-item"><div>❌ Aucune transaction trouvée</div></div>';
                return;
            }

            transactions.forEach(transaction => {
                const item = document.createElement('div');
                item.className = 'transaction-item';
                
                const isPositive = transaction.montant >= 0;
                const amountClass = isPositive ? 'amount-positive' : 'amount-negative';
                const sign = isPositive ? '+' : '';
                const icon = getTransactionIcon(transaction.type);

                item.innerHTML = `
                    <div>
                        <strong>${icon} ${transaction.type}</strong> - Compte: ${transaction.compte}<br>
                        <small>${transaction.date} - ${transaction.commentaires}</small><br>
                        <small>👤 Agent: ${transaction.agent}</small>
                    </div>
                    <div class="${amountClass}">${sign}${Math.abs(transaction.montant).toFixed(2)} €</div>
                `;

                container.appendChild(item);
            });
        }

        function getTransactionIcon(type) {
            const icons = {
                'Dépôt': '💰',
                'Retrait': '💳',
                'Virement': '🔄',
                'Paiement': '💸'
            };
            return icons[type] || '📝';
        }

        function exporterHistorique() {
            showLoading();
            showStatus('Génération du rapport PDF...', 'info');
            
            // Simulation d'export PDF
            setTimeout(() => {
                showStatus('Historique exporté avec succès', 'success');
                hideLoading();
                
                // Simulation du téléchargement
                const now = new Date();
                const filename = `historique_${now.getFullYear()}-${(now.getMonth()+1).toString().padStart(2,'0')}-${now.getDate().toString().padStart(2,'0')}.pdf`;
                console.log(`Téléchargement simulé: ${filename}`);
            }, 2500);
        }

        // Fonctions utilitaires
        function enregistrerTransaction(type, compte, montant, commentaires) {
            const transaction = {
                type,
                compte,
                montant,
                commentaires,
                date: new Date().toISOString(),
                agent: 'Jean Dupont'
            };
            
            transactionsJour.push(transaction);
            console.log('Transaction enregistrée:', transaction);
            
            // Ici, vous ajouteriez l'appel AJAX vers votre backend PHP
            
            fetch('api/transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(transaction)
            })
            .then(response => response.json())
            .then(data => console.log('Transaction sauvée:', data));
            
        }

        function enregistrerEcheance(chequeData) {
            const echeance = {
                ...chequeData,
                date_creation: new Date().toISOString(),
                agent: 'Jean Dupont'
            };
            
            console.log('Échéance enregistrée:', echeance);
            
            // Appel AJAX vers echeancecredit table
        }

        function enregistrerDemandeService(service, data) {
            const demande = {
                service,
                ...data,
                date_demande: new Date().toISOString(),
                statut: 'En attente',
                agent: 'Jean Dupont'
            };
            
            console.log('Demande de service enregistrée:', demande);
        }

        function creerCompte(compteData) {
            const nouveauCompte = {
                ...compteData,
                numero: genererNumeroCompte(),
                date_creation: new Date().toISOString(),
                statut: 'Actif',
                agent: 'Jean Dupont'
            };
            
            console.log('Nouveau compte créé:', nouveauCompte);
        }

        function genererNumeroCompte() {
            // Génération d'un numéro de compte IBAN français
            const random = Math.floor(Math.random() * 10000000000000000000).toString().padStart(20, '0');
            return `FR76${random}89`;
        }

        function genererRapportCaisse() {
            const rapport = {
                date: new Date().toISOString(),
                agent: 'Jean Dupont',
                solde_initial: soldeInitialCaisse,
                solde_final: soldeCourantCaisse,
                transactions: transactionsJour.length,
                total_depot: transactionsJour.filter(t => t.montant > 0).reduce((sum, t) => sum + t.montant, 0),
                total_retrait: Math.abs(transactionsJour.filter(t => t.montant < 0).reduce((sum, t) => sum + t.montant, 0))
            };
            
            console.log('Rapport de caisse généré:', rapport);
        }

        function validerCaisseOuverte() {
            if (!caisseOuverte) {
                showStatus('Veuillez d\'abord ouvrir la caisse', 'error');
                return false;
            }
            return true;
        }

        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        function showStatus(message, type = 'success') {
            const statusBar = document.getElementById('statusBar');
            const statusMessage = document.getElementById('statusMessage');
            
            statusMessage.textContent = message;
            statusBar.className = `status-bar ${type}`;
            statusBar.style.display = 'block';

            // Animation d'apparition
            statusBar.style.transform = 'translateX(100%)';
            setTimeout(() => {
                statusBar.style.transform = 'translateX(0)';
            }, 10);

            // Masquer après 4 secondes
            setTimeout(() => {
                statusBar.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    statusBar.style.display = 'none';
                }, 300);
            }, 4000);
        }

        // Validation des formulaires
        function validerMontant(montant) {
            return montant > 0 && !isNaN(montant) && isFinite(montant);
        }

        function validerCompte(numeroCompte) {
            // Validation IBAN français plus stricte
            const iban = numeroCompte.replace(/\s/g, '');
            const regex = /^FR[0-9]{2}[0-9]{10}[0-9A-Z]{11}[0-9]{2}$/;
            return regex.test(iban);
        }

        // Auto-formatage des champs
        function formatMontant(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('blur', function() {
                    if (this.value && !isNaN(this.value)) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            }
        }

        // Initialisation des formatages au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-formatage des montants
            const montantFields = ['depotMontant', 'retraitMontant', 'virementMontant', 'chequeMontant', 'paiementMontant', 'fondsCaisse', 'montantCompte', 'epargneDepot'];
            montantFields.forEach(id => formatMontant(id));

            // Validation en temps réel des codes PIN
            const pinField = document.getElementById('retraitPin');
            if (pinField) {
                pinField.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }

            // Limitation de la date d'émission des chèques (pas dans le futur)
            const chequeDateField = document.getElementById('chequeDate');
            if (chequeDateField) {
                const today = new Date().toISOString().split('T')[0];
                chequeDateField.max = today;
            }

            // Auto-complétion des bénéficiaires de paiements
            const paiementTypeField = document.getElementById('paiementType');
            const paiementBeneficiaireField = document.getElementById('paiementBeneficiaire');
            if (paiementTypeField && paiementBeneficiaireField) {
                paiementTypeField.addEventListener('change', function() {
                    const beneficiaires = {
                        'electricite': 'EDF',
                        'eau': 'Veolia',
                        'telephone': 'Orange',
                        'internet': 'Free',
                        'assurance': 'AXA'
                    };
                    if (beneficiaires[this.value]) {
                        paiementBeneficiaireField.value = beneficiaires[this.value];
                    }
                });
            }
        });

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Ctrl+S pour sauvegarder/valider le formulaire actuel
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeSection = document.querySelector('.section.active');
                const form = activeSection.querySelector('form');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
            
            // Echap pour fermer les résultats de recherche
            if (e.key === 'Escape') {
                document.getElementById('searchResults').style.display = 'none';
            }
        });

        // Masquer les résultats de recherche en cliquant ailleurs
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.client-search')) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });

        // Sauvegarde automatique des brouillons (simulation)
        function sauvegarderBrouillon() {
            const drafts = {};
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                const formData = new FormData(form);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    if (value.trim() !== '') {
                        data[key] = value;
                    }
                }
                if (Object.keys(data).length > 0) {
                    drafts[form.id] = data;
                }
            });
            
            if (Object.keys(drafts).length > 0) {
                console.log('Brouillons sauvegardés:', drafts);
                // localStorage.setItem('drafts', JSON.stringify(drafts));
            }
        }

        // Sauvegarde automatique toutes les 30 secondes
        setInterval(sauvegarderBrouillon, 30000);

        // Confirmation avant fermeture si des données sont saisies
        window.addEventListener('beforeunload', function(e) {
            const hasUnsavedData = Array.from(document.querySelectorAll('input, select, textarea'))
                .some(field => field.value.trim() !== '' && field.id !== 'searchClient');
            
            if (hasUnsavedData) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>