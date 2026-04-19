const mongoose = require('mongoose');

const unitSchema = new mongoose.Schema({
  code: { type: String, required: true },
  name: { type: String, required: true },
  description: String,
  is_active: { type: Boolean, default: true }
}, { timestamps: true });

module.exports = mongoose.model('Unit', unitSchema);
