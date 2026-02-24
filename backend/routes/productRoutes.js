const express = require('express');
const router = express.Router();
const Product = require('../models/Product');
const multer = require('multer');
const path = require('path');

// multer setup
const storage = multer.diskStorage({
  destination(req, file, cb) {
    cb(null, 'uploads/products/');
  },
  filename(req, file, cb) {
    cb(null, `${Date.now()}-${file.originalname}`);
  }
});
const upload = multer({ storage });

// Get all products
router.get('/', async (req, res) => {
  const products = await Product.find();
  res.json(products);
});

// Get single product
router.get('/:id', async (req, res) => {
  const product = await Product.findById(req.params.id);
  if (!product) return res.status(404).json({ message: 'Product not found' });
  res.json(product);
});

// Admin: add product with image
router.post('/', upload.single('image'), async (req, res) => {
  const { name, description, price, countInStock } = req.body;
  const image = req.file ? `/uploads/products/${req.file.filename}` : req.body.image;
  const product = new Product({ name, description, price, countInStock, image });
  await product.save();
  res.status(201).json(product);
});

module.exports = router;
