<?php

require_once __DIR__ . '/../models/Teacher.php';

class TeacherController
{
    // GET: /teachers
    public function index()
    {
        $teacher = new Teacher();
        $result = $teacher->getAll();

        echo json_encode([
            "status" => "success",
            "data" => $result
        ]);
    }

    // GET: /teachers/{id}
    public function show($id)
    {
        $teacher = new Teacher();
        $data = $teacher->getById($id);

        if ($data) {
            echo json_encode(["status" => "success", "data" => $data]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Teacher not found"]);
        }
    }

    // POST: /teachers
    public function store()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $teacher = new Teacher();

        $teacher->teacher_code      = $input['teacher_code'] ?? null;
        $teacher->firstname         = $input['firstname'] ?? null;
        $teacher->lastname          = $input['lastname'] ?? null;
        $teacher->contact_no        = $input['contact_no'] ?? null;
        $teacher->role              = $input['role'] ?? "teacher";
        $teacher->academic_year_id  = $input['academic_year_id'] ?? null;
        $teacher->term_id           = $input['term_id'] ?? null;
        $teacher->department_id     = $input['department_id'] ?? null;

        if ($teacher->create()) {
            echo json_encode([
                "status" => "success",
                "message" => "Teacher created successfully",
                "id" => $teacher->id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create teacher (Duplicate teacher_code?)"
            ]);
        }
    }

    // PUT: /teachers/{id}
    public function update($id)
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $teacher = new Teacher();
        $existing = $teacher->getById($id);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Teacher not found"]);
            return;
        }

        // Keep old values if missing
        $teacher->id               = $id;
        $teacher->teacher_code     = $input['teacher_code'] ?? $existing['teacher_code'];
        $teacher->firstname        = $input['firstname'] ?? $existing['firstname'];
        $teacher->lastname         = $input['lastname'] ?? $existing['lastname'];
        $teacher->contact_no       = $input['contact_no'] ?? $existing['contact_no'];
        $teacher->role             = $input['role'] ?? $existing['role'];
        $teacher->academic_year_id = $input['academic_year_id'] ?? $existing['academic_year_id'];
        $teacher->term_id          = $input['term_id'] ?? $existing['term_id'];
        $teacher->department_id    = $input['department_id'] ?? $existing['department_id'];

        if ($teacher->update()) {
            echo json_encode([
                "status" => "success",
                "message" => "Teacher updated successfully"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to update teacher"
            ]);
        }
    }

    // DELETE: /teachers/{id}
    public function destroy($id)
    {
        $teacher = new Teacher();
        $teacher->id = $id;

        if ($teacher->delete()) {
            echo json_encode(["status" => "success", "message" => "Teacher deleted"]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Delete failed"
            ]);
        }
    }
}
