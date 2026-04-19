const mongoose = require('mongoose');

const PayrollSchema = new mongoose.Schema({
  employeeId: { type: mongoose.Schema.Types.ObjectId, ref: 'User' },
  period: { type: String, required: true }, // YYYY-MM
  grossPay: { type: Number, required: true },
  deductions: { type: Number, default: 0 },
  netPay: Number,
  status: { type: String, enum: ['draft', 'paid'], default: 'draft' },
  companyId: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' }
}, { timestamps: true });

module.exports = mongoose.model('Payroll', PayrollSchema);

