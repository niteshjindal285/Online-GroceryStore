const express = require('express');
const router = express.Router();
const InventoryTransaction = require('../models/InventoryTransaction');
const Product = require('../models/Product');
const Category = require('../models/Category');
const Unit = require('../models/Unit');
const auth = require('../middleware/auth');

// --- Inventory Items (Products) ---

// Get all inventory items (equivalent to inventory/index.php)
router.get('/items', auth, async (req, res) => {
  try {
    const { search, category, companyId } = req.query;
    // Multi-company filtering logic from conversation history
    const targetCompanyId = companyId || (req.user && req.user.companyId);
    
    const query = { company_id: targetCompanyId };
    
    if (search) {
      query.$or = [
        { name: { $regex: search, $options: 'i' } },
        { code: { $regex: search, $options: 'i' } },
        { barcode: { $regex: search, $options: 'i' } }
      ];
    }
    
    if (category) {
      query.category = category;
    }

    const items = await Product.find(query)
      .populate('category_id')
      .populate('unit_id')
      .populate('supplier_id');
    
    res.json(items);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Create inventory item (create.php)
router.post('/items', auth, async (req, res) => {
  try {
    const item = new Product({
      ...req.body,
      company_id: req.body.company_id || req.user.companyId
    });
    await item.save();
    res.status(201).json(item);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Update inventory item (edit.php)
router.put('/items/:id', auth, async (req, res) => {
  try {
    const item = await Product.findByIdAndUpdate(req.params.id, req.body, { new: true });
    res.json(item);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Delete inventory item
router.delete('/items/:id', auth, async (req, res) => {
  try {
    const item = await Product.findByIdAndDelete(req.params.id);
    if (!item) return res.status(404).json({ message: 'Item not found' });
    res.json({ message: 'Item deleted successfully' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// --- Categories ---

router.get('/categories', auth, async (req, res) => {
  try {
    const targetCompanyId = req.query.companyId || (req.user && req.user.companyId);
    const categories = await Category.find({ company_id: targetCompanyId });
    res.json(categories);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/categories', auth, async (req, res) => {
  try {
    const category = new Category({
      ...req.body,
      company_id: req.body.company_id || req.user.companyId
    });
    await category.save();
    res.status(201).json(category);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

router.put('/categories/:id', auth, async (req, res) => {
  try {
    const category = await Category.findByIdAndUpdate(req.params.id, req.body, { new: true });
    res.json(category);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

router.delete('/categories/:id', auth, async (req, res) => {
  try {
    await Category.findByIdAndDelete(req.params.id);
    res.json({ message: 'Category deleted successfully' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// --- Units ---

router.get('/units', auth, async (req, res) => {
  try {
    const units = await Unit.find({ is_active: true });
    res.json(units);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// --- Transactions ---

// Get inventory transactions (from ERP inventory/dashboard.php)
router.get('/transactions', auth, async (req, res) => {
  try {
    const { type, productId } = req.query;
    const query = { companyId: req.user.companyId };
    if (type) query.type = type;
    if (productId) query.productId = productId;
    const transactions = await InventoryTransaction.find(query).populate('productId createdBy');
    res.json(transactions);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Create transaction (add_transaction.php equivalent)
router.post('/transactions', auth, async (req, res) => {
  try {
    const transaction = new InventoryTransaction({
      ...req.body,
      companyId: req.user.companyId,
      createdBy: req.user.id
    });
    await transaction.save();
    res.status(201).json(transaction);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

module.exports = router;

