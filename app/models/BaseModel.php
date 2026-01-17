<?php
require_once __DIR__ . '/../config/database.php';

class BaseModel {
    protected $conn;
    protected $logs_table = 'logs';

    public $user_id;
    public $academic_year_id;
    public $term_id;

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }

        $this->user_id = $user_id;
        $this->academic_year_id = $academic_year_id;
        $this->term_id = $term_id;
    }

    /**
     * Logs an action performed by the current user
     *
     * @param string $action Description of the action
     * @param string|null $description Optional detailed info
     */
    protected function logAction(string $action, ?string $description = null) {
        if (!$this->user_id) return;

        try {
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
        } catch (PDOException $e) {
            error_log("BaseModel Log Error: " . $e->getMessage());
        }
    }
}
