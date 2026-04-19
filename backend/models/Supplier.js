const mongoose = require('mongoose');

const supplierSchema = new mongoose.Schema({
  name: { type: String, required: true },
  code: { type: String, required: true, unique: true },
  contact_person: String,
  email: String,
  phone: String,
  address: String,
  is_active: { type: Boolean, default: true },
  company_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' }
}, { timestamps: true });

module.exports = mongoose.model('Supplier', supplierSchema);
