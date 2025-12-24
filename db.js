const mysql = require('mysql2/promise');
require('dotenv').config();

const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASS,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// Test Connection Immediately
pool.getConnection()
    .then(connection => {
        console.log('DATABASE CONNECTED SUCCESSFULLY');
        connection.release();
    })
    .catch(err => {
        console.error('DATABASE CONNECTION FAILED:', err.message);
        console.error('Check your DB_HOST, DB_USER, DB_PASS in environment variables.');
    });

module.exports = pool;
