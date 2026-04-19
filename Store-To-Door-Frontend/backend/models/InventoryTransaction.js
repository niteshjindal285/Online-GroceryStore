const mongoose = require('mongoose');

const InventoryTransactionSchema = new mongoose.Schema({
  type: { type: String, enum: ['adjustment', 'transfer', 'receipt', 'issue'], required: true },
  productId: { type: mongoose.Schema.Types.ObjectId, ref: 'Product', required: true },
  quantity: { type: Number, required: true },
  fromLocation: String,
  toLocation: String,
  reference: String, // PO/SO number
  companyId: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' },
  createdBy: { type: mongoose.Schema.Types.ObjectId, ref: 'User' }
}, { timestamps: true });

module.exports = mongoose.model('InventoryTransaction', InventoryTransactionSchema);

