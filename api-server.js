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

app.post('/api/auth/login', async (req, res) => {
    try {
        const { username, password } = req.body;

        const { data, error } = await supabase
            .from('users')
            .select('*')
            .eq('username', username)
            .maybeSingle();

        if (error) {
            return res.status(500).json({ success: false, message: 'خطا در بررسی اطلاعات' });
        }

        if (!data) {
            return res.json({ success: false, message: 'نام کاربری یا رمز عبور اشتباه است' });
        }

        if (data.password !== password) {
            return res.json({ success: false, message: 'نام کاربری یا رمز عبور اشتباه است' });
        }

        const fullName = `${data.first_name || ''} ${data.last_name || ''}`.trim() || data.username;

        const user = {
            id: data.id,
            username: data.username,
            full_name: fullName,
            email: data.email,
            role: data.role
        };

        res.json({ success: true, user });
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ success: false, message: 'خطا در سرور' });
    }
});

app.get('/api/customers', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('customers')
            .select('*')
            .order('created_at', { ascending: false });

        if (error) throw error;
        res.json(data || []);
    } catch (error) {
        console.error('Error fetching customers:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/customers/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('customers')
            .select('*')
            .eq('id', req.params.id)
            .maybeSingle();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error fetching customer:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/customers', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('customers')
            .insert(req.body)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error creating customer:', error);
        res.status(500).json({ error: error.message });
    }
});

app.put('/api/customers/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('customers')
            .update(req.body)
            .eq('id', req.params.id)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error updating customer:', error);
        res.status(500).json({ error: error.message });
    }
});

app.delete('/api/customers/:id', async (req, res) => {
    try {
        const { error } = await supabase
            .from('customers')
            .delete()
            .eq('id', req.params.id);

        if (error) throw error;
        res.json({ success: true });
    } catch (error) {
        console.error('Error deleting customer:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/products', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('products')
            .select('*')
            .order('created_at', { ascending: false });

        if (error) throw error;
        res.json(data || []);
    } catch (error) {
        console.error('Error fetching products:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/products/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('products')
            .select('*')
            .eq('id', req.params.id)
            .maybeSingle();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error fetching product:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/products', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('products')
            .insert(req.body)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error creating product:', error);
        res.status(500).json({ error: error.message });
    }
});

app.put('/api/products/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('products')
            .update(req.body)
            .eq('id', req.params.id)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error updating product:', error);
        res.status(500).json({ error: error.message });
    }
});

app.delete('/api/products/:id', async (req, res) => {
    try {
        const { error } = await supabase
            .from('products')
            .delete()
            .eq('id', req.params.id);

        if (error) throw error;
        res.json({ success: true });
    } catch (error) {
        console.error('Error deleting product:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/sales', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('sales')
            .select(`
                *,
                customer:customers(name)
            `)
            .order('sale_date', { ascending: false });

        if (error) throw error;

        const salesWithCustomerName = (data || []).map(sale => ({
            ...sale,
            customer_name: sale.customer?.name || ''
        }));

        res.json(salesWithCustomerName);
    } catch (error) {
        console.error('Error fetching sales:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/sales/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('sales')
            .select('*')
            .eq('id', req.params.id)
            .maybeSingle();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error fetching sale:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/sales', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('sales')
            .insert(req.body)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error creating sale:', error);
        res.status(500).json({ error: error.message });
    }
});

app.put('/api/sales/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('sales')
            .update(req.body)
            .eq('id', req.params.id)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error updating sale:', error);
        res.status(500).json({ error: error.message });
    }
});

app.delete('/api/sales/:id', async (req, res) => {
    try {
        const { error } = await supabase
            .from('sales')
            .delete()
            .eq('id', req.params.id);

        if (error) throw error;
        res.json({ success: true });
    } catch (error) {
        console.error('Error deleting sale:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/tasks', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('tasks')
            .select('*')
            .order('due_date', { ascending: true });

        if (error) throw error;
        res.json(data || []);
    } catch (error) {
        console.error('Error fetching tasks:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/tasks/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('tasks')
            .select('*')
            .eq('id', req.params.id)
            .maybeSingle();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error fetching task:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/tasks', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('tasks')
            .insert(req.body)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error creating task:', error);
        res.status(500).json({ error: error.message });
    }
});

app.put('/api/tasks/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('tasks')
            .update(req.body)
            .eq('id', req.params.id)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error updating task:', error);
        res.status(500).json({ error: error.message });
    }
});

app.delete('/api/tasks/:id', async (req, res) => {
    try {
        const { error } = await supabase
            .from('tasks')
            .delete()
            .eq('id', req.params.id);

        if (error) throw error;
        res.json({ success: true });
    } catch (error) {
        console.error('Error deleting task:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/leads', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('leads')
            .select('*')
            .order('created_at', { ascending: false });

        if (error) throw error;
        res.json(data || []);
    } catch (error) {
        console.error('Error fetching leads:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/leads/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('leads')
            .select('*')
            .eq('id', req.params.id)
            .maybeSingle();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error fetching lead:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/leads', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('leads')
            .insert(req.body)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error creating lead:', error);
        res.status(500).json({ error: error.message });
    }
});

app.put('/api/leads/:id', async (req, res) => {
    try {
        const { data, error } = await supabase
            .from('leads')
            .update(req.body)
            .eq('id', req.params.id)
            .select()
            .single();

        if (error) throw error;
        res.json(data);
    } catch (error) {
        console.error('Error updating lead:', error);
        res.status(500).json({ error: error.message });
    }
});

app.delete('/api/leads/:id', async (req, res) => {
    try {
        const { error } = await supabase
            .from('leads')
            .delete()
            .eq('id', req.params.id);

        if (error) throw error;
        res.json({ success: true });
    } catch (error) {
        console.error('Error deleting lead:', error);
        res.status(500).json({ error: error.message });
    }
});

app.listen(PORT, () => {
    console.log(`API server running on http://localhost:${PORT}`);
    console.log(`Supabase URL: ${supabaseUrl}`);
});
