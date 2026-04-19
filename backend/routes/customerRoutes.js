const express = require('express');
const router = express.Router();
const Customer = require('../models/Customer');
const auth = require('../middleware/auth');

// Get all customers for the company
router.get('/', auth, async (req, res) => {
    try {
        const targetCompanyId = req.query.companyId || (req.user && req.user.companyId);
        const customers = await Customer.find({ company_id: targetCompanyId });
        res.json(customers);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Create customer
router.post('/', auth, async (req, res) => {
    try {
        const customer = new Customer({
            ...req.body,
            company_id: req.body.company_id || req.user.companyId
        });
        await customer.save();
        res.status(201).json(customer);
    } catch (err) {
        res.status(400).json({ error: err.message });
    }
});

// Update customer
router.put('/:id', auth, async (req, res) => {
    try {
        const customer = await Customer.findByIdAndUpdate(req.params.id, req.body, { new: true });
        res.json(customer);
    } catch (err) {
        res.status(400).json({ error: err.message });
    }
});

// Delete customer
router.delete('/:id', auth, async (req, res) => {
    try {
        await Customer.findByIdAndDelete(req.params.id);
        res.json({ message: 'Customer deleted successfully' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

module.exports = router;
