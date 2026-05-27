<?php
session_start();
require_once "../config/database.php";

// Protection de la page - Vérification du rôle Admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}

$message = "";
$status = "success";

// Traitement de la suppression d'un utilisateur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $id_to_delete = intval($_POST['user_id']);
    
    if ($id_to_delete === intval($_SESSION["user_id"])) {
        $message = "Vous ne pouvez pas supprimer votre propre compte administrateur.";
        $status = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Si c'est un étudiant, on nettoie ses inscriptions et ses notes
            $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$id_to_delete]);
            $pdo->prepare("DELETE FROM grades WHERE student_id = ?")->execute([$id_to_delete]);
            
            // 2. Si c'est un enseignant, on gère ses cours et les dépendances (planning, notes, inscriptions)
            $stmtCourses = $pdo->prepare("SELECT id FROM courses WHERE teacher_id = ?");
            $stmtCourses->execute([$id_to_delete]);
            while ($course = $stmtCourses->fetch()) {
                $course_id = $course['id'];
                $pdo->prepare("DELETE FROM schedules WHERE course_id = ?")->execute([$course_id]);
                $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$course_id]);
                $pdo->prepare("DELETE FROM grades WHERE course_id = ?")->execute([$course_id]);
            }
            $pdo->prepare("DELETE FROM courses WHERE teacher_id = ?")->execute([$id_to_delete]);
            
            // 3. Enfin, suppression définitive de l'utilisateur
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id_to_delete]);
            
            $pdo->commit();
            $message = "Utilisateur et toutes ses données associées supprimés avec succès.";
            $status = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Erreur lors de la suppression : " . $e->getMessage();
            $status = "danger";
        }
    }
}

// Récupération des statistiques simples
$nbStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$nbTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$nbCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Récupération de la liste des utilisateurs
$stmtUsers = $pdo->query("SELECT id, nom, prenom, email, role FROM users ORDER BY role DESC, nom ASC");
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
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="#">SmartCampus Admin</a>
        <div class="navbar-text text-white ms-auto d-flex align-items-center">
            <span class="me-3">Bonjour, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm fw-bold">Déconnexion</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <h1 class="mb-4 fw-bold">Tableau de bord Administrateur</h1>

    <?php if ($message != ""): ?>
        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-primary text-white p-3">
                <div class="card-body">
                    <h5 class="card-title text-uppercase opacity-75">Nombre d'Étudiants</h5>
                    <p class="card-text display-5 fw-bold"><?php echo $nbStudents; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-success text-white p-3">
                <div class="card-body">
                    <h5 class="card-title text-uppercase opacity-75">Nombre d'Enseignants</h5>
                    <p class="card-text display-5 fw-bold"><?php echo $nbTeachers; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-warning text-dark p-3">
                <div class="card-body">
                    <h5 class="card-title text-uppercase opacity-75">Total des Cours</h5>
                    <p class="card-text display-5 fw-bold"><?php echo $nbCourses; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-3">
            <h3 class="card-title mb-0 fw-bold">Gestion des utilisateurs</h3>
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
                            <td class="ps-4"><?php echo $user['id']; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($user['nom']); ?></td>
                            <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger text-uppercase px-3 py-2">Admin</span>
                                <?php elseif($user['role'] === 'teacher'): ?>
                                    <span class="badge bg-success text-uppercase px-3 py-2">Enseignant</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-uppercase px-3 py-2">Étudiant</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($user['id'] != $_SESSION["user_id"]): ?>
                                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ainsi que toutes ses données liées ?');" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm px-3">Supprimer</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Session active</span>
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