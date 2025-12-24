const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const helmet = require('helmet');
const compression = require('compression');
const morgan = require('morgan');
require('dotenv').config();

console.log("----- SERVER STARTING -----");
console.log(`Environment: ${process.env.NODE_ENV}`);
console.log(`Port: ${process.env.PORT}`);

// Global Error Handlers to prevent silent crashes
process.on('uncaughtException', (err) => {
    console.error('UNCAUGHT EXCEPTION:', err);
    // Keep running if possible, or exit with 1
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('UNHANDLED REJECTION:', reason);
});

// Create uploads directory if not exists
const uploadDir = path.join(__dirname, 'uploads');
if (!fs.existsSync(uploadDir)) {
    fs.mkdirSync(uploadDir);
}

const app = express();

// Middleware
app.use(helmet({
    crossOriginResourcePolicy: { policy: "cross-origin" } // Allow serving images/files
}));
app.use(compression()); // Compress responses
app.use(morgan('combined')); // Logging
app.use(cors()); // Configure specific origins in production if needed
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve Uploads Static Folder
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// Routes (Importing later)
app.use('/api/auth', require('./routes/auth.routes'));
app.use('/api/content', require('./routes/content.routes'));
app.use('/api/upload', require('./routes/upload.routes'));
app.use('/api/settings', require('./routes/settings.routes'));
app.use('/', require('./routes/ads.routes')); // Serve /ads.txt at root level

// Health Check
app.get('/api/health', (req, res) => {
    res.json({ message: "Pharma Server Running" });
});

// Serve React Frontend (Production)
// We assume the build output is in 'client_build' directory
const buildPath = path.join(__dirname, 'client_build');
if (fs.existsSync(buildPath)) {
    app.use(express.static(buildPath));
    app.get('*', (req, res) => {
        res.sendFile(path.join(buildPath, 'index.html'));
    });
}

// Start Server
const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});
