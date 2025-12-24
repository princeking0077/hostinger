const express = require('express');
const router = express.Router();
const pool = require('../db');

// Get Settings
router.get('/', async (req, res) => {
    try {
        const [rows] = await pool.query("SELECT * FROM settings");
        const settings = {};
        rows.forEach(r => {
            settings[r.setting_key] = r.setting_value;
        });
        res.json(settings);
    } catch (error) {
        res.status(500).json({ message: "Error fetching settings" });
    }
});

const authenticateToken = require('../middleware/auth.middleware');

// Save Settings
router.post('/', authenticateToken, async (req, res) => {
    const settings = req.body;
    try {
        for (const [key, value] of Object.entries(settings)) {
            await pool.query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                [key, value, value]
            );
        }
        res.json({ message: "Settings saved" });
    } catch (error) {
        console.error(error);
        res.status(500).json({ message: "Error saving settings" });
    }
});

module.exports = router;
