import React from 'react';
import { Receipt } from 'lucide-react';

interface ReceiptProps {
    invoice: {
        invoice_number: string;
        date: string;
        customer_id: { name: string; phone?: string; address?: string };
        items: Array<{
            name: string;
            quantity: number;
            unit_price: number;
            line_total: number;
        }>;
        subtotal: number;
        tax_amount: number;
        total_amount: number;
    };
    onClose: () => void;
}

const ReceiptTemplate: React.FC<ReceiptProps> = ({ invoice, onClose }) => {
    return (
        <div className="fixed inset-0 z-[100] bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto print:p-0 print:bg-white print:block">
            {/* Modal Container */}
            <div className="bg-white w-full max-w-[400px] shadow-2xl rounded-2xl overflow-hidden print:shadow-none print:w-full print:max-w-none print:rounded-none">
                
                {/* Actions (Not for Print) */}
                <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white print:hidden">
                    <h3 className="font-bold text-slate-900">Print Preview</h3>
                    <div className="flex gap-2">
                        <button 
                            onClick={() => window.print()}
                            className="p-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            <Receipt size={18} />
                        </button>
                        <button 
                            onClick={onClose}
                            className="p-2 hover:bg-slate-100 text-slate-400 rounded-lg transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>

                {/* The Receipt Content */}
                <div className="p-8 font-mono text-sm text-slate-900 print:p-4 print:text-[12px]">
                    <div className="text-center mb-6">
                        <h2 className="text-xl font-black uppercase tracking-tighter">STORE TO DOOR</h2>
                        <p className="text-[10px] text-slate-500 font-sans">Reliable Grocery & Essentials Delivery</p>
                        <div className="mt-2 border-y border-dashed border-slate-300 py-1">
                            <p className="text-[11px] font-bold">TAX INVOICE</p>
                        </div>
                    </div>

                    <div className="space-y-1 mb-6">
                        <div className="flex justify-between">
                            <span>INV NO:</span>
                            <span className="font-bold">{invoice.invoice_number}</span>
                        </div>
                        <div className="flex justify-between">
                            <span>DATE:</span>
                            <span>{new Date(invoice.date).toLocaleDateString()}</span>
                        </div>
                        <div className="flex justify-between">
                            <span>CUSTOMER:</span>
                            <span className="font-bold uppercase">{invoice.customer_id.name}</span>
                        </div>
                        {invoice.customer_id.phone && (
                            <div className="flex justify-between">
                                <span>CONTACT:</span>
                                <span>{invoice.customer_id.phone}</span>
                            </div>
                        )}
                    </div>

                    {/* Table Header */}
                    <div className="border-b border-dashed border-slate-300 pb-1 mb-2 font-bold flex">
                        <span className="flex-1">ITEM</span>
                        <span className="w-12 text-center">QTY</span>
                        <span className="w-20 text-right">TOTAL</span>
                    </div>

                    {/* Items */}
                    <div className="space-y-2 mb-6">
                        {invoice.items.map((item, idx) => (
                            <div key={idx} className="flex leading-tight">
                                <div className="flex-1">
                                    <p className="font-bold">{item.name}</p>
                                    <p className="text-[10px] text-slate-500">@{item.unit_price.toFixed(2)}</p>
                                </div>
                                <span className="w-12 text-center pt-0.5">{item.quantity}</span>
                                <span className="w-20 text-right pt-0.5">₹{item.line_total.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>

                    {/* Summary */}
                    <div className="border-t border-dashed border-slate-300 pt-3 space-y-1">
                        <div className="flex justify-between">
                            <span>SUBTOTAL:</span>
                            <span>₹{invoice.subtotal.toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span>TAX (GST 18%):</span>
                            <span>₹{invoice.tax_amount.toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between text-lg font-black pt-2 border-t border-double border-slate-900 mt-2">
                            <span>GRAND TOTAL:</span>
                            <span>₹{invoice.total_amount.toFixed(2)}</span>
                        </div>
                    </div>

                    <div className="mt-10 text-center space-y-4">
                        <div className="inline-block p-1 border-2 border-slate-900 rounded-lg">
                            <p className="text-[10px] font-black uppercase px-2">PAID SUCCESSFUL</p>
                        </div>
                        <p className="text-[10px] text-slate-400 italic">Thank you for shopping with us!<br/>Visit again at store-to-door.erp</p>
                        <div className="flex justify-center grayscale opacity-80 pt-2">
                             {/* Mock Barcode */}
                             <div className="h-8 w-40 bg-slate-900 rounded-[2px]" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ReceiptTemplate;
