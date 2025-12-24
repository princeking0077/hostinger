const mysql = require('mysql2/promise');
require('dotenv').config();
const bcrypt = require('bcrypt');

async function setup() {
    console.log("Starting Database Setup...");

    // Connect without database first to create it
    const connection = await mysql.createConnection({
        host: process.env.DB_HOST,
        user: process.env.DB_USER,
        password: process.env.DB_PASS
    });

    try {
        await connection.query(`CREATE DATABASE IF NOT EXISTS \`${process.env.DB_NAME}\``);
        console.log(`Database '${process.env.DB_NAME}' checked/created.`);

        await connection.end();

        // Connect to database
        const db = await mysql.createPool({
            host: process.env.DB_HOST,
            user: process.env.DB_USER,
            password: process.env.DB_PASS,
            database: process.env.DB_NAME
        });

        // 1. Users Table
        await db.query(`
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);
        console.log("Users table created.");

        // 2. Settings Table
        await db.query(`
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        `);
        console.log("Settings table created.");

        // 3. Topics Table (Content)
        await db.query(`
            CREATE TABLE IF NOT EXISTS topics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_id VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT, /* Animation Code */
                blog_content LONGTEXT, /* Rich Text Blog */
                youtube_id VARCHAR(50),
                file_url VARCHAR(255),
                quiz_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);
        console.log("Topics table created.");

        // Create Default Admin User
        const [rows] = await db.query("SELECT * FROM users WHERE email = 'admin@apexapps.in'");
        if (rows.length === 0) {
            const hashedPassword = await bcrypt.hash('admin123', 10);
            await db.query("INSERT INTO users (email, password) VALUES (?, ?)", ['admin@apexapps.in', hashedPassword]);
            console.log("Default Admin User Created (admin@apexapps.in / admin123)");
        } else {
            console.log("Admin user already exists.");
        }

        console.log("✅ Setup Complete!");
        process.exit();

    } catch (error) {
        console.error("Setup Failed:", error);
        process.exit(1);
    }
}

setup();
