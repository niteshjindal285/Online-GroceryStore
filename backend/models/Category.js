const mongoose = require('mongoose');

const categorySchema = new mongoose.Schema({
  name: { type: String, required: true },
  description: String,
  is_active: { type: Boolean, default: true },
  company_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' }
}, { timestamps: true });

module.exports = mongoose.model('Category', categorySchema);
