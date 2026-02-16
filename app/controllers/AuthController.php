<?php
require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController
{
    // POST /api/login  (FormData or JSON)
    // body: teacher_code
    public function login(): void
    {
        $data = $this->request();
        $teacher_code = trim((string)($data['teacher_code'] ?? ''));

        if ($teacher_code === '') {
            $this->json(['ok' => false, 'error' => 'teacher_code is required'], 400);
        }

        $pdo = $this->pdo();

        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_code = :code LIMIT 1");
        $stmt->execute([':code' => $teacher_code]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$t) {
            $this->json(['ok' => false, 'error' => 'Invalid teacher_code'], 401);
        }

        // Session user
        $_SESSION['user'] = [
            'id' => (int)$t['id'],
            'role' => (string)$t['role'], // teacher | hod | head_teacher
            'name' => trim($t['firstname'] . ' ' . $t['lastname']),
            'department_id' => $t['department_id'] !== null ? (int)$t['department_id'] : null,
        ];

        // Context (year/term): used by models
        $_SESSION['context'] = [
            'academic_year_id' => $t['academic_year_id'] !== null ? (int)$t['academic_year_id'] : null,
            'term_id' => $t['term_id'] !== null ? (int)$t['term_id'] : null,
        ];

        $this->json([
            'ok' => true,
            'message' => 'Login successful',
            'user' => $_SESSION['user'],
            'context' => $_SESSION['context'],
        ]);
    }

    // GET /api/me
    public function me(): void
    {
        $this->requireAuth();
        $this->json([
            'ok' => true,
            'user' => $_SESSION['user'],
            'context' => $_SESSION['context'],
        ]);
    }

    // POST /api/logout
    public function logout(): void
    {
        session_destroy();
        $this->json(['ok' => true, 'message' => 'Logged out']);
    }

    // (Optional) POST /api/context  -> set active year/term in session
    // body: academic_year_id, term_id
    public function setContext(): void
    {
        $this->requireAuth();

        $data = $this->request();
        $year = isset($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
        $term = isset($data['term_id']) ? (int)$data['term_id'] : null;

        // Basic validation: allow nulls, but if provided must exist
        $pdo = $this->pdo();

        if ($year !== null) {
            $st = $pdo->prepare("SELECT 1 FROM academic_years WHERE id=:id LIMIT 1");
            $st->execute([':id' => $year]);
            if (!$st->fetchColumn()) {
                $this->json(['ok' => false, 'error' => 'Invalid academic_year_id'], 400);
            }
        }

        if ($term !== null) {
            $st = $pdo->prepare("SELECT 1 FROM terms WHERE id=:id LIMIT 1");
            $st->execute([':id' => $term]);
            if (!$st->fetchColumn()) {
                $this->json(['ok' => false, 'error' => 'Invalid term_id'], 400);
            }
        }

        $_SESSION['context']['academic_year_id'] = $year;
        $_SESSION['context']['term_id'] = $term;

        $this->json(['ok' => true, 'context' => $_SESSION['context']]);
    }
}
