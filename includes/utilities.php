<?php
// Form Validation and Sanitization Utilities

class FormValidator {
    private $errors = [];
    private $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    public function validate($rules) {
        foreach ($rules as $field => $rule_list) {
            foreach ($rule_list as $rule) {
                switch ($rule) {
                    case 'required':
                        if (empty($this->data[$field])) {
                            $this->errors[$field][] = "The {$field} field is required.";
                        }
                        break;

                    case 'email':
                        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field][] = "The {$field} must be a valid email address.";
                        }
                        break;

                    case 'min_length:8':
                        if (!empty($this->data[$field]) && strlen($this->data[$field]) < 8) {
                            $this->errors[$field][] = "The {$field} must be at least 8 characters.";
                        }
                        break;

                    case 'file_type:image':
                        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                            if (!in_array($_FILES[$field]['type'], $allowed_types)) {
                                $this->errors[$field][] = "The {$field} must be an image file (JPEG, PNG, or GIF).";
                            }
                        }
                        break;

                    case 'file_size:5':
                        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                            $max_size = 5 * 1024 * 1024; // 5MB
                            if ($_FILES[$field]['size'] > $max_size) {
                                $this->errors[$field][] = "The {$field} must not exceed 5MB.";
                            }
                        }
                        break;
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }

    public function sanitize() {
        $sanitized = [];
        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                // Remove HTML and PHP tags
                $value = strip_tags($value);
                // Convert special characters to HTML entities
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                // Remove leading/trailing whitespace
                $value = trim($value);
            }
            $sanitized[$key] = $value;
        }
        return $sanitized;
    }
}

class FileUploader {
    private $upload_dir;
    private $max_size;
    private $allowed_types;
    private $errors = [];

    public function __construct($upload_dir, $max_size = 5242880, $allowed_types = ['image/jpeg', 'image/png', 'image/gif']) {
        $this->upload_dir = rtrim($upload_dir, '/') . '/';
        $this->max_size = $max_size;
        $this->allowed_types = $allowed_types;
    }

    public function upload($file) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'No file uploaded or upload error occurred.';
            return false;
        }

        // Validate file size
        if ($file['size'] > $this->max_size) {
            $this->errors[] = 'File size exceeds maximum limit.';
            return false;
        }

        // Validate file type
        if (!in_array($file['type'], $this->allowed_types)) {
            $this->errors[] = 'File type not allowed.';
            return false;
        }

        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_extension;

        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0777, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $this->upload_dir . $new_filename)) {
            $this->errors[] = 'Failed to move uploaded file.';
            return false;
        }

        return $new_filename;
    }

    public function getErrors() {
        return $this->errors;
    }
}

class Cookie {
    public static function set($name, $value, $expire = 0, $path = '/', $domain = '', $secure = true, $httponly = true) {
        if ($expire > 0) {
            $expire = time() + $expire;
        }
        
        return setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Strict' // Protect against CSRF
        ]);
    }

    public static function get($name) {
        return $_COOKIE[$name] ?? null;
    }

    public static function delete($name, $path = '/', $domain = '') {
        if (isset($_COOKIE[$name])) {
            unset($_COOKIE[$name]);
            return setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => $path,
                'domain' => $domain
            ]);
        }
        return false;
    }

    public static function exists($name) {
        return isset($_COOKIE[$name]);
    }
}

// CSRF Protection
class CSRF {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Remember Me functionality
function setRememberMeCookie($user_id) {
    $selector = bin2hex(random_bytes(16));
    $authenticator = bin2hex(random_bytes(32));
    $expires = time() + (30 * 24 * 60 * 60); // 30 days

    $hash = password_hash($authenticator, PASSWORD_DEFAULT);
    
    $db = connectDB();
    $stmt = $db->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $selector, $hash, date('Y-m-d H:i:s', $expires)]);

    Cookie::set('remember_me', $selector . ':' . $authenticator, 30 * 24 * 60 * 60);
}

function validateRememberMeCookie() {
    $cookie = Cookie::get('remember_me');
    if (!$cookie) {
        return null;
    }

    list($selector, $authenticator) = explode(':', $cookie);
    
    $db = connectDB();
    $stmt = $db->prepare("
        SELECT auth_tokens.*, users.id as user_id, users.username, users.role
        FROM auth_tokens 
        JOIN users ON auth_tokens.user_id = users.id
        WHERE selector = ? AND expires > NOW()
    ");
    $stmt->execute([$selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token || !password_verify($authenticator, $token['token'])) {
        return null;
    }

    // Update session
    $_SESSION['user_id'] = $token['user_id'];
    $_SESSION['username'] = $token['username'];
    $_SESSION['role'] = $token['role'];

    // Generate new remember me token
    Cookie::delete('remember_me');
    setRememberMeCookie($token['user_id']);

    return $token['user_id'];
}
?>
