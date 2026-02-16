<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Teacher.php';

class HODController extends BaseController
{
    // =========================
    // GET /api/hod/dashboard
    // HOD only
    // =========================
    public function dashboard(): void
    {
        $this->requireRole(['hod']);

        $pdo   = $this->pdo();
        $hodId = $this->userId();

        $deptId = $this->hodDepartmentId($hodId);
        if (!$deptId) {
            $this->json([
                'ok' => true,
                'data' => [
                    'department_id' => null,
                    'teachers' => [],
                    'summary' => [
                        'teachers_total' => 0,
                        'teachers_with_classes' => 0,
                        'teachers_with_subjects' => 0
                    ]
                ]
            ]);
        }

        // Teachers in my department
        $stmt = $pdo->prepare("
            SELECT id, teacher_code, firstname, lastname, role, department_id
            FROM teachers
            WHERE department_id = :dept
            ORDER BY lastname ASC, firstname ASC
        ");
        $stmt->execute([':dept' => $deptId]);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary: assigned classes/subjects counts
        $withClasses  = 0;
        $withSubjects = 0;

        foreach ($teachers as $t) {
            $st = $pdo->prepare("SELECT 1 FROM teacher_classes WHERE teacher_id=:tid LIMIT 1");
            $st->execute([':tid' => (int)$t['id']]);
            if ($st->fetchColumn()) $withClasses++;

            $st = $pdo->prepare("SELECT 1 FROM teacher_subjects WHERE teacher_id=:tid LIMIT 1");
            $st->execute([':tid' => (int)$t['id']]);
            if ($st->fetchColumn()) $withSubjects++;
        }

        // Department name (nice for UI)
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $deptId]);
        $deptName = $stmt->fetchColumn() ?: null;

        $this->json([
            'ok' => true,
            'data' => [
                'department_id' => $deptId,
                'department_name' => $deptName,
                'teachers' => $teachers,
                'summary' => [
                    'teachers_total' => count($teachers),
                    'teachers_with_classes' => $withClasses,
                    'teachers_with_subjects' => $withSubjects
                ]
            ]
        ]);
    }

    // =========================
    // GET /api/hod/teachers
    // HOD only (teachers in their department)
    // =========================
    public function teachers(): void
    {
        $this->requireRole(['hod']);

        $pdo   = $this->pdo();
        $deptId = $this->hodDepartmentId($this->userId());
        if (!$deptId) $this->json(['ok' => true, 'data' => []]);

        $stmt = $pdo->prepare("
            SELECT t.*, d.name AS department_name
            FROM teachers t
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.department_id = :dept
            ORDER BY t.lastname ASC, t.firstname ASC
        ");
        $stmt->execute([':dept' => $deptId]);
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =========================
    // POST /api/hod/assign-class
    // HOD only (teacher must be in HOD department)
    // body: teacher_id, class_id
    // =========================
    public function assignClass(): void
    {
        $this->requireRole(['hod']);

        $data = $this->request();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $classId   = (int)($data['class_id'] ?? 0);

        if ($teacherId <= 0 || $classId <= 0) {
            $this->json(['ok' => false, 'error' => 'teacher_id and class_id are required'], 400);
        }

        $pdo = $this->pdo();
        $deptId = $this->hodDepartmentId($this->userId());
        if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

        // ensure teacher belongs to HOD department
        $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
        $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
        if (!$stmt->fetchColumn()) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        if ($m->assignClass($classId)) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to assign class'], 500);
    }

    // =========================
    // POST /api/hod/assign-subject
    // HOD only (teacher must be in HOD department)
    // body: teacher_id, subject_id
    // =========================
    public function assignSubject(): void
    {
        $this->requireRole(['hod']);

        $data = $this->request();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);

        if ($teacherId <= 0 || $subjectId <= 0) {
            $this->json(['ok' => false, 'error' => 'teacher_id and subject_id are required'], 400);
        }

        $pdo = $this->pdo();
        $deptId = $this->hodDepartmentId($this->userId());
        if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

        // ensure teacher belongs to HOD department
        $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
        $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
        if (!$stmt->fetchColumn()) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        if ($m->assignSubject($subjectId)) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to assign subject'], 500);
    }

    // =========================
    // GET /api/hod/teacher-classes?teacher_id=1
    // HOD only (teacher must be in HOD department)
    // =========================
    public function teacherClasses(): void
    {
        $this->requireRole(['hod']);

        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        if ($teacherId <= 0) $this->json(['ok' => false, 'error' => 'Invalid teacher_id'], 400);

        $pdo = $this->pdo();
        $deptId = $this->hodDepartmentId($this->userId());
        if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

        $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
        $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
        if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        $data = json_decode($m->getClasses(), true);
        $this->json(['ok' => true, 'data' => $data ?? []]);
    }

    // =========================
    // GET /api/hod/teacher-subjects?teacher_id=1
    // HOD only (teacher must be in HOD department)
    // =========================
    public function teacherSubjects(): void
    {
        $this->requireRole(['hod']);

        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        if ($teacherId <= 0) $this->json(['ok' => false, 'error' => 'Invalid teacher_id'], 400);

        $pdo = $this->pdo();
        $deptId = $this->hodDepartmentId($this->userId());
        if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

        $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
        $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
        if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        $data = json_decode($m->getSubjects(), true);
        $this->json(['ok' => true, 'data' => $data ?? []]);
    }
}
