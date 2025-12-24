const express = require('express');
const cors = require('cors');
const path = require('path');
// const pool = require('./db'); // DISABLED FOR SAFE MODE
require('dotenv').config();

console.log("----- SAFE MODE SERVER STARTING -----");

const app = express();
app.use(cors());

// SIMPLE SAFE ROUTE
app.get('/api/ping', (req, res) => {
    res.json({ status: "SAFE_MODE_ACTIVE", message: "Server is running! DB is disabled." });
});

/* DISABLED CAUSING CRASH?
app.use('/api/auth', require('./routes/auth.routes'));
app.use('/api/content', require('./routes/content.routes'));
app.use('/api/upload', require('./routes/upload.routes'));
app.use('/api/settings', require('./routes/settings.routes'));
*/

// Serve React Frontend (Production)
// We assume the build output is in 'client_build' directory
const buildPath = path.join(__dirname, 'client_build');
if (require('fs').existsSync(buildPath)) {
    app.use(express.static(buildPath));
    app.get('*', (req, res) => {
        res.sendFile(path.join(buildPath, 'index.html'));
    });
}

const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
    console.log(`SAFE MODE Server running on port ${PORT}`);
});

/*
// Debug Route (Check Node.js DB Connection)
app.get('/api/debug-status', async (req, res) => { ... });
*/


