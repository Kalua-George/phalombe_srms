<?php
require_once __DIR__ . '/../config/database.php';

class Discipline {
    private $conn;
    private $table = 'discipline_cases';
    private $logs_table = 'logs';

    public $id;
    public $student_id;
    public $teacher_id; // person reporting or logging the case
    public $case_type;   // e.g. "Late to class", "Fighting", "Uniform issue"
    public $description;
    public $action_taken; // e.g. "Warning", "Suspension", "Parent Called"
    public $severity; // Low, Medium, High

    public $academic_year_id;
    public $term_id;

    public $user_id; // user performing the action

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        $database = new Database();
        $this->conn = $database->getConnection();

        $this->user_id = $user_id;
        $this->academic_year_id = $academic_year_id;
        $this->term_id = $term_id;
    }

    private function logAction($action, $description = null) {
        if (!$this->user_id) return;

        $query = "INSERT INTO {$this->logs_table}
                  (user_id, action, description, academic_year_id, term_id)
                  VALUES (:user_id, :action, :description, :academic_year_id, :term_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);
        $stmt->execute();
    }

    // Create a new discipline case
    public function create() {
        $query = "INSERT INTO {$this->table}
                 (student_id, teacher_id, case_type, description, action_taken, severity,
                  academic_year_id, term_id)
                  VALUES
                 (:student_id, :teacher_id, :case_type, :description, :action_taken, :severity,
                  :academic_year_id, :term_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->bindParam(':case_type', $this->case_type);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':action_taken', $this->action_taken);
        $stmt->bindParam(':severity', $this->severity);
        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->logAction("Create Discipline Case", "Case ID {$this->id} for student {$this->student_id}");
            return true;
        }
        return false;
    }

    // Update a discipline case
    public function update() {
        $query = "UPDATE {$this->table} SET
                    case_type = :case_type,
                    description = :description,
                    action_taken = :action_taken,
                    severity = :severity
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':case_type', $this->case_type);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':action_taken', $this->action_taken);
        $stmt->bindParam(':severity', $this->severity);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            $this->logAction("Update Discipline Case", "Case ID {$this->id} updated");
            return true;
        }
        return false;
    }

    // Delete a discipline record
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            $this->logAction("Delete Discipline Case", "Case ID {$this->id} deleted");
            return true;
        }
        return false;
    }

    // Get one discipline record
    public function getById($id) {
        $query = "SELECT dc.*, st.fname, st.lname, t.fname AS teacher_fname, t.lname AS teacher_lname
                  FROM {$this->table} dc
                  JOIN students st ON dc.student_id = st.id
                  JOIN teachers t ON dc.teacher_id = t.id
                  WHERE dc.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    // Get all discipline cases for a student
    public function getByStudent($student_id) {
        $query = "SELECT dc.*, t.fname AS teacher_fname, t.lname AS teacher_lname
                  FROM {$this->table} dc
                  JOIN teachers t ON dc.teacher_id = t.id
                  WHERE dc.student_id = :student_id
                  ORDER BY dc.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Get all discipline cases for a given year + term
    public function getByTerm($academic_year_id, $term_id) {
        $query = "SELECT dc.*, st.fname, st.lname
                  FROM {$this->table} dc
                  JOIN students st ON dc.student_id = st.id
                  WHERE dc.academic_year_id = :year AND dc.term_id = :term
                  ORDER BY dc.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $academic_year_id);
        $stmt->bindParam(':term', $term_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
