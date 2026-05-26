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
    <title>Inscription</title>

</head>

<body>

    <h1>Inscription</h1>

    <p><?php echo $message; ?></p>

    <form method="POST">

        <input type="text" name="nom" placeholder="Nom" required>
        <br><br>

        <input type="text" name="prenom" placeholder="Prénom" required>
        <br><br>

        <input type="email" name="email" placeholder="Email" required>
        <br><br>

        <input type="password" name="password" placeholder="Mot de passe" required>
        <br><br>

        <select name="role">

            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>

        </select>

        <br><br>

        <button type="submit">S'inscrire</button>

    </form>

</body>

</html>