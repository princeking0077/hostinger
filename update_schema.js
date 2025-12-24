const mysql = require('mysql2/promise');
require('dotenv').config();

const updateSchema = async () => {
    try {
        const connection = await mysql.createConnection({
            host: process.env.DB_HOST || 'localhost',
            user: process.env.DB_USER || 'root',
            password: process.env.DB_PASSWORD || '',
            database: process.env.DB_NAME || 'pharmacy_db'
        });

        console.log('Connected to database.');

        // 1. Users Table
        await connection.execute(`
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100),
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        `);

        // Ensure columns exist if table already existed
        const addUsersColumn = async (colDef) => {
            try {
                await connection.execute(`ALTER TABLE users ADD COLUMN ${colDef}`);
                console.log(`Added user column: ${colDef}`);
            } catch (e) {
                if (e.code === 'ER_DUP_FIELDNAME') {
                    // console.log(`Column already exists`);
                } else {
                    console.error(`Error adding user column ${colDef}:`, e.message);
                }
            }
        };
        await addUsersColumn("name VARCHAR(100)");
        await addUsersColumn("role VARCHAR(20) DEFAULT 'user'");

        // List Tables
        const [tables] = await connection.query("SHOW TABLES");
        console.log("Current tables:", tables.map(t => Object.values(t)[0]));

        // 2. Content Table
        await connection.execute(`
            CREATE TABLE IF NOT EXISTS content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_id VARCHAR(50) NOT NULL,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(255) UNIQUE,
                type ENUM('animation', 'note', 'video', 'topic') DEFAULT 'topic',
                file_url VARCHAR(500),
                description TEXT,
                blog_content TEXT,
                youtube_id VARCHAR(50),
                quiz_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        `);
        console.log('Verified Content table.');

        const addColumn = async (colDef) => {
            try {
                await connection.execute(`ALTER TABLE content ADD COLUMN ${colDef}`);
                console.log(`Added content column: ${colDef}`);
            } catch (e) {
                if (e.code === 'ER_DUP_FIELDNAME') {
                    // console.log(`Column already exists`);
                } else {
                    console.error(`Error adding content column ${colDef}:`, e.message);
                }
            }
        };

        await addColumn("slug VARCHAR(255) UNIQUE");
        await addColumn("blog_content TEXT");
        await addColumn("youtube_id VARCHAR(50)");
        await addColumn("quiz_data JSON");

        // Fix type column
        try {
            await connection.execute("ALTER TABLE content MODIFY COLUMN type ENUM('animation', 'note', 'video', 'topic') DEFAULT 'topic'");
            console.log("Updated type column.");
        } catch (e) {
            console.error("Error updating type column:", e.message);
        }

        console.log('Schema update complete.');
        await connection.end();
        process.exit(0);

    } catch (error) {
        console.error('Schema update failed:', error);
        process.exit(1);
    }
};

updateSchema();
