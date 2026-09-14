-- 卡密访问控制系统数据库初始化脚本
-- SQLite 3.x

-- 管理员表
CREATE TABLE IF NOT EXISTS admin_user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 卡密批次表
CREATE TABLE IF NOT EXISTS key_batch (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prefix VARCHAR(10) DEFAULT '',
    count INTEGER NOT NULL,
    expire_days INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (created_by) REFERENCES admin_user(id)
);

-- 卡密表
CREATE TABLE IF NOT EXISTS license_key (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER NOT NULL,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    key_encrypted TEXT, -- AES-256-CBC(base64)，用于管理员展示明文卡密
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active/banned/deleted
    expire_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    first_used_at DATETIME,
    last_used_at DATETIME,
    nickname VARCHAR(100),
    FOREIGN KEY (batch_id) REFERENCES key_batch(id)
);

-- 创建索引以提高查询性能
CREATE INDEX IF NOT EXISTS idx_license_key_hash ON license_key(key_hash);
CREATE INDEX IF NOT EXISTS idx_license_key_status ON license_key(status);
CREATE INDEX IF NOT EXISTS idx_license_key_expire ON license_key(expire_at);

-- 访问令牌表
CREATE TABLE IF NOT EXISTS access_token (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    key_id INTEGER NOT NULL,
    expire_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    last_seen_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    ip_hash VARCHAR(64),
    ua_hash VARCHAR(64),
    FOREIGN KEY (key_id) REFERENCES license_key(id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_access_token_hash ON access_token(token_hash);
CREATE INDEX IF NOT EXISTS idx_access_token_expire ON access_token(expire_at);
CREATE INDEX IF NOT EXISTS idx_access_token_key_id ON access_token(key_id);

-- 卡密使用日志表
CREATE TABLE IF NOT EXISTS key_usage_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_id INTEGER,
    action VARCHAR(50) NOT NULL, -- verify/ping/access
    ip VARCHAR(45),
    user_agent TEXT,
    result VARCHAR(20) NOT NULL, -- success/failed
    reason VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (key_id) REFERENCES license_key(id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_key_usage_log_key_id ON key_usage_log(key_id);
CREATE INDEX IF NOT EXISTS idx_key_usage_log_created_at ON key_usage_log(created_at);

-- 管理员操作日志表
CREATE TABLE IF NOT EXISTS admin_op_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (admin_id) REFERENCES admin_user(id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_admin_op_log_admin_id ON admin_op_log(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_op_log_created_at ON admin_op_log(created_at);

-- 系统配置表
CREATE TABLE IF NOT EXISTS system_config (
    key VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 问题类型表
CREATE TABLE IF NOT EXISTS problem_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 模板表
CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_type_id INTEGER NOT NULL,
    template_type VARCHAR(20) NOT NULL, -- SLOGAN/APPLICATION
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (problem_type_id) REFERENCES problem_types(id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_templates_problem_type ON templates(problem_type_id);
CREATE INDEX IF NOT EXISTS idx_templates_type ON templates(template_type);

-- 防爆破临时表（用于记录尝试次数）
CREATE TABLE IF NOT EXISTS rate_limit (
    ip_hash VARCHAR(64) PRIMARY KEY,
    attempt_count INTEGER NOT NULL DEFAULT 1,
    first_attempt_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
    last_attempt_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_rate_limit_last_attempt ON rate_limit(last_attempt_at);
