<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/exam.php';

class HeadTeacherController extends BaseController
{
    // =========================
    // GET /api/head/dashboard
    // head_teacher only
    // =========================
    public function dashboard(): void
    {
        $this->requireRole(['head_teacher']);

        $pdo   = $this->pdo();
        $today = date('Y-m-d');

        $students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $teachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
        $classes  = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

        // Attendance today per class
        $stmt = $pdo->prepare("
            SELECT c.id AS class_id, c.name AS class_name, c.form_id,
                   COUNT(a.id) AS records
            FROM classes c
            LEFT JOIN attendance a
              ON a.class_id = c.id AND a.attendance_date = :dt
            GROUP BY c.id
            ORDER BY c.form_id ASC, c.name ASC
        ");
        $stmt->execute([':dt' => $today]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Latest exams
        $stmt = $pdo->query("
            SELECT id, name, exam_type, exam_date
            FROM exams
            ORDER BY exam_date DESC
            LIMIT 5
        ");
        $latestExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->json([
            'ok' => true,
            'data' => [
                'date' => $today,
                'counts' => [
                    'students' => $students,
                    'teachers' => $teachers,
                    'classes'  => $classes
                ],
                'attendance_today' => $attendance,
                'latest_exams' => $latestExams
            ]
        ]);
    }

    // =========================
    // TEACHERS MANAGEMENT
    // (head_teacher only)
    // =========================

    // POST /api/head/teachers/create
    public function createTeacher(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->teacher_code  = $data['teacher_code'] ?? null;
        $m->firstname     = trim((string)($data['firstname'] ?? ''));
        $m->lastname      = trim((string)($data['lastname'] ?? ''));
        $m->contact_no    = $data['contact_no'] ?? null;
        $m->role          = $data['role'] ?? 'teacher';
        $m->department_id = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

        if ($m->firstname === '' || $m->lastname === '') {
            $this->json(['ok' => false, 'error' => 'firstname and lastname are required'], 400);
        }

        if ($m->create()) {
            $this->json(['ok' => true, 'id' => (int)$m->id]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to create teacher'], 500);
    }

    // POST /api/head/teachers/update
    public function updateTeacher(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id           = $id;
        $m->teacher_code = $data['teacher_code'] ?? null;
        $m->firstname    = trim((string)($data['firstname'] ?? ''));
        $m->lastname     = trim((string)($data['lastname'] ?? ''));
        $m->contact_no   = $data['contact_no'] ?? null;
        $m->role         = $data['role'] ?? 'teacher';
        $m->department_id = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

        if ($m->firstname === '' || $m->lastname === '') {
            $this->json(['ok' => false, 'error' => 'firstname and lastname are required'], 400);
        }

        if ($m->update()) {
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to update teacher'], 500);
    }

    // POST /api/head/teachers/delete
    public function deleteTeacher(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $id;

        if ($m->delete()) {
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to delete teacher'], 500);
    }

    // GET /api/head/teachers
    public function teachers(): void
    {
        $this->requireRole(['head_teacher']);

        $pdo = $this->pdo();
        $stmt = $pdo->query("
            SELECT t.*, d.name AS department_name
            FROM teachers t
            LEFT JOIN departments d ON d.id = t.department_id
            ORDER BY t.lastname ASC, t.firstname ASC
        ");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =========================
    // ASSIGNMENTS (head_teacher allowed)
    // =========================

    // POST /api/head/assign-class  body: teacher_id, class_id
    public function assignClass(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $classId   = (int)($data['class_id'] ?? 0);

        if ($teacherId <= 0 || $classId <= 0) {
            $this->json(['ok' => false, 'error' => 'teacher_id and class_id are required'], 400);
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        if ($m->assignClass($classId)) {
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to assign class'], 500);
    }

    // POST /api/head/assign-subject  body: teacher_id, subject_id
    public function assignSubject(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);

        if ($teacherId <= 0 || $subjectId <= 0) {
            $this->json(['ok' => false, 'error' => 'teacher_id and subject_id are required'], 400);
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        if ($m->assignSubject($subjectId)) {
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to assign subject'], 500);
    }

    // =========================
    // CLASSES + FORMS
    // (head_teacher only)
    // =========================

    // GET /api/head/forms
    public function forms(): void
    {
        $this->requireRole(['head_teacher']);

        $pdo = $this->pdo();
        $stmt = $pdo->query("SELECT id, name FROM forms ORDER BY id ASC");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /api/head/classes
    public function classes(): void
    {
        $this->requireRole(['head_teacher']);

        $pdo = $this->pdo();
        $stmt = $pdo->query("
            SELECT c.*, f.name AS form_name
            FROM classes c
            JOIN forms f ON f.id = c.form_id
            ORDER BY c.form_id ASC, c.name ASC
        ");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // POST /api/head/classes/create  body: name, form_id
    public function createClass(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $name   = trim((string)($data['name'] ?? ''));
        $formId = (int)($data['form_id'] ?? 0);

        if ($name === '' || $formId <= 0) {
            $this->json(['ok' => false, 'error' => 'name and form_id are required'], 400);
        }

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            INSERT INTO classes (academic_year_id, term_id, name, form_id)
            VALUES (:year, :term, :name, :form)
        ");
        $ok = $stmt->execute([
            ':year' => $this->yearId(),
            ':term' => $this->termId(),
            ':name' => $name,
            ':form' => $formId
        ]);

        if ($ok) {
            $this->json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to create class'], 500);
    }

    // =========================
    // EXAMS
    // (head_teacher only)
    // =========================

    // GET /api/head/subjects
    public function subjects(): void
    {
        $this->requireRole(['head_teacher']);

        $pdo = $this->pdo();
        $stmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name ASC");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /api/head/exams
    public function exams(): void
    {
        $this->requireRole(['head_teacher']);

        $pdo = $this->pdo();
        $stmt = $pdo->query("
            SELECT id, name, exam_type, exam_date, academic_year_id, term_id
            FROM exams
            ORDER BY exam_date DESC
        ");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // POST /api/head/exams/create
    // body: name, exam_type, exam_date, subject_ids[]
    public function createExam(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();

        $name = trim((string)($data['name'] ?? ''));
        $type = (string)($data['exam_type'] ?? '');
        $date = trim((string)($data['exam_date'] ?? ''));
        $subjectIds = $data['subject_ids'] ?? [];

        if ($name === '' || $type === '' || $date === '') {
            $this->json(['ok' => false, 'error' => 'name, exam_type, exam_date are required'], 400);
        }

        if (!is_array($subjectIds)) $subjectIds = [];

        $m = new exam($this->userId(), $this->yearId(), $this->termId());
        $m->name = $name;
        $m->exam_type = $type;
        $m->exam_date = $date;

        if ($m->create($subjectIds)) {
            $this->json(['ok' => true, 'id' => (int)$m->id]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to create exam'], 500);
    }

    // GET /api/head/exams/subjects?exam_id=1
    public function examSubjects(): void
    {
        $this->requireRole(['head_teacher']);

        $examId = (int)($_GET['exam_id'] ?? 0);
        if ($examId <= 0) $this->json(['ok' => false, 'error' => 'Invalid exam_id'], 400);

        $m = new exam($this->userId(), $this->yearId(), $this->termId());
        $m->id = $examId;

        $this->json(['ok' => true, 'data' => $m->getSubjects()]);
    }
}
