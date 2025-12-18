const express = require('express');
const cors = require('cors');
const { createClient } = require('@supabase/supabase-js');
require('dotenv').config();

const app = express();
const PORT = 3001;

app.use(cors());
app.use(express.json());

const supabaseUrl = process.env.VITE_SUPABASE_URL;
const supabaseKey = process.env.VITE_SUPABASE_ANON_KEY;
const supabase = createClient(supabaseUrl, supabaseKey);

app.post('/api/query', async (req, res) => {
    try {
        const { sql, params } = req.body;

        if (!sql) {
            return res.status(400).json({ error: 'SQL query is required' });
        }

        let query = sql;
        if (params && params.length > 0) {
            params.forEach((param, index) => {
                const placeholder = `$${index + 1}`;
                query = query.replace('?', placeholder);
            });
        }

        const { data, error } = await supabase.rpc('exec_sql', { query_text: query });

        if (error) {
            console.error('Supabase error:', error);
            return res.status(500).json({ error: error.message });
        }

        res.json({ success: true, data });
    } catch (error) {
        console.error('Query error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/select', async (req, res) => {
    try {
        const { table, columns, where, limit, offset } = req.body;

        let query = supabase.from(table).select(columns || '*');

        if (where) {
            Object.entries(where).forEach(([key, value]) => {
                query = query.eq(key, value);
            });
        }

        if (limit) query = query.limit(limit);
        if (offset) query = query.range(offset, offset + (limit || 10) - 1);

        const { data, error } = await query;

        if (error) {
            console.error('Select error:', error);
            return res.status(500).json({ error: error.message });
        }

        res.json({ success: true, data });
    } catch (error) {
        console.error('Select error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/insert', async (req, res) => {
    try {
        const { table, data: insertData } = req.body;

        const { data, error } = await supabase
            .from(table)
            .insert(insertData)
            .select();

        if (error) {
            console.error('Insert error:', error);
            return res.status(500).json({ error: error.message });
        }

        res.json({ success: true, data });
    } catch (error) {
        console.error('Insert error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/update', async (req, res) => {
    try {
        const { table, data: updateData, where } = req.body;

        let query = supabase.from(table).update(updateData);

        if (where) {
            Object.entries(where).forEach(([key, value]) => {
                query = query.eq(key, value);
            });
        }

        const { data, error } = await query.select();

        if (error) {
            console.error('Update error:', error);
            return res.status(500).json({ error: error.message });
        }

        res.json({ success: true, data });
    } catch (error) {
        console.error('Update error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/delete', async (req, res) => {
    try {
        const { table, where } = req.body;

        let query = supabase.from(table).delete();

        if (where) {
            Object.entries(where).forEach(([key, value]) => {
                query = query.eq(key, value);
            });
        }

        const { data, error } = await query;

        if (error) {
            console.error('Delete error:', error);
            return res.status(500).json({ error: error.message });
        }

        res.json({ success: true, data });
    } catch (error) {
        console.error('Delete error:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', message: 'API server is running' });
});

app.listen(PORT, () => {
    console.log(`API server running on http://localhost:${PORT}`);
    console.log(`Supabase URL: ${supabaseUrl}`);
});
