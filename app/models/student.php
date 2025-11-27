<?php
require_once __DIR__ . '/../config/database.php';

class Student {
    private $conn;
    private $table = 'students';
    private $logs_table = 'logs';

    // Student properties
    public $id;
    public $student_code;
    public $fname;
    public $lname;
    public $class_id;
    public $form_id;
    public $dob;
    public $home;
    public $parent_contact;

    // The teacher performing actions
    public $user_id;

    public function __construct($user_id = null) {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->user_id = $user_id; // optional, pass current logged-in teacher
    }

    // Helper to log actions
    private function logAction($action, $description = null) {
        if(!$this->user_id) return; // skip if user not set

        $query = "INSERT INTO {$this->logs_table} (user_id, action, description) 
                  VALUES (:user_id, :action, :description)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
    }

    // Create a new student
    public function create() {
        $query = "INSERT INTO {$this->table} 
                  (student_code, fname, lname, class_id, form_id, dob, home, parent_contact)
                  VALUES (:student_code, :fname, :lname, :class_id, :form_id, :dob, :home, :parent_contact)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':student_code', $this->student_code);
        $stmt->bindParam(':fname', $this->fname);
        $stmt->bindParam(':lname', $this->lname);
        $stmt->bindParam(':class_id', $this->class_id);
        $stmt->bindParam(':form_id', $this->form_id);
        $stmt->bindParam(':dob', $this->dob);
        $stmt->bindParam(':home', $this->home);
        $stmt->bindParam(':parent_contact', $this->parent_contact);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->logAction("Create Student", "Student {$this->fname} {$this->lname} created with ID {$this->id}");
            return true;
        }
        return false;
    }

    // Update student
    public function update() {
        $query = "UPDATE {$this->table} SET 
                  student_code = :student_code,
                  fname = :fname,
                  lname = :lname,
                  class_id = :class_id,
                  form_id = :form_id,
                  dob = :dob,
                  home = :home,
                  parent_contact = :parent_contact
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':student_code', $this->student_code);
        $stmt->bindParam(':fname', $this->fname);
        $stmt->bindParam(':lname', $this->lname);
        $stmt->bindParam(':class_id', $this->class_id);
        $stmt->bindParam(':form_id', $this->form_id);
        $stmt->bindParam(':dob', $this->dob);
        $stmt->bindParam(':home', $this->home);
        $stmt->bindParam(':parent_contact', $this->parent_contact);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->logAction("Update Student", "Student ID {$this->id} updated");
            return true;
        }
        return false;
    }

    // Delete student
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        if($stmt->execute()) {
            $this->logAction("Delete Student", "Student ID {$this->id} deleted");
            return true;
        }
        return false;
    }

    // Fetch all students
    public function getAll() {
        $query = "SELECT * FROM {$this->table} ORDER BY lname ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Fetch student by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Fetch student's attendance
    public function getAttendance() {
        $query = "SELECT * FROM attendance WHERE student_id = :student_id ORDER BY attendance_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Fetch student's grades
    public function getGrades() {
        $query = "SELECT g.*, s.name as subject_name, e.name as exam_name
                  FROM grades g
                  JOIN subjects s ON g.subject_id = s.id
                  JOIN exams e ON g.exam_id = e.id
                  WHERE g.student_id = :student_id
                  ORDER BY e.exam_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
