<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Grade.php';

class ExamController extends BaseController
{
    // =========================================================
    // GET /api/exams
    // all authenticated roles can view exams (read-only)
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();

        $m = new Exam($this->userId(), $this->yearId(), $this->termId());
        $rows = $m->getAll();

        $this->json(['ok' => true, 'data' => $rows]);
    }

    // =========================================================
    // GET /api/exams/show?id=1
    // all authenticated roles can view an exam
    // =========================================================
    public function show(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Exam($this->userId(), $this->yearId(), $this->termId());
        $row = $m->getById($id);

        if (!$row) $this->json(['ok' => false, 'error' => 'Exam not found'], 404);

        $this->json(['ok' => true, 'data' => $row]);
    }

    // =========================================================
    // GET /api/exams/subjects?exam_id=1
    // all authenticated roles can view exam subjects
    // =========================================================
    public function subjects(): void
    {
        $this->requireAuth();

        $examId = (int)($_GET['exam_id'] ?? 0);
        if ($examId <= 0) $this->json(['ok' => false, 'error' => 'Invalid exam_id'], 400);

        $m = new Exam($this->userId(), $this->yearId(), $this->termId());
        $m->id = $examId;

        $rows = $m->getSubjects();
        $this->json(['ok' => true, 'data' => $rows]);
    }

    // =========================================================
    // POST /api/exams/create
    // head_teacher only
    // body: name, exam_type, exam_date, subject_ids[]
    // =========================================================
    public function create(): void
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

        // Ensure ints
        $subjectIds = array_values(array_filter(array_map(fn($x) => (int)$x, $subjectIds), fn($x) => $x > 0));

        $m = new Exam($this->userId(), $this->yearId(), $this->termId());
        $m->name = $name;
        $m->exam_type = $type;
        $m->exam_date = $date;

        if ($m->create($subjectIds)) {
            $this->json(['ok' => true, 'id' => (int)$m->id]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to create exam'], 500);
    }

    // =========================================================
    // POST /api/exams/delete
    // head_teacher only
    // body: id
    // =========================================================
    public function delete(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Exam($this->userId(), $this->yearId(), $this->termId());
        $m->id = $id;

        if ($m->delete()) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to delete exam'], 500);
    }

    // =========================================================
    // POST /api/exams/enter-grade
    // teacher only (or allow hod/head_teacher if you want)
    //
    // Body:
    //  exam_id, student_id, subject_id, score, grade_id(optional)
    //
    // SoD enforcement:
    //  - teacher must be assigned to subject
    //  - teacher must be assigned to student's class
    // =========================================================
    public function enterGrade(): void
    {
        $this->requireRole(['teacher', 'hod', 'head_teacher']);

        $data = $this->request();

        $examId    = (int)($data['exam_id'] ?? 0);
        $studentId = (int)($data['student_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        $scoreRaw  = $data['score'] ?? null;
        $gradeId   = isset($data['grade_id']) && $data['grade_id'] !== '' ? (int)$data['grade_id'] : null;

        if ($examId <= 0 || $studentId <= 0 || $subjectId <= 0 || $scoreRaw === null || $scoreRaw === '') {
            $this->json(['ok' => false, 'error' => 'exam_id, student_id, subject_id, score are required'], 400);
        }

        $score = (float)$scoreRaw;
        if ($score < 0 || $score > 100) {
            $this->json(['ok' => false, 'error' => 'score must be between 0 and 100'], 400);
        }

        $role = $this->role();
        $tid  = $this->userId();

        // Teachers: strict checks
        if ($role === 'teacher') {
            if (!$this->teacherHasSubject($tid, $subjectId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: not assigned to subject'], 403);
            }

            $classId = $this->studentClassId($studentId);
            if (!$classId || !$this->teacherHasClass($tid, $classId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: student not in your class'], 403);
            }
        }

        // HOD: allow if the teacher is in their department? (optional)
        // For now: HOD can enter grades only if they themselves are assigned like a teacher
        if ($role === 'hod') {
            if (!$this->teacherHasSubject($tid, $subjectId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: not assigned to subject'], 403);
            }
            $classId = $this->studentClassId($studentId);
            if (!$classId || !$this->teacherHasClass($tid, $classId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: student not in your class'], 403);
            }
        }

        // Head teacher: allow (admin override), or you can restrict similarly.
        // We'll allow by default but still record teacher_id = current user in Grade.
        $g = new Grade($tid, $this->yearId(), $this->termId());
        $g->exam_id    = $examId;
        $g->student_id = $studentId;
        $g->subject_id = $subjectId;
        $g->teacher_id = $tid;
        $g->score      = $score;
        $g->grade_id   = $gradeId;

        if ($g->save()) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to save grade'], 500);
    }

    // =========================================================
    // GET /api/exams/grades?exam_id=1&subject_id=2&class_id=3
    //
    // teacher: only for subjects they teach AND classes they are assigned
    // hod/head_teacher: allowed (hod scope optional)
    // =========================================================
    public function grades(): void
    {
        $this->requireAuth();

        $examId    = (int)($_GET['exam_id'] ?? 0);
        $subjectId = (int)($_GET['subject_id'] ?? 0);
        $classId   = (int)($_GET['class_id'] ?? 0);

        if ($examId <= 0) $this->json(['ok' => false, 'error' => 'exam_id is required'], 400);

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        if ($role === 'teacher') {
            if ($subjectId > 0 && !$this->teacherHasSubject($uid, $subjectId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: not assigned to subject'], 403);
            }
            if ($classId > 0 && !$this->teacherHasClass($uid, $classId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: not assigned to class'], 403);
            }
        }

        // basic query: grades for exam, optionally filter by subject/class
        $sql = "
            SELECT g.*, st.fname, st.lname, s.name AS subject_name
            FROM grades g
            JOIN students st ON st.id = g.student_id
            JOIN subjects s ON s.id = g.subject_id
            WHERE g.exam_id = :exam
        ";
        $params = [':exam' => $examId];

        if ($subjectId > 0) {
            $sql .= " AND g.subject_id = :sub";
            $params[':sub'] = $subjectId;
        }

        if ($classId > 0) {
            $sql .= " AND st.class_id = :cls";
            $params[':cls'] = $classId;
        }

        $sql .= " ORDER BY st.lname ASC, st.fname ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->json(['ok' => true, 'data' => $rows]);
    }
}
