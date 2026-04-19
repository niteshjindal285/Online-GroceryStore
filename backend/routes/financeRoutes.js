const express = require('express');
const router = express.Router();
const JournalEntry = require('../models/JournalEntry');
const Payroll = require('../models/Payroll');
const auth = require('../middleware/auth');

// Get journal entries
router.get('/journal-entries', auth, async (req, res) => {
  try {
    const entries = await JournalEntry.find({ companyId: req.user.companyId }).populate('lines.accountId');
    res.json(entries);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Create journal entry (converted from ERP add_journal_entry.php)
router.post('/journal-entries', auth, async (req, res) => {
  try {
    const entry = new JournalEntry({
      ...req.body,
      createdBy: req.user.id,
      companyId: req.user.companyId
    });
    await entry.save();
    res.status(201).json(entry);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

// Get payroll
router.get('/payroll', auth, async (req, res) => {
  try {
    const payroll = await Payroll.find({ companyId: req.user.companyId }).populate('employeeId');
    res.json(payroll);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Post payroll (from ERP payroll.php)
router.post('/payroll', auth, async (req, res) => {
  try {
    const payroll = new Payroll({
      ...req.body,
      companyId: req.user.companyId
    });
    await payroll.save();
    res.status(201).json(payroll);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

module.exports = router;

