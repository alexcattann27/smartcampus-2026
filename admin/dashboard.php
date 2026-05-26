<?php

session_start();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <title>Dashboard Admin</title>

</head>

<body>

    <h1>Dashboard Admin</h1>

    <p>

        Bienvenue <?php echo $_SESSION["user_name"]; ?>

    </p>

</body>

</html>