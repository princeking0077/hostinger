const pool = require('./db');
const fs = require('fs');

const runMigration = async () => {
    try {
        console.log("Starting Migration...");

        // 1. Add 'slug' column to topics if not exists
        try {
            await pool.query("ALTER TABLE topics ADD COLUMN slug VARCHAR(255) UNIQUE");
            console.log("Added 'slug' column to topics.");
        } catch (e) {
            if (e.code === 'ER_DUP_FIELDNAME') {
                console.log("'slug' column already exists.");
            } else {
                console.error("Error adding slug column:", e);
            }
        }

        // 2. Add AdSense fields to settings if not exists
        const settingsFields = [
            "ADD COLUMN adsense_code TEXT",
            "ADD COLUMN ads_txt TEXT",
            "ADD COLUMN ad_rpm_head VARCHAR(255)",
            "ADD COLUMN ad_rpm_body VARCHAR(255)"
        ];

        for (const field of settingsFields) {
            try {
                await pool.query(`ALTER TABLE settings ${field}`);
                console.log(`Executed: ALTER TABLE settings ${field}`);
            } catch (e) {
                if (e.code === 'ER_DUP_FIELDNAME') {
                    // ignore
                } else {
                    console.error("Error altering settings:", e);
                }
            }
        }

        // 3. Backfill Slugs
        console.log("Backfilling slugs...");
        const [topics] = await pool.query("SELECT id, title, slug FROM topics");

        for (const topic of topics) {
            if (!topic.slug) {
                const slug = topic.title
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-') // Replace non-alphanumeric with hyphens
                    .replace(/^-+|-+$/g, '');   // Remove leading/trailing hyphens

                // Ensure uniqueness (simple append id if needed, though rare for backfill)
                await pool.query("UPDATE topics SET slug = ? WHERE id = ?", [slug, topic.id]);
                console.log(`Generated slug '${slug}' for topic '${topic.title}'`);
            }
        }

        console.log("Migration Complete!");
        process.exit(0);

    } catch (e) {
        console.error("Migration Failed:", e);
        process.exit(1);
    }
};

runMigration();
