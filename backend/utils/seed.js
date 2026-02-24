const mongoose = require('mongoose');
const Product = require('../models/Product');
const dotenv = require('dotenv');
const fs = require('fs');
const path = require('path');

dotenv.config();
const MONGO_URI = process.env.MONGO_URI || 'mongodb://localhost:27017/store-to-door';

const sampleProducts = [
  { name: 'Fresh Apples', description: 'Crisp red apples', price: 2.5, image: '/uploads/products/apple.jpg', countInStock: 50 },
  { name: 'Orange Juice', description: 'Natural orange juice 1L', price: 3.75, image: '/uploads/products/orange-juice.jpg', countInStock: 30 },
  { name: 'Brown Bread', description: 'Whole wheat bread', price: 1.75, image: '/uploads/products/bread.jpg', countInStock: 20 }
];

mongoose.connect(MONGO_URI, { useNewUrlParser: true, useUnifiedTopology: true })
  .then(async () => {
    console.log('Connected to MongoDB, seeding...');
    await Product.deleteMany({});
    await Product.insertMany(sampleProducts);
    console.log('Seeded products');
    mongoose.disconnect();
  })
  .catch(err => {
    console.error('DB error', err);
  });
