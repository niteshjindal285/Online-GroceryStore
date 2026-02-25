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
  const { name, description, price, countInStock, category, rating, discount, inStock } = req.body;
  const image = req.file ? `/uploads/products/${req.file.filename}` : req.body.image;
  const product = new Product({ name, description, price, countInStock, image, category, rating, discount, inStock });
  await product.save();
  res.status(201).json(product);
});

// Update product
router.put('/:id', upload.single('image'), async (req, res) => {
  try {
    const product = await Product.findById(req.params.id);
    if (!product) return res.status(404).json({ message: 'Product not found' });

    const { name, description, price, countInStock, category, rating, discount, inStock } = req.body;

    product.name = name || product.name;
    product.description = description || product.description;
    product.price = price !== undefined ? price : product.price;
    product.countInStock = countInStock !== undefined ? countInStock : product.countInStock;
    product.category = category || product.category;
    product.rating = rating !== undefined ? rating : product.rating;
    product.discount = discount !== undefined ? discount : product.discount;
    product.inStock = inStock !== undefined ? inStock : product.inStock;

    if (req.file) {
      product.image = `/uploads/products/${req.file.filename}`;
    } else if (req.body.image) {
      product.image = req.body.image;
    }

    const updatedProduct = await product.save();
    res.json(updatedProduct);
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

// Delete product
router.delete('/:id', async (req, res) => {
  try {
    const product = await Product.findById(req.params.id);
    if (!product) return res.status(404).json({ message: 'Product not found' });

    await Product.deleteOne({ _id: req.params.id });
    res.json({ message: 'Product removed' });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

module.exports = router;
