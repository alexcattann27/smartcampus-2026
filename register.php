<?php

require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nom = $_POST["nom"];
    $prenom = $_POST["prenom"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role = $_POST["role"];

    $sql = "INSERT INTO users (nom, prenom, email, password, role)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$nom, $prenom, $email, $password, $role])) {

        $message = "Utilisateur créé avec succès";

    } else {

        $message = "Erreur lors de l'inscription";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Inscription</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-dark d-flex justify-content-center align-items-center vh-100">

    <div class="card shadow p-4" style="width: 400px; border-radius: 20px;">

        <h1 class="text-center mb-4">SmartCampus</h1>

        <?php if($message != ""): ?>

            <div class="alert alert-success">

                <?php echo $message; ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">

                <input type="text"
                       name="nom"
                       class="form-control"
                       placeholder="Nom"
                       required>

            </div>

            <div class="mb-3">

                <input type="text"
                       name="prenom"
                       class="form-control"
                       placeholder="Prénom"
                       required>

            </div>

            <div class="mb-3">

                <input type="email"
                       name="email"
                       class="form-control"
                       placeholder="Email"
                       required>

            </div>

            <div class="mb-3">

                <input type="password"
                       name="password"
                       class="form-control"
                       placeholder="Mot de passe"
                       required>

            </div>

            <div class="mb-3">

                <select name="role" class="form-select">

                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>

                </select>

            </div>

            <button type="submit" class="btn btn-primary w-100">

                S'inscrire

            </button>

        </form>

    </div>

</body>

</html>