<?php
require_once __DIR__ . '/BaseModel.php';

class Student extends BaseModel {
    private $table = 'students';

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

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        parent::__construct($user_id, $academic_year_id, $term_id);
    }

    // Create a new student
    public function create() {
        try {
            $query = "INSERT INTO {$this->table} 
                      (student_code, fname, lname, class_id, form_id, dob, home, parent_contact, term_id, academic_year_id)
                      VALUES 
                      (:student_code, :fname, :lname, :class_id, :form_id, :dob, :home, :parent_contact, :term_id, :academic_year_id)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_code', $this->student_code);
            $stmt->bindParam(':fname', $this->fname);
            $stmt->bindParam(':lname', $this->lname);
            $stmt->bindParam(':class_id', $this->class_id);
            $stmt->bindParam(':form_id', $this->form_id);
            $stmt->bindParam(':dob', $this->dob);
            $stmt->bindParam(':home', $this->home);
            $stmt->bindParam(':parent_contact', $this->parent_contact);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                $this->logAction("Create Student", "Created Student ID {$this->id}");
                return true;
            }
        } catch (PDOException $e) {
            error_log("Student Create Error: " . $e->getMessage());
        }

        return false;
    }

    // Update a student
    public function update() {
        try {
            $query = "UPDATE {$this->table} SET
                      student_code = :student_code,
                      fname = :fname,
                      lname = :lname,
                      class_id = :class_id,
                      form_id = :form_id,
                      dob = :dob,
                      home = :home,
                      parent_contact = :parent_contact,
                      term_id = :term_id,
                      academic_year_id = :academic_year_id
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
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logAction("Update Student", "Updated Student ID {$this->id}");
                return true;
            }
        } catch (PDOException $e) {
            error_log("Student Update Error: " . $e->getMessage());
        }

        return false;
    }

    // Delete a student
    public function delete() {
        try {
            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logAction("Delete Student", "Deleted Student ID {$this->id}");
                return true;
            }
        } catch (PDOException $e) {
            error_log("Student Delete Error: " . $e->getMessage());
        }

        return false;
    }

    // Fetch all students
    public function getAll() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} ORDER BY lname ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Student GetAll Error: " . $e->getMessage());
            return [];
        }
    }

    // Fetch a student by ID
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Student GetById Error: " . $e->getMessage());
            return null;
        }
    }

    // Get attendance for this student
    public function getAttendance() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM attendance 
                                          WHERE student_id = :student_id 
                                          AND academic_year_id = :academic_year_id
                                          AND term_id = :term_id
                                          ORDER BY attendance_date DESC");
            $stmt->bindParam(':student_id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Student GetAttendance Error: " . $e->getMessage());
            return [];
        }
    }

    // Get grades for this student
    public function getGrades() {
        try {
            $stmt = $this->conn->prepare("SELECT g.*, s.name AS subject_name, e.name AS exam_name
                                          FROM grades g
                                          JOIN subjects s ON g.subject_id = s.id
                                          JOIN exams e ON g.exam_id = e.id
                                          WHERE g.student_id = :student_id
                                          AND g.academic_year_id = :academic_year_id
                                          AND g.term_id = :term_id
                                          ORDER BY e.exam_date DESC");
            $stmt->bindParam(':student_id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Student GetGrades Error: " . $e->getMessage());
            return [];
        }
    }
}
