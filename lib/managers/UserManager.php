<?php
declare(strict_types=1);

/**
 * Handles user-facing auth/session helpers.
 */
class UserManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function register(string $username, string $email, string $password): array
    {
        $username = trim($username);
        $email = trim($email);
        if ($username === '' || $email === '' || $password === '') {
            return ['success' => false, 'message' => 'All fields are required.'];
        }

        // Check duplicates
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Registration unavailable.'];
        }
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            return ['success' => false, 'message' => 'Username or email already taken.'];
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password, created_at, last_activity_at) VALUES (?, ?, ?, NOW(), NOW())");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Registration unavailable.'];
        }
        $stmt->bind_param("sss", $username, $email, $hashed);
        $ok = $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        return $ok ? ['success' => true, 'user_id' => $userId] : ['success' => false, 'message' => 'Could not create user.'];
    }

    public function login(string $username, string $password): array
    {
        $stmt = $this->conn->prepare("SELECT id, password, is_banned FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Login unavailable.'];
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }
        if ((int)$row['is_banned'] === 1) {
            return ['success' => false, 'message' => 'Account is banned.'];
        }
        if (!password_verify($password, $row['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        $this->touchLastActivity((int)$row['id']);
        return ['success' => true, 'user_id' => (int)$row['id']];
    }

    public function getUserByUsername(string $username): ?array
    {
        $stmt = $this->conn->prepare("SELECT id, username, email FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getUserById(int $userId): ?array
    {
        $stmt = $this->conn->prepare("SELECT id, username, email, is_admin, is_banned, ally_id, points FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function touchLastActivity(int $userId): void
    {
        if (!dbColumnExists($this->conn, 'users', 'last_activity_at')) {
            return;
        }
        $stmt = $this->conn->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
