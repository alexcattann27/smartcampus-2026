<?php
session_start();

require_once "../config/database.php";
require_once "../includes/navbar.php";

// Protection de la page - Vérification du rôle Admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {

    header("Location: ../login.php");
    exit();
}

$message = "";
$status = "success";

// SUPPRESSION UTILISATEUR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {

    $id_to_delete = intval($_POST['user_id']);

    if ($id_to_delete === intval($_SESSION["user_id"])) {

        $message = "Vous ne pouvez pas supprimer votre propre compte administrateur.";
        $status = "danger";

    } else {

        try {

            $pdo->beginTransaction();

            // Suppression données étudiant
            $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$id_to_delete]);
            $pdo->prepare("DELETE FROM grades WHERE student_id = ?")->execute([$id_to_delete]);

            // Suppression données professeur
            $stmtCourses = $pdo->prepare("SELECT id FROM courses WHERE teacher_id = ?");
            $stmtCourses->execute([$id_to_delete]);

            while ($course = $stmtCourses->fetch()) {

                $course_id = $course['id'];

                $pdo->prepare("DELETE FROM schedules WHERE course_id = ?")->execute([$course_id]);
                $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$course_id]);
                $pdo->prepare("DELETE FROM grades WHERE course_id = ?")->execute([$course_id]);
            }

            $pdo->prepare("DELETE FROM courses WHERE teacher_id = ?")->execute([$id_to_delete]);

            // Suppression utilisateur
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id_to_delete]);

            $pdo->commit();

            $message = "Utilisateur supprimé avec succès.";

        } catch (PDOException $e) {

            $pdo->rollBack();

            $message = "Erreur : " . $e->getMessage();
            $status = "danger";
        }
    }
}

// STATISTIQUES
$nbStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

$nbTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();

$nbCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// UTILISATEURS
$stmtUsers = $pdo->query("
    SELECT id, nom, prenom, email, role
    FROM users
    ORDER BY role DESC, nom ASC
");

$users = $stmtUsers->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SmartCampus - Administration</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body style="background: #f4f6f9;">

<div class="container mb-5">

    <h1 class="mb-4 fw-bold">

        Tableau de bord Administrateur

    </h1>

    <?php if ($message != ""): ?>

        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show">

            <?php echo htmlspecialchars($message); ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">

            </button>

        </div>

    <?php endif; ?>

    <div class="row mb-4">

        <div class="col-md-4 mb-3">

            <div class="card shadow border-0 rounded-4 bg-primary text-white">

                <div class="card-body">

                    <h5 class="text-uppercase opacity-75">

                        Étudiants

                    </h5>

                    <p class="display-5 fw-bold">

                        <?php echo $nbStudents; ?>

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-4 mb-3">

            <div class="card shadow border-0 rounded-4 bg-success text-white">

                <div class="card-body">

                    <h5 class="text-uppercase opacity-75">

                        Enseignants

                    </h5>

                    <p class="display-5 fw-bold">

                        <?php echo $nbTeachers; ?>

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-4 mb-3">

            <div class="card shadow border-0 rounded-4 bg-warning">

                <div class="card-body">

                    <h5 class="text-uppercase opacity-75">

                        Cours

                    </h5>

                    <p class="display-5 fw-bold">

                        <?php echo $nbCourses; ?>

                    </p>

                </div>

            </div>

        </div>

    </div>

    <div class="card shadow border-0 rounded-4">

        <div class="card-header bg-white border-0 py-3">

            <h3 class="fw-bold mb-0">

                Gestion des utilisateurs

            </h3>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover table-striped align-middle mb-0">

                    <thead class="table-dark">

                        <tr>

                            <th class="ps-4">ID</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th class="text-center">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($users as $user): ?>

                        <tr>

                            <td class="ps-4">

                                <?php echo $user['id']; ?>

                            </td>

                            <td class="fw-bold">

                                <?php echo htmlspecialchars($user['nom']); ?>

                            </td>

                            <td>

                                <?php echo htmlspecialchars($user['prenom']); ?>

                            </td>

                            <td>

                                <?php echo htmlspecialchars($user['email']); ?>

                            </td>

                            <td>

                                <?php if($user['role'] === 'admin'): ?>

                                    <span class="badge bg-danger px-3 py-2">

                                        Admin

                                    </span>

                                <?php elseif($user['role'] === 'teacher'): ?>

                                    <span class="badge bg-success px-3 py-2">

                                        Enseignant

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-info px-3 py-2">

                                        Étudiant

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="text-center">

                                <?php if($user['id'] != $_SESSION["user_id"]): ?>

                                    <form method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Confirmer la suppression ?');">

                                        <input type="hidden"
                                               name="user_id"
                                               value="<?php echo $user['id']; ?>">

                                        <button type="submit"
                                                name="delete_user"
                                                class="btn btn-outline-danger btn-sm px-3 fw-bold">

                                            Supprimer

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="text-muted small">

                                        Session active

                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>