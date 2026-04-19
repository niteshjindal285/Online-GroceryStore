const mongoose = require('mongoose');

const JournalEntrySchema = new mongoose.Schema({
  date: { type: Date, required: true },
  description: String,
  companyId: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' },
  lines: [{
    accountId: { type: mongoose.Schema.Types.ObjectId, ref: 'Account' }, // Assuming Account model added later
    debit: { type: Number, default: 0 },
    credit: { type: Number, default: 0 },
    description: String
  }],
  posted: { type: Boolean, default: false },
  createdBy: { type: mongoose.Schema.Types.ObjectId, ref: 'User' }
}, { timestamps: true });

module.exports = mongoose.model('JournalEntry', JournalEntrySchema);

