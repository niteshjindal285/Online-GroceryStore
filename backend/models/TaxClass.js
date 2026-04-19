const mongoose = require('mongoose');

const TaxClassSchema = new mongoose.Schema({
  name: { type: String, required: true },
  rate: { type: Number, required: true },
  type: { type: String, enum: ['sales', 'purchase'], default: 'sales' },
  companyId: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' },
  isActive: { type: Boolean, default: true }
}, { timestamps: true });

module.exports = mongoose.model('TaxClass', TaxClassSchema);

