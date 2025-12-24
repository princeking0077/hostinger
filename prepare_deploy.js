const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log("Starting Build Process...");

// 1. Build Frontend
try {
    console.log("Building Frontend...");
    execSync('npm run build', { stdio: 'inherit', cwd: path.join(__dirname, '..') });
} catch (e) {
    console.error("Frontend build failed!", e);
    process.exit(1);
}

// 2. Prepare Server Directory
const serverDir = __dirname;
const distDir = path.join(__dirname, '..', 'dist');
const targetBuildDir = path.join(serverDir, 'client_build');

// Clean previous build in server
if (fs.existsSync(targetBuildDir)) {
    fs.rmSync(targetBuildDir, { recursive: true, force: true });
}

// Move dist to server/client_build
try {
    console.log("Moving Build to Server...");
    fs.cpSync(distDir, targetBuildDir, { recursive: true });
} catch (e) {
    console.error("Failed to copy build to server folder:", e);
    process.exit(1);
}

console.log("Build and Copy Complete. Ready for Zipping.");
