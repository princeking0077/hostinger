const express = require('express');
const router = express.Router();
const pool = require('../db');

router.get('/ads.txt', async (req, res) => {
    try {
        const [rows] = await pool.query("SELECT setting_value FROM settings WHERE setting_key = 'ads_txt'");
        if (rows.length > 0 && rows[0].setting_value) {
            res.header('Content-Type', 'text/plain');
            res.send(rows[0].setting_value);
        } else {
            res.status(404).send("No ads.txt content configured.");
        }
    } catch (error) {
        res.status(500).send("Server Error");
    }
});

module.exports = router;
