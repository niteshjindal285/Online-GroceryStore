const express = require('express');
const router = express.Router();
const Invoice = require('../models/Invoice');
const Product = require('../models/Product');
const auth = require('../middleware/auth');

// Get all invoices for the company
router.get('/', auth, async (req, res) => {
    try {
        const targetCompanyId = req.query.companyId || (req.user && req.user.companyId);
        const invoices = await Invoice.find({ company_id: targetCompanyId }).populate('customer_id');
        res.json(invoices);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Create new invoice (Bill)
router.post('/', auth, async (req, res) => {
    try {
        const { items, ...invoiceData } = req.body;
        
        // 1. Create the invoice
        const invoice = new Invoice({
            ...invoiceData,
            items,
            company_id: req.user.companyId,
            created_by: req.user.id
        });

        // 2. Process stock deduction for each item
        for (const lineItem of items) {
            const product = await Product.findById(lineItem.item_id);
            if (product) {
                // Deduct quantity from stock
                product.countInStock = Math.max(0, product.countInStock - lineItem.quantity);
                product.inStock = product.countInStock > 0;
                await product.save();
            }
        }

        await invoice.save();
        res.status(201).json(invoice);
    } catch (err) {
        res.status(400).json({ error: err.message });
    }
});

// Get single invoice details
router.get('/:id', auth, async (req, res) => {
    try {
        const invoice = await Invoice.findById(req.params.id)
            .populate('customer_id')
            .populate('items.item_id'); // Populate product info in items
        if (!invoice) return res.status(404).json({ error: 'Invoice not found' });
        res.json(invoice);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

module.exports = router;
