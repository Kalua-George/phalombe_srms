<?php
require_once __DIR__ . '/../config/database.php';

class Teacher {
    private $conn;
    private $table = 'teachers';
    private $logs_table = 'logs';

    // Teacher properties
    public $id;
    public $teacher_code;
    public $firstname;
    public $lastname;
    public $contact_no;
    public $role;  // teacher, hod, head_teacher
    public $department_id;

    // Actor performing the action
    public $user_id;

    // Term + Year tracking
    public $academic_year_id;
    public $term_id;

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        $database = new Database();
        $this->conn = $database->getConnection();

        $this->user_id = $user_id;
        $this->academic_year_id = $academic_year_id;
        $this->term_id = $term_id;
    }

    // Log helper
    private function logAction($action, $description = null) {
        if(!$this->user_id) return;

        $query = "INSERT INTO {$this->logs_table}
                 (user_id, action, description, academic_year_id, term_id)
                 VALUES (:user_id, :action, :description, :academic_year_id, :term_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);

        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);

        $stmt->execute();
    }

    // Create teacher
    public function create() {
        $query = "INSERT INTO {$this->table}
                  (teacher_code, firstname, lastname, contact_no, role, department_id)
                  VALUES (:teacher_code, :firstname, :lastname, :contact_no, :role, :department_id)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':teacher_code', $this->teacher_code);
        $stmt->bindParam(':firstname', $this->firstname);
        $stmt->bindParam(':lastname', $this->lastname);
        $stmt->bindParam(':contact_no', $this->contact_no);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':department_id', $this->department_id);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();

            $this->logAction("Create Teacher",
                "Created teacher {$this->firstname} {$this->lastname} ({$this->teacher_code})");

            return true;
        }
        return false;
    }

    // Update teacher
    public function update() {
        $query = "UPDATE {$this->table} SET
                  teacher_code = :teacher_code,
                  firstname = :firstname,
                  lastname = :lastname,
                  contact_no = :contact_no,
                  role = :role,
                  department_id = :department_id
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':teacher_code', $this->teacher_code);
        $stmt->bindParam(':firstname', $this->firstname);
        $stmt->bindParam(':lastname', $this->lastname);
        $stmt->bindParam(':contact_no', $this->contact_no);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->logAction("Update Teacher", "Updated Teacher ID {$this->id}");
            return true;
        }
        return false;
    }

    // Delete teacher
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->logAction("Delete Teacher", "Deleted Teacher ID {$this->id}");
            return true;
        }
        return false;
    }

    // Get all teachers (JSON ready)
    public function getAll() {
        $query = "SELECT t.*, d.name AS department_name
                  FROM {$this->table} t
                  LEFT JOIN departments d ON t.department_id = d.id
                  ORDER BY t.lastname ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Get teacher by ID
    public function getById($id) {
        $query = "SELECT t.*, d.name AS department_name
                  FROM {$this->table} t
                  LEFT JOIN departments d ON t.department_id = d.id
                  WHERE t.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    }

    // SUBJECTS + CLASSES =======================================

    public function getClasses() {
        $query = "SELECT c.*
                  FROM teacher_classes tc
                  JOIN classes c ON tc.class_id = c.id
                  WHERE tc.teacher_id = :teacher_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getSubjects() {
        $query = "SELECT s.*
                  FROM teacher_subjects ts
                  JOIN subjects s ON ts.subject_id = s.id
                  WHERE ts.teacher_id = :teacher_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Assign class
    public function assignClass($class_id) {
        $query = "INSERT IGNORE INTO teacher_classes (teacher_id, class_id)
                  VALUES (:teacher_id, :class_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $this->id);
        $stmt->bindParam(':class_id', $class_id);

        if ($stmt->execute()) {
            $this->logAction("Assign Class", "Class {$class_id} → Teacher {$this->id}");
            return true;
        }
        return false;
    }

    // Assign subject
    public function assignSubject($subject_id) {
        $query = "INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id)
                  VALUES (:teacher_id, :subject_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $this->id);
        $stmt->bindParam(':subject_id', $subject_id);

        if ($stmt->execute()) {
            $this->logAction("Assign Subject", "Subject {$subject_id} → Teacher {$this->id}");
            return true;
        }
        return false;
    }
}
