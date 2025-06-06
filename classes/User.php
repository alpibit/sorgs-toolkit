<?php
class User
{
    private $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = new Database();
        }
        $this->db = $db->connect();
    }

    public function login($username, $password)
    {
        $sql = "SELECT * FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    }

    public function logout()
    {
        session_unset();
        session_destroy();
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function getUser($userId)
    {
        $sql = "SELECT id, username, email, role FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUser($userId, $data)
    {
        $sql = "UPDATE users SET username = :username, email = :email WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $userId,
            ':username' => $data['username'],
            ':email' => $data['email']
        ]);
    }

    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $userId,
            ':password' => $hashedPassword
        ]);
    }

    private function getClientIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    private function getLoginAttemptsKey()
    {
        return 'login_attempts_' . hash('sha256', $this->getClientIp());
    }

    public function isLoginBlocked()
    {
        $key = $this->getLoginAttemptsKey();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => time(),
                'last_attempt' => time()
            ];
        }

        if (time() - $_SESSION[$key]['first_attempt'] > 900) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => time(),
                'last_attempt' => time()
            ];
            return false;
        }

        return $_SESSION[$key]['count'] >= 5;
    }

    public function trackFailedLogin()
    {
        $key = $this->getLoginAttemptsKey();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => time(),
                'last_attempt' => time()
            ];
        }

        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = time();
    }

    public function resetLoginAttempts()
    {
        $key = $this->getLoginAttemptsKey();
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time(),
            'last_attempt' => time()
        ];
    }
}
