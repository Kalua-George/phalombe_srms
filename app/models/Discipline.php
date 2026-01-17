<?php
require_once __DIR__ . '/BaseModel.php';

class Discipline extends BaseModel {
    private $table = 'discipline_cases';

    public $id;
    public $student_id;
    public $teacher_id;     // person reporting/logging the case
    public $case_type;      // e.g. "Late to class"
    public $description;
    public $action_taken;   // e.g. "Warning"
    public $severity;       // Low, Medium, High

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        parent::__construct($user_id, $academic_year_id, $term_id);
    }

    private function validate() {
        if (empty($this->student_id) || empty($this->teacher_id) || empty($this->case_type) || empty($this->severity)) {
            throw new Exception("student_id, teacher_id, case_type, and severity are required.");
        }

        $allowedSeverity = ['Low', 'Medium', 'High'];
        if (!in_array($this->severity, $allowedSeverity, true)) {
            throw new Exception("Invalid severity. Allowed: Low, Medium, High.");
        }

        if (empty($this->academic_year_id) || empty($this->term_id)) {
            throw new Exception("academic_year_id and term_id are required.");
        }
    }

    // Create a new discipline case
    public function create() {
        try {
            $this->validate();

            $query = "INSERT INTO {$this->table}
                      (student_id, teacher_id, case_type, description, action_taken, severity, academic_year_id, term_id)
                      VALUES
                      (:student_id, :teacher_id, :case_type, :description, :action_taken, :severity, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $this->student_id, PDO::PARAM_INT);
            $stmt->bindParam(':teacher_id', $this->teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(':case_type', $this->case_type);
            $stmt->bindParam(':description', $this->description);
            $stmt->bindParam(':action_taken', $this->action_taken);
            $stmt->bindParam(':severity', $this->severity);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->id = (int)$this->conn->lastInsertId();
                $this->logAction("Create Discipline Case", "Case ID {$this->id} for student {$this->student_id}");
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log("Discipline create() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Discipline create() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Update a discipline case
    public function update() {
        try {
            if (empty($this->id)) throw new Exception("Discipline case id is required for update.");
            $this->validate();

            $query = "UPDATE {$this->table} SET
                        case_type = :case_type,
                        description = :description,
                        action_taken = :action_taken,
                        severity = :severity,
                        student_id = :student_id,
                        teacher_id = :teacher_id,
                        academic_year_id = :academic_year_id,
                        term_id = :term_id
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':case_type', $this->case_type);
            $stmt->bindParam(':description', $this->description);
            $stmt->bindParam(':action_taken', $this->action_taken);
            $stmt->bindParam(':severity', $this->severity);
            $stmt->bindParam(':student_id', $this->student_id, PDO::PARAM_INT);
            $stmt->bindParam(':teacher_id', $this->teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logAction("Update Discipline Case", "Case ID {$this->id} updated");
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log("Discipline update() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Discipline update() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Delete a discipline record
    public function delete() {
        try {
            if (empty($this->id)) throw new Exception("Discipline case id is required for delete.");

            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logAction("Delete Discipline Case", "Case ID {$this->id} deleted");
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log("Discipline delete() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Discipline delete() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Get one discipline record by id
    public function getById($id) {
        try {
            $query = "SELECT dc.*, st.fname, st.lname,
                             t.firstname AS teacher_fname, t.lastname AS teacher_lname
                      FROM {$this->table} dc
                      JOIN students st ON dc.student_id = st.id
                      JOIN teachers t ON dc.teacher_id = t.id
                      WHERE dc.id = :id
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Discipline getById() Error: " . $e->getMessage());
            return null;
        }
    }

    // Get all discipline cases for a student (optionally filtered by current year+term)
    public function getByStudent($student_id, $filterByTermYear = true) {
        try {
            $query = "SELECT dc.*, t.firstname AS teacher_fname, t.lastname AS teacher_lname
                      FROM {$this->table} dc
                      JOIN teachers t ON dc.teacher_id = t.id
                      WHERE dc.student_id = :student_id";

            if ($filterByTermYear) {
                $query .= " AND dc.academic_year_id = :academic_year_id AND dc.term_id = :term_id";
            }

            $query .= " ORDER BY dc.id DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);

            if ($filterByTermYear) {
                $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
                $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Discipline getByStudent() Error: " . $e->getMessage());
            return [];
        }
    }

    // Get all discipline cases for a given year + term (admin/reporting)
    public function getByTerm($academic_year_id, $term_id) {
        try {
            $query = "SELECT dc.*, st.fname, st.lname
                      FROM {$this->table} dc
                      JOIN students st ON dc.student_id = st.id
                      WHERE dc.academic_year_id = :year AND dc.term_id = :term
                      ORDER BY dc.id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':year', $academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term', $term_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Discipline getByTerm() Error: " . $e->getMessage());
            return [];
        }
    }
}
