const express = require('express');
const router = express.Router();
const Supplier = require('../models/Supplier');
const auth = require('../middleware/auth');

// Get all suppliers
router.get('/', auth, async (req, res) => {
  try {
    const targetCompanyId = req.query.companyId || (req.user && req.user.companyId);
    const suppliers = await Supplier.find({ company_id: targetCompanyId });
    res.json(suppliers);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Create supplier
router.post('/', auth, async (req, res) => {
  try {
    const supplier = new Supplier({
      ...req.body,
      company_id: req.body.company_id || req.user.companyId
    });
    await supplier.save();
    res.status(201).json(supplier);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Update supplier
router.put('/:id', auth, async (req, res) => {
  try {
    const supplier = await Supplier.findByIdAndUpdate(req.params.id, req.body, { new: true });
    res.json(supplier);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

module.exports = router;
