<?php

session_start();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <title>Dashboard Teacher</title>

</head>

<body>

    <h1>Dashboard Teacher</h1>

    <p>

        Bienvenue <?php echo $_SESSION["user_name"]; ?>

    </p>

</body>

</html>