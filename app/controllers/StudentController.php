<?php

require_once __DIR__ . '/../models/Student.php';

class StudentController
{
    // GET /students
    public function index()
    {
        $student = new Student();
        $result = $student->getAll();

        echo json_encode([
            "status" => "success",
            "data" => $result
        ]);
    }

    // GET /students/{id}
    public function show($id)
    {
        $student = new Student();
        $data = $student->getById($id);

        if ($data) {
            echo json_encode([
                "status" => "success",
                "data" => $data
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Student not found"
            ]);
        }
    }

    // POST /students
    public function store()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $student = new Student();

        $student->student_code      = $input['student_code'] ?? null;
        $student->firstname         = $input['firstname'] ?? null;
        $student->lastname          = $input['lastname'] ?? null;
        $student->gender            = $input['gender'] ?? null;
        $student->dob               = $input['dob'] ?? null;
        $student->class_id          = $input['class_id'] ?? null;
        $student->contact_no        = $input['contact_no'] ?? null; 
        $student->academic_year_id  = $input['academic_year_id'] ?? null;
        $student->term_id           = $input['term_id'] ?? null;

        if ($student->create()) {
            echo json_encode([
                "status" => "success",
                "message" => "Student created successfully",
                "id" => $student->id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to create student (Duplicate student_code?)"
            ]);
        }
    }

    // PUT /students/{id}
    public function update($id)
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $student = new Student();
        $existing = $student->getById($id);

        if (!$existing) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Student not found"
            ]);
            return;
        }

        // keep existing values if not passed
        $student->id               = $id;
        $student->student_code     = $input['student_code'] ?? $existing['student_code'];
        $student->firstname        = $input['firstname'] ?? $existing['firstname'];
        $student->lastname         = $input['lastname'] ?? $existing['lastname'];
        $student->gender           = $input['gender'] ?? $existing['gender'];
        $student->dob              = $input['dob'] ?? $existing['dob'];
        $student->class_id         = $input['class_id'] ?? $existing['class_id'];
        $student->contact_no       = $input['contact_no'] ?? $existing['contact_no'];
        $student->academic_year_id = $input['academic_year_id'] ?? $existing['academic_year_id'];
        $student->term_id          = $input['term_id'] ?? $existing['term_id'];

        if ($student->update()) {
            echo json_encode([
                "status" => "success",
                "message" => "Student updated successfully"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to update student"
            ]);
        }
    }

    // DELETE /students/{id}
    public function destroy($id)
    {
        $student = new Student();
        $student->id = $id;

        if ($student->delete()) {
            echo json_encode([
                "status" => "success",
                "message" => "Student deleted"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Failed to delete student"
            ]);
        }
    }
}

