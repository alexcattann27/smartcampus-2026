<?php

session_start();

require_once "config/database.php";

$message = "";
$alertType = "danger";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["prenom"];
        $_SESSION["user_role"] = $user["role"];

        $message = "Connexion réussie";
        $alertType = "success";

    } else {

        $message = "Email ou mot de passe incorrect";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-dark d-flex justify-content-center align-items-center vh-100">

    <div class="card shadow p-4" style="width: 400px; border-radius: 20px;">

        <h1 class="text-center mb-2 fw-bold text-primary">

            SmartCampus

        </h1>

        <p class="text-center text-muted mb-4">

            Connectez-vous à votre compte

        </p>

        <?php if($message != ""): ?>

            <div class="alert alert-<?php echo $alertType; ?>">

                <?php echo $message; ?>

            </div>

        <?php endif; ?>

        <form method="POST">

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

            <button type="submit" class="btn btn-primary w-100 fw-bold">

                Se connecter

            </button>

        </form>

    </div>

</body>

</html>