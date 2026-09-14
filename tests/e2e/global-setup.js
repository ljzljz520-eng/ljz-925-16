const { resetTestDatabase } = require('./test-db');

async function globalSetup() {
  resetTestDatabase();
}

module.exports = globalSetup;
