const mongoose = require('mongoose');

const productSchema = new mongoose.Schema({
  name: { type: String, required: true },
  code: { type: String, unique: true, sparse: true },
  barcode: String,
  description: String,
  price: { type: Number, required: true }, // This maps to selling_price
  average_cost: { type: Number, default: 0 },
  category: { type: String, required: true, default: 'grocery' },
  category_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Category' },
  unit_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Unit' },
  supplier_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Supplier' },
  image: String,
  rating: { type: mongoose.Schema.Types.Mixed, default: 0 },
  discount: { type: Number, default: 0 },
  inStock: { type: Boolean, default: true },
  countInStock: { type: Number, default: 0 },
  reorder_level: { type: Number, default: 0 },
  company_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' }
}, { timestamps: true });

module.exports = mongoose.model('Product', productSchema);
