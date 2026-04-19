const mongoose = require('mongoose');

const customerSchema = new mongoose.Schema({
    code: { type: String, required: true, unique: true },
    name: { type: String, required: true },
    email: String,
    phone: String,
    address: String,
    city: String,
    state: String,
    country: String,
    postal_code: String,
    credit_limit: { type: Number, default: 0 },
    payment_terms: String,
    company_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' },
    is_active: { type: Boolean, default: true }
}, { timestamps: true });

module.exports = mongoose.model('Customer', customerSchema);
