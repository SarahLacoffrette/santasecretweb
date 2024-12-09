<?php
// Initialisation des participants
$participants = ["Noé", "Théo", "Antoine", "Valerien", "Sarah", "Camille"];
$results_file = "results.txt";
$password = "callipyge"; // Mot de passe pour réinitialiser

// Fonction pour initialiser les listes dans results.txt
function initialize_lists($participants, $results_file) {
    $content = implode(",", $participants) . "\n" . implode(",", $participants) . "\n";
    file_put_contents($results_file, $content);
}

// Fonction pour charger les listes
function load_lists($results_file) {
    if (!file_exists($results_file)) {
        global $participants;
        initialize_lists($participants, $results_file);
    }
    $lines = file($results_file, FILE_IGNORE_NEW_LINES);
    $bêtise_list = explode(",", $lines[0]);
    $sérieux_list = explode(",", $lines[1]);
    return [$bêtise_list, $sérieux_list];
}

// Fonction pour charger les résultats des tirages
function load_results($results_file) {
    if (!file_exists($results_file)) {
        return [];
    }
    $lines = file($results_file, FILE_IGNORE_NEW_LINES);
    $results = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, "RESULT:")) {
            $parts = explode(",", str_replace("RESULT:", "", $line));
            $results[$parts[0]] = ["bêtise" => $parts[1], "sérieux" => $parts[2]];
        }
    }
    return $results;
}

// Fonction pour sauvegarder les listes mises à jour
function save_lists($bêtise_list, $sérieux_list, $results_file) {
    $lines = file($results_file, FILE_IGNORE_NEW_LINES);
    $results_lines = array_filter($lines, fn($line) => str_starts_with($line, "RESULT:"));
    $content = implode(",", $bêtise_list) . "\n" . implode(",", $sérieux_list) . "\n" . implode("\n", $results_lines);
    file_put_contents($results_file, $content);
}

// Fonction pour sauvegarder un résultat
function save_result($participant, $bêtise, $sérieux, $results_file) {
    $line = "\nRESULT:$participant,$bêtise,$sérieux\n";
    file_put_contents($results_file, $line, FILE_APPEND);
}

// Gestion des actions
$action = $_GET['action'] ?? null;
$participant = $_GET['participant'] ?? null;
$message = "";
$result_to_display = null;

if ($action === "reset" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted_password = $_POST['password'] ?? '';
    if ($submitted_password === $password) {
        initialize_lists($participants, $results_file);
        $message = "Les résultats ont été réinitialisés.";
    } else {
        $message = "Mot de passe incorrect.";
    }
}

if ($action === "results" && $participant) {
    [$bêtise_list, $sérieux_list] = load_lists($results_file);
    $results = load_results($results_file);

    if (isset($results[$participant])) {
        $result_to_display = $results[$participant];
    } else {
        // Cas spécial pour Valerien
        if ($participant === "Valerien") {
            $bêtise = "Antoine";
            $sérieux = "Antoine";

            // Retirer Antoine des listes
            $bêtise_list = array_diff($bêtise_list, [$bêtise]);
            $sérieux_list = array_diff($sérieux_list, [$sérieux]);
        } else {
            // Exclure le participant lui-même
            $bêtise_choices = array_diff($bêtise_list, [$participant, "Antoine"]);
            $sérieux_choices = array_diff($sérieux_list, [$participant, "Antoine"]);

            // Gestion spéciale pour le dernier tirage
            if (count($bêtise_choices) === 1 && reset($bêtise_choices) === $participant) {
                $bêtise_choices = array_diff($bêtise_list, [$participant]);
            }

            if (count($sérieux_choices) === 1 && reset($sérieux_choices) === $participant) {
                $sérieux_choices = array_diff($sérieux_list, [$participant]);
            }

            // Effectuer les tirages
            if (empty($bêtise_choices) || empty($sérieux_choices)) {
                $message = "Pas assez de participants disponibles pour effectuer le tirage.";
            } else {
                $bêtise = $bêtise_choices[array_rand($bêtise_choices)];
                $sérieux = $sérieux_choices[array_rand($sérieux_choices)];

                // Mettre à jour les listes
                $bêtise_list = array_diff($bêtise_list, [$bêtise]);
                $sérieux_list = array_diff($sérieux_list, [$sérieux]);
            }
        }

        // Sauvegarder les nouvelles listes et le tirage
        save_lists($bêtise_list, $sérieux_list, $results_file);
        save_result($participant, $bêtise, $sérieux, $results_file);

        $result_to_display = ["bêtise" => $bêtise, "sérieux" => $sérieux];
    }
}

// Charger les données pour l'affichage
[$bêtise_list, $sérieux_list] = load_lists($results_file);
$results = load_results($results_file);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Secret Santa</title>
</head>
<body>
    <h1>Secret Santa - Tirages</h1>
    <?php if ($message): ?>
        <p><strong><?= htmlspecialchars($message) ?></strong></p>
    <?php endif; ?>

    <?php if ($result_to_display): ?>
        <div class="results">
            <h2>Résultat pour <?= htmlspecialchars($participant) ?></h2>
            <p><strong>Cadeau bêtise :</strong> <?= htmlspecialchars($result_to_display["bêtise"]) ?></p>
            <p><strong>Cadeau sérieux :</strong> <?= htmlspecialchars($result_to_display["sérieux"]) ?></p>
            <a href="index.php">Retour à l'accueil</a>
        </div>
    <?php else: ?>
        <ul>
            <?php foreach ($participants as $p): ?>
                <li>
                    <?php if (isset($results[$p])): ?>
                        <?= htmlspecialchars($p) ?> - <em>Déjà tiré</em>
                    <?php else: ?>
                        <a href="?action=results&participant=<?= urlencode($p) ?>">
                            <button><?= htmlspecialchars($p) ?></button>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <hr>
        <form action="?action=reset" method="POST">
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Réinitialiser</button>
        </form>
    <?php endif; ?>
</body>
</html>
