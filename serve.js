const express = require('express');
const path = require('path');

const app = express();
const PORT = 8000;

app.use(express.static(path.join(__dirname, 'public')));

app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.listen(PORT, () => {
    console.log(`Web server running on http://localhost:${PORT}`);
    console.log(`Open your browser and go to: http://localhost:${PORT}`);
});
