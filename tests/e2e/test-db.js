const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const repoRoot = path.resolve(__dirname, '../..');
const dbPath = path.join(repoRoot, 'data', 'app.db');
const initSqlPath = path.join(repoRoot, 'backend', 'migrations', 'init.sql');
const seedSqlPath = path.join(repoRoot, 'backend', 'migrations', 'seed.sql');

const clearSql = `
PRAGMA foreign_keys = OFF;
DELETE FROM access_token;
DELETE FROM key_usage_log;
DELETE FROM admin_op_log;
DELETE FROM license_key;
DELETE FROM key_batch;
DELETE FROM admin_user;
DELETE FROM system_config;
DELETE FROM problem_types;
DELETE FROM templates;
DELETE FROM rate_limit;
DELETE FROM sqlite_sequence;
PRAGMA foreign_keys = ON;
`;

function execSql(sql) {
  execFileSync('sqlite3', [dbPath], {
    input: sql,
    stdio: ['pipe', 'ignore', 'pipe']
  });
}

function applySqlFile(filePath) {
  execSql(fs.readFileSync(filePath, 'utf8'));
}

function resetTestDatabase() {
  fs.mkdirSync(path.dirname(dbPath), { recursive: true });
  applySqlFile(initSqlPath);
  execSql(clearSql);
  applySqlFile(seedSqlPath);
}

module.exports = {
  resetTestDatabase,
};
