const express = require('express');
const router = express.Router();
const pool = require('../db');
const authenticateToken = require('../middleware/auth.middleware');

// Get content by Subject ID
router.get('/', async (req, res) => {
    const { subject } = req.query;
    if (!subject) return res.status(400).json({ message: "Subject ID required" });

    try {
        const [rows] = await pool.query("SELECT * FROM content WHERE subject_id = ? ORDER BY created_at DESC", [subject]);
        res.json(rows);
    } catch (error) {
        res.status(500).json({ message: "Database Error", error });
    }
});

// Search Topics Global (Matches search.php logic)
router.get('/search', async (req, res) => {
    const { q } = req.query;
    if (!q) return res.json([]);

    try {
        // PHP Logic: title LIKE :q OR subject_id LIKE :q OR description LIKE :q OR blog_content LIKE :q
        const query = `
            SELECT id, subject_id, title, slug, description, 'topic' as type 
            FROM content 
            WHERE title LIKE ? 
               OR subject_id LIKE ? 
               OR description LIKE ? 
               OR blog_content LIKE ? 
            ORDER BY created_at DESC 
            LIMIT 20
        `;
        const searchTerm = `%${q}%`;
        const [rows] = await pool.query(query, [searchTerm, searchTerm, searchTerm, searchTerm]);

        // Ensure slug exists (PHP fallback logic handled here by assumed DB column or default)
        const results = rows.map(row => ({
            ...row,
            id: String(row.id),
            subject_id: String(row.subject_id),
            slug: row.slug || row.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
        }));

        res.json(results);
    } catch (error) {
        console.error(error);
        res.status(500).json({ message: "Search Error", error });
    }
});

// Get Single Topic by Slug
router.get('/:subjectSlug/:topicSlug', async (req, res) => {
    const { subjectSlug, topicSlug } = req.params;
    try {
        const [rows] = await pool.query("SELECT * FROM content WHERE slug = ? AND subject_id = ?", [topicSlug, subjectSlug]);
        if (rows.length === 0) return res.status(404).json({ message: "Topic not found" });
        res.json(rows[0]);
    } catch (error) {
        res.status(500).json({ message: "Error fetching topic" });
    }
});

// Helper: Generate Slug
const generateSlug = (text) => {
    return text.toString().toLowerCase()
        .replace(/\s+/g, '-')           // Replace spaces with -
        .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
        .replace(/\-\-+/g, '-')         // Replace multiple - with single -
        .replace(/^-+/, '')             // Trim - from start
        .replace(/-+$/, '');            // Trim - from end
};

// Create Topic
router.post('/', authenticateToken, async (req, res) => {
    const { subjectId, title, description, blogContent, youtubeId, fileUrl, quiz } = req.body;
    let slug = generateSlug(title);

    try {
        // Check uniqueness
        const [exists] = await pool.query("SELECT id FROM content WHERE slug = ?", [slug]);
        if (exists.length > 0) {
            slug = `${slug}-${Date.now()}`;
        }

        await pool.query(
            "INSERT INTO content (subject_id, title, slug, description, blog_content, youtube_id, file_url, quiz_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [subjectId, title, slug, description, blogContent, youtubeId, fileUrl, JSON.stringify(quiz)]
        );
        res.json({ message: "Topic created successfully", slug });
    } catch (error) {
        console.error(error);
        res.status(500).json({ message: "Failed to create topic" });
    }
});

// Update Topic (Matches update_topic.php)
router.put('/', authenticateToken, async (req, res) => {
    const { id, title, description, blogContent, youtubeId, fileUrl, quiz } = req.body;

    if (!id || !title) {
        return res.status(400).json({ message: "Incomplete data." });
    }

    try {
        let sql = "UPDATE content SET title = ?, description = ?, blog_content = ?, youtube_id = ?, quiz_data = ?";
        const params = [title, description, blogContent, youtubeId, JSON.stringify(quiz)];

        if (fileUrl !== undefined) {
            sql += ", file_url = ?";
            params.push(fileUrl);
        }

        sql += " WHERE id = ?";
        params.push(id);

        await pool.query(sql, params);
        res.json({ message: "Topic updated successfully" });
    } catch (error) {
        console.error(error);
        res.status(500).json({ message: "Failed to update topic" });
    }
});

// Delete Topic
router.delete('/:id', authenticateToken, async (req, res) => {
    const { id } = req.params;
    try {
        await pool.query("DELETE FROM content WHERE id = ?", [id]);
        res.json({ message: "Topic deleted successfully" });
    } catch (error) {
        res.status(500).json({ message: "Failed to delete topic" });
    }
});

module.exports = router;
