const express = require('express');
const router = express.Router();
const multer = require('multer');
const path = require('path');

const authenticateToken = require('../middleware/auth.middleware');

// Configure Multer Storage
const storage = multer.diskStorage({
    destination: './uploads/',
    filename: function (req, file, cb) {
        cb(null, 'file-' + Date.now() + path.extname(file.originalname));
    }
});

const upload = multer({
    storage: storage,
    limits: { fileSize: 15000000 }, // 15MB limit
    fileFilter: function (req, file, cb) {
        checkFileType(file, cb);
    }
});

// Check File Type
function checkFileType(file, cb) {
    const filetypes = /jpeg|jpg|png|gif|pdf|html|css|js/;
    const extname = filetypes.test(path.extname(file.originalname).toLowerCase());
    const mimetype = filetypes.test(file.mimetype);

    // Note: Node's filetypes.test is strict, if issues arise might need to relax like PHP
    if (mimetype && extname) {
        return cb(null, true);
    } else {
        cb('Error: Invalid file type!');
    }
}

// Upload Endpoint
router.post('/', authenticateToken, upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ message: "No file uploaded" });
    }

    // Return relative URL to match PHP behavior: /uploads/filename
    const fileUrl = `/uploads/${req.file.filename}`;

    res.json({
        message: "File uploaded successfully",
        url: fileUrl
    });
});

module.exports = router;
