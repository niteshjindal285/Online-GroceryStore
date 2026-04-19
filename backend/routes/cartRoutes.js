const express = require('express');
const router = express.Router();
const auth = require('../middleware/auth');
const Cart = require('../models/Cart');

// Get cart for user
router.get('/', auth, async (req, res) => {
  let cart = await Cart.findOne({ user: req.user._id }).populate('items.product');
  if (!cart) cart = { items: [] };
  res.json(cart);
});

// Add / update cart
router.post('/', auth, async (req, res) => {
  const { items } = req.body; // items: [{ product, qty }]
  let cart = await Cart.findOne({ user: req.user._id });
  if (!cart) cart = new Cart({ user: req.user._id, items });
  else cart.items = items;
  await cart.save();
  res.json(cart);
});

module.exports = router;
