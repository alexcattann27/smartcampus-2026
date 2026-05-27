<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "student") {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION["user_id"];
$message = "";
$status = "success";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll_course'])) {
    $course_id = intval($_POST['course_id']);
    
    $stmtCheck = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmtCheck->execute([$student_id, $course_id]);
    
    if ($stmtCheck->rowCount() > 0) {
        $message = "Vous êtes déjà inscrit à ce cours !";
        $status = "warning";
    } else {
        $stmtConflict = $pdo->prepare("
            SELECT s1.jour, s1.heure_debut, s1.heure_fin, c1.nom AS new_course
            FROM schedules s1
            JOIN schedules s2 ON s1.jour = s2.jour 
                AND s1.heure_debut < s2.heure_fin 
                AND s1.heure_fin > s2.heure_debut
            JOIN enrollments e ON s2.course_id = e.course_id
            JOIN courses c1 ON s1.course_id = c1.id
            WHERE s1.course_id = ? AND e.student_id = ?
        ");
        $stmtConflict->execute([$course_id, $student_id]);
        $conflict = $stmtConflict->fetch();

        if ($conflict) {
            $message = "Conflit d'emploi du temps détecté le " . $conflict['jour'] . " pour le cours " . $conflict['new_course'] . " !";
            $status = "danger";
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
            if ($stmtInsert->execute([$student_id, $course_id])) {
                $message = "Inscription réussie !";
                $status = "success";
            } else {
                $message = "Erreur lors de l'inscription.";
                $status = "danger";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['unenroll_course'])) {
    $course_id = intval($_POST['course_id']);
    
    $stmtDelete = $pdo->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
    if ($stmtDelete->execute([$student_id, $course_id])) {
        $message = "Vous avez été désinscrit du cours.";
        $status = "success";
    } else {
        $message = "Erreur lors de la désinscription.";
        $status = "danger";
    }
}

$stmtCourses = $pdo->prepare("
    SELECT c.id AS course_id, c.nom AS course_name, c.description, u.nom AS teacher_name, u.prenom AS teacher_prenom
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    JOIN users u ON c.teacher_id = u.id
    WHERE e.student_id = ?
");
$stmtCourses->execute([$student_id]);
$my_courses = $stmtCourses->fetchAll();

$stmtAvailable = $pdo->prepare("
    SELECT c.id, c.nom AS course_name, u.nom AS teacher_name, u.prenom AS teacher_prenom
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
");
$stmtAvailable->execute([$student_id]);
$available_courses = $stmtAvailable->fetchAll();

$stmtGrades = $pdo->prepare("
    SELECT c.nom AS course_name, g.grade, g.is_locked
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    WHERE g.student_id = ?
");
$stmtGrades->execute([$student_id]);
$my_grades = $stmtGrades->fetchAll();

$stmtAvg = $pdo->prepare("SELECT AVG(grade) as moyenne_generale FROM grades WHERE student_id = ?");
$stmtAvg->execute([$student_id]);
$avgResult = $stmtAvg->fetch();
$moyenne_generale = $avgResult['moyenne_generale'] ? number_format($avgResult['moyenne_generale'], 2) : null;

$stmtSchedule = $pdo->prepare("
    SELECT c.nom AS course_name, s.jour, s.heure_debut, s.heure_fin, s.salle
    FROM schedules s
    JOIN courses c ON s.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY FIELD(s.jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), s.heure_debut
");
$stmtSchedule->execute([$student_id]);
$my_schedule = $stmtSchedule->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCampus - Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-info mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">SmartCampus Étudiant</a>
        <div class="navbar-text text-white ms-auto d-flex align-items-center">
            <span class="me-3">Bienvenue, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm fw-bold">Déconnexion</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <h1 class="mb-4 fw-bold text-dark">Mon Tableau de Bord</h1>

    <?php if ($message != ""): ?>
        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-5 mb-4">
            
            <div class="card shadow-sm border-0 mb-4 border-start border-info border-4">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">S'inscrire à un nouveau cours</h5>
                    <?php if (count($available_courses) > 0): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <select name="course_id" class="form-select" required>
                                    <option value="">-- Sélectionner un cours --</option>
                                    <?php foreach ($available_courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name'] . ' (Prof: ' . $course['teacher_prenom'] . ' ' . $course['teacher_name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="enroll_course" class="btn btn-info text-white w-100 fw-bold">Valider l'inscription</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Aucun nouveau cours disponible pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-3 fw-bold">📚 Mes Cours</div>
                <div class="card-body p-0">
                    <?php if (count($my_courses) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($my_courses as $course): ?>
                                <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                        <p class="small text-muted mb-1"><?php echo htmlspecialchars($course['description']); ?></p>
                                        <small class="text-primary fw-bold">Prof: <?php echo htmlspecialchars($course['teacher_prenom'] . ' ' . $course['teacher_name']); ?></small>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir vous désinscrire de ce cours ?');">
                                        <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                        <button type="submit" name="unenroll_course" class="btn btn-sm btn-outline-danger fw-bold">X</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <p class="mb-0">Vous n'êtes inscrit à aucun cours.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white py-3 fw-bold">🎯 Mes Notes</div>
                <div class="card-body p-0">
                    <?php if (count($my_grades) > 0): ?>
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Matière</th>
                                    <th>Note</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_grades as $grade): ?>
                                    <tr>
                                        <td class="ps-3"><?php echo htmlspecialchars($grade['course_name']); ?></td>
                                        <td class="fw-bold <?php echo ($grade['grade'] >= 10) ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo htmlspecialchars($grade['grade']); ?> / 20
                                        </td>
                                        <td>
                                            <?php if ($grade['is_locked']): ?>
                                                <span class="badge bg-danger">Définitif</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Provisoire</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if ($moyenne_generale !== null): ?>
                            <tfoot>
                                <tr class="table-light fw-bold fs-5">
                                    <td class="ps-3 text-end" colspan="2">Moyenne Générale :</td>
                                    <td class="<?php echo ($moyenne_generale >= 10) ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $moyenne_generale; ?> / 20
                                    </td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <p class="mb-0">Aucune note disponible.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-warning text-dark py-3 fw-bold">📅 Mon Emploi du Temps</div>
                <div class="card-body p-0">
                    <?php if (count($my_schedule) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Jour</th>
                                        <th>Horaires</th>
                                        <th>Cours</th>
                                        <th>Salle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_schedule as $sched): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold"><?php echo htmlspecialchars($sched['jour']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo substr($sched['heure_debut'], 0, 5) . ' - ' . substr($sched['heure_fin'], 0, 5); ?></span></td>
                                            <td><?php echo htmlspecialchars($sched['course_name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($sched['salle']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <p class="mb-0">Votre emploi du temps est vide.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>