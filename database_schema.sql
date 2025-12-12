--Database Name = todo_db
-- 1. USERS TABLE (Enhanced Security & DATETIME Precision)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    -- REMOVED: encrypted_email VARBINARY(255) COMMENT 'Encrypted email for privacy',

    -- Password Security (CRITICAL)
    password_hash VARCHAR(255) NOT NULL COMMENT 'Argon2id or BCrypt hash',
    password_salt VARCHAR(32) NOT NULL COMMENT 'Unique salt per user',
    password_changed_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    last_password_reset DATETIME(6) NULL,

    -- Account Security
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME(6) NULL,
    is_active BOOLEAN DEFAULT TRUE,

    -- Email Verification (Token length increased)
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL COMMENT 'Hashed email verification token',

    -- Password Reset (Token length increased)
    reset_token VARCHAR(255) NULL COMMENT 'Hashed password reset token',
    reset_token_expires DATETIME(6) NULL,

    -- Two-Factor Authentication
    two_factor_secret VARCHAR(255) NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,

    -- Timestamps
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    last_login DATETIME(6) NULL,

    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_failed_logins (failed_login_attempts, is_active),
    INDEX idx_locked_users (locked_until, is_active),
    INDEX idx_tokens (reset_token, reset_token_expires),
    INDEX idx_verification (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. SESSIONS TABLE (Session Management - FIXED)

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY COMMENT 'Unique session identifier (stored in cookie)',
    user_id INT NOT NULL,
    session_token_hash VARCHAR(255) NOT NULL COMMENT 'Hashed session token (SHA-256 or BLAKE2)',
    expires_at DATETIME(6) NOT NULL,
    ip_address VARCHAR(45) COMMENT 'Supports IPv4 and IPv6',
    user_agent TEXT,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    last_activity DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity),
    INDEX idx_token_hash (session_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. LOGIN ATTEMPTS TABLE (Brute Force Protection)
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    successful BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(100) COMMENT 'invalid_password, account_locked, etc.',
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ip (ip_address),
    INDEX idx_user_email (user_id, email),
    INDEX idx_created_at (created_at),
    INDEX idx_successful (successful, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TODOS TABLE (Enhanced with Audit Fields)
CREATE TABLE todos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    task TEXT NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'archived') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATETIME NULL,
    completed_at DATETIME(6) NULL,

    -- Audit Fields
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_created_at (created_at),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. CATEGORIES TABLE
-- ============================================================================
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, name),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. TODO CATEGORIES (Many-to-Many)
CREATE TABLE todo_categories (
    todo_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),

    PRIMARY KEY (todo_id, category_id),
    FOREIGN KEY (todo_id) REFERENCES todos(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. TAGS TABLE

CREATE TABLE tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tag (user_id, name),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 10. TODO TAGS (Many-to-Many)

CREATE TABLE todo_tags (
    todo_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),

    PRIMARY KEY (todo_id, tag_id),
    FOREIGN KEY (todo_id) REFERENCES todos(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 11. SUBTASKS TABLE

CREATE TABLE subtasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    todo_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    position INT DEFAULT 0,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),

    FOREIGN KEY (todo_id) REFERENCES todos(id) ON DELETE CASCADE,
    INDEX idx_todo_id (todo_id),
    INDEX idx_position (todo_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 12. ACTIVITY LOG (Enhanced Audit Trail)
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    todo_id INT NULL,

    -- Action Details
    action VARCHAR(50) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, LOGIN, etc.',
    details TEXT,

    -- What Changed
    affected_record_id INT COMMENT 'ID of the affected record',
    old_values JSON COMMENT 'Previous state for UPDATE actions',
    new_values JSON COMMENT 'New state for CREATE/UPDATE actions',

    -- Security Context
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),

    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (todo_id) REFERENCES todos(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_todo_id (todo_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;