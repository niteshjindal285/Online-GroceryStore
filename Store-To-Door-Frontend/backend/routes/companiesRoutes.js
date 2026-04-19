const express = require('express');
const router = express.Router();
const Company = require('../models/Company');
const auth = require('../middleware/auth');

// Get companies (from ERP companies/index.php)
router.get('/', auth, async (req, res) => {
  try {
    console.log(`Fetching companies for user: ${req.user.email} (Admin: ${req.user.isAdmin})`);
    const filter = req.user.isAdmin ? {} : { users: req.user.id };
    const companies = await Company.find(filter).populate('managerId');
    res.json(companies);
  } catch (err) {
    console.error('Fetch Companies Error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// Create company (companies/create.php)
router.post('/', auth, async (req, res) => {
  try {
    const data = { ...req.body };
    if (!data.managerId || data.managerId === '') {
      delete data.managerId;
    }
    const company = new Company({
      ...data,
      users: [req.user.id]
    });
    await company.save();
    res.status(201).json(company);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Update basic details
router.put('/:id', auth, async (req, res) => {
  try {
    const company = await Company.findById(req.params.id);
    if (!company.users.includes(req.user.id) && !req.user.isAdmin) return res.status(401).json({ message: 'Unauthorized' });
    
    const data = { ...req.body };
    if (data.managerId === '') {
      data.managerId = null;
    }
    
    Object.assign(company, data);
    await company.save();
    res.json(company);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Update company (assign user/manager)
router.put('/:id/users', auth, async (req, res) => {
  try {
    const company = await Company.findById(req.params.id);
    if (!company.users.includes(req.user.id) && !req.user.isAdmin) return res.status(401).json({ message: 'Unauthorized' });
    if (!company.users.includes(req.body.userId)) {
      company.users.push(req.body.userId);
    }
    if (req.body.asManager) company.managerId = req.body.userId;
    await company.save();
    res.json(company);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Delete company
router.delete('/:id', auth, async (req, res) => {
  try {
    if (!req.user.isAdmin) return res.status(403).json({ message: 'Only admins can delete companies' });
    await Company.findByIdAndDelete(req.params.id);
    res.json({ message: 'Company deleted' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;

