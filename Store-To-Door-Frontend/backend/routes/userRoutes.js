const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const User = require('../models/User');

// Register
router.post('/register', async (req, res) => {
  try {
    const { name, email, password } = req.body;
    let user = await User.findOne({ email });
    if (user) return res.status(400).json({ message: 'User already exists' });
    const salt = await bcrypt.genSalt(10);
    const hashed = await bcrypt.hash(password, salt);
    user = new User({ name, email, password: hashed });
    await user.save();
    const token = jwt.sign({ id: user._id }, process.env.JWT_SECRET || 'your_jwt_secret_here', { expiresIn: '30d' });
    res.json({ token, user: { id: user._id, name: user.name, email: user.email, role: user.isAdmin ? 'admin' : 'customer' } });
  } catch (err) {
    console.error("Register Error:", err);
    res.status(500).json({ message: 'Server error', error: err.message });
  }
});

// Login
router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body;
    const user = await User.findOne({ email });
    if (!user) return res.status(400).json({ message: 'Invalid credentials' });
    const isMatch = await require('bcryptjs').compare(password, user.password);
    if (!isMatch) return res.status(400).json({ message: 'Invalid credentials' });
    const token = jwt.sign({ id: user._id }, process.env.JWT_SECRET || 'your_jwt_secret_here', { expiresIn: '30d' });
    res.json({ token, user: { id: user._id, name: user.name, email: user.email, role: user.isAdmin ? 'admin' : 'customer' } });
  } catch (err) {
    res.status(500).json({ message: 'Server error' });
  }
});

// Get all users (Admin only)
const auth = require('../middleware/auth');
router.get('/', auth, async (req, res) => {
  try {
    console.log(`Fetching all users. Requested by: ${req.user.email} (Admin: ${req.user.isAdmin})`);
    if (!req.user.isAdmin) {
      console.warn(`Access denied to '/api/users' for user: ${req.user.email}`);
      return res.status(403).json({ message: 'Access denied' });
    }
    const users = await User.find().select('-password');
    res.json(users);
  } catch (err) {
    console.error('Fetch Users Error:', err.message);
    res.status(500).json({ error: err.message });
  }
});

module.exports = router;
