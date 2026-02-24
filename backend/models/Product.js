const mongoose = require('mongoose');

const productSchema = new mongoose.Schema({
  name: { type: String, required: true },
  description: String,
  price: { type: Number, required: true },
  image: String,
  category: { type: String, required: true, default: 'grocery' },
  rating: { type: mongoose.Schema.Types.Mixed, default: 0 },
  discount: { type: Number, default: 0 },
  inStock: { type: Boolean, default: true },
  countInStock: { type: Number, default: 0 }
}, { timestamps: true });

module.exports = mongoose.model('Product', productSchema);
