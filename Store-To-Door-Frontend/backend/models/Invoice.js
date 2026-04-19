const mongoose = require('mongoose');

const invoiceItemSchema = new mongoose.Schema({
    item_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Product', required: true },
    name: String, // Snapshot of item name at time of billing
    quantity: { type: Number, required: true },
    unit_price: { type: Number, required: true },
    line_total: { type: Number, required: true }
});

const invoiceSchema = new mongoose.Schema({
    invoice_number: { type: String, required: true, unique: true },
    customer_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Customer', required: true },
    company_id: { type: mongoose.Schema.Types.ObjectId, ref: 'Company' },
    date: { type: Date, default: Date.now },
    items: [invoiceItemSchema],
    subtotal: { type: Number, required: true },
    tax_amount: { type: Number, default: 0 },
    discount_amount: { type: Number, default: 0 },
    total_amount: { type: Number, required: true },
    status: { type: String, enum: ['draft', 'paid', 'cancelled'], default: 'paid' },
    payment_method: String,
    notes: String,
    created_by: { type: mongoose.Schema.Types.ObjectId, ref: 'User' }
}, { timestamps: true });

module.exports = mongoose.model('Invoice', invoiceSchema);
