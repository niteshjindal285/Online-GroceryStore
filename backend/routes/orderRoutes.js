const express = require('express');
const router = express.Router();
const auth = require('../middleware/auth');
const Order = require('../models/Order');

// Create order
router.post('/', auth, async (req, res) => {
  const { items, shippingAddress, totalPrice } = req.body;
  const order = new Order({ user: req.user._id, items, shippingAddress, totalPrice });
  await order.save();
  res.status(201).json(order);
});

// Get orders for user
router.get('/', auth, async (req, res) => {
  const orders = await Order.find({ user: req.user._id }).populate('items.product');
  res.json(orders);
});

module.exports = router;
