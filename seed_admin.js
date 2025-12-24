const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
require('dotenv').config();

const seedAdmin = async () => {
    try {
        const connection = await mysql.createConnection({
            host: process.env.DB_HOST || 'localhost',
            user: process.env.DB_USER || 'root',
            password: process.env.DB_PASSWORD || '',
            database: process.env.DB_NAME || 'pharmacy_db'
        });

        console.log('Connected to database.');

        const email = 'shoaib.ss300@gmail.com';
        const rawPassword = 'Shaikh@#$001';
        const hashedPassword = await bcrypt.hash(rawPassword, 10);

        // Check if user exists
        const [rows] = await connection.execute('SELECT * FROM users WHERE email = ?', [email]);

        if (rows.length > 0) {
            console.log('Admin user already exists. Updating password...');
            await connection.execute('UPDATE users SET password = ?, role = ? WHERE email = ?', [hashedPassword, 'admin', email]);
            console.log('Password updated.');
        } else {
            console.log('Creating admin user...');
            await connection.execute('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)', ['Shoaib Shaikh', email, hashedPassword, 'admin']);
            console.log('Admin user created.');
        }

        await connection.end();
        process.exit(0);

    } catch (error) {
        console.error('Error seeding admin:', error);
        process.exit(1);
    }
};

seedAdmin();
