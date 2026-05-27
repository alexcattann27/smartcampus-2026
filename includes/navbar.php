<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-lg mb-5">

    <div class="container">

        <a class="navbar-brand fw-bold text-primary"
           href="../index.php">

            SmartCampus

        </a>

        <div class="navbar-text text-white ms-auto d-flex align-items-center">

            <span class="me-3">

                Bonjour,
                <strong>

                    <?php echo htmlspecialchars($_SESSION["user_name"] ?? "Utilisateur"); ?>

                </strong>

            </span>

            <a href="../logout.php"
               class="btn btn-outline-light btn-sm fw-bold">

                Déconnexion

            </a>

        </div>

    </div>

</nav>