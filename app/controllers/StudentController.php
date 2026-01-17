<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Student.php';

class StudentController extends BaseController
{
    // GET /api/students
    public function index(): void
    {
        $this->requireAuth();

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $rows = $m->getAll();

        $this->json(['ok' => true, 'data' => $rows]);
    }

    // GET /api/students/show?id=1
    public function show(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $row = $m->getById($id);

        if (!$row) $this->json(['ok' => false, 'error' => 'Student not found'], 404);

        $this->json(['ok' => true, 'data' => $row]);
    }

    // POST /api/students/create
    public function create(): void
    {
        // Headteacher manages core structure; allow HOD too if you want:
        $this->requireRole(['head_teacher']);

        $data = $this->request();

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $m->student_code    = $data['student_code'] ?? null;
        $m->fname           = trim((string)($data['fname'] ?? ''));
        $m->lname           = trim((string)($data['lname'] ?? ''));
        $m->class_id        = (int)($data['class_id'] ?? 0);
        $m->form_id         = (int)($data['form_id'] ?? 0);
        $m->dob             = $data['dob'] ?? null;
        $m->home            = $data['home'] ?? null;
        $m->parent_contact  = $data['parent_contact'] ?? null;

        // ensure term/year stored (your model expects these columns exist)
        $m->academic_year_id = $this->yearId();
        $m->term_id          = $this->termId();

        if ($m->fname === '' || $m->lname === '' || $m->class_id <= 0 || $m->form_id <= 0) {
            $this->json(['ok' => false, 'error' => 'fname, lname, class_id, form_id are required'], 400);
        }

        if ($m->create()) {
            $this->json(['ok' => true, 'id' => $m->id]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to create student'], 500);
    }

    // POST /api/students/update
    public function update(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $m->id             = $id;
        $m->student_code   = $data['student_code'] ?? null;
        $m->fname          = trim((string)($data['fname'] ?? ''));
        $m->lname          = trim((string)($data['lname'] ?? ''));
        $m->class_id       = (int)($data['class_id'] ?? 0);
        $m->form_id        = (int)($data['form_id'] ?? 0);
        $m->dob            = $data['dob'] ?? null;
        $m->home           = $data['home'] ?? null;
        $m->parent_contact = $data['parent_contact'] ?? null;

        $m->academic_year_id = $this->yearId();
        $m->term_id          = $this->termId();

        if ($m->fname === '' || $m->lname === '' || $m->class_id <= 0 || $m->form_id <= 0) {
            $this->json(['ok' => false, 'error' => 'fname, lname, class_id, form_id are required'], 400);
        }

        if ($m->update()) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to update student'], 500);
    }

    // POST /api/students/delete
    public function delete(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $m->id = $id;

        if ($m->delete()) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to delete student'], 500);
    }

    // GET /api/students/attendance?id=1
    public function attendance(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $m->id = $id;

        $rows = $m->getAttendance();
        $this->json(['ok' => true, 'data' => $rows]);
    }

    // GET /api/students/grades?id=1
    public function grades(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Student($this->userId(), $this->yearId(), $this->termId());
        $m->id = $id;

        $rows = $m->getGrades();
        $this->json(['ok' => true, 'data' => $rows]);
    }
}
