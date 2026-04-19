import React, { useState, useEffect, useCallback } from 'react';
import api from '../api/config';
import { 
    Search, Receipt, User, Calendar, 
    ChevronRight, Loader2, Printer, 
    Eye, Filter, ArrowLeft 
} from 'lucide-react';
import { useToast } from '../contexts/ToastContext';
import { useNavigate } from 'react-router-dom';

interface Invoice {
    _id: string;
    invoice_number: string;
    customer_id: { name: string; code: string };
    total_amount: number;
    subtotal: number;
    tax_amount: number;
    date: string;
    status: string;
}

const BillingHistory: React.FC = () => {
    const { showToast } = useToast();
    const navigate = useNavigate();
    const [invoices, setInvoices] = useState<Invoice[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);

    const fetchInvoices = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/invoices');
            setInvoices(Array.isArray(res.data) ? res.data : []);
        } catch (error: unknown) {
            console.error('Fetch error:', error);
            showToast('Failed to load billing history', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchInvoices();
    }, [fetchInvoices]);

    const filteredInvoices = invoices.filter(inv => 
        inv.invoice_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
        inv.customer_id.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        inv.customer_id.code.toLowerCase().includes(searchTerm.toLowerCase())
    );

    if (loading && invoices.length === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <Loader2 className="animate-spin text-indigo-600" size={40} />
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 p-6 lg:p-10 text-slate-900">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div className="flex items-center gap-4">
                        <button 
                            onClick={() => navigate('/admin')}
                            className="p-2 hover:bg-white rounded-lg transition-colors text-slate-400 hover:text-indigo-600"
                        >
                            <ArrowLeft size={20} />
                        </button>
                        <div>
                            <h1 className="text-3xl font-bold text-slate-900 flex items-center gap-3">
                                <Receipt className="text-indigo-600" />
                                Billing History
                            </h1>
                            <p className="text-slate-500 mt-1">Review all generated invoices and transactions.</p>
                        </div>
                    </div>
                </div>

                {/* Filters & Stats */}
                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                    <div className="lg:col-span-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                            <input
                                type="text"
                                placeholder="Search by Invoice # or Customer Name..."
                                className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                        <button className="flex items-center gap-2 px-4 py-2 text-slate-600 font-bold text-sm hover:bg-slate-50 rounded-lg border border-slate-200 transition-all">
                            <Filter size={16} />
                            Filters
                        </button>
                    </div>
                    <div className="bg-indigo-600 p-4 rounded-xl shadow-lg shadow-indigo-600/20 text-white flex flex-col justify-center">
                        <span className="text-indigo-100 text-[10px] font-bold uppercase tracking-widest">Total Sales</span>
                        <span className="text-2xl font-black">₹{invoices.reduce((s, i) => s + i.total_amount, 0).toLocaleString()}</span>
                    </div>
                </div>

                {/* Invoices List */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Invoice Details</th>
                                    <th className="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer</th>
                                    <th className="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date</th>
                                    <th className="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Amount</th>
                                    <th className="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</th>
                                    <th className="px-6 py-4 text-right"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredInvoices.map((inv) => (
                                    <tr key={inv._id} className="hover:bg-slate-50/50 transition-colors group">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
                                                    <Receipt size={18} />
                                                </div>
                                                <div>
                                                    <p className="font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">{inv.invoice_number}</p>
                                                    <p className="text-[10px] text-slate-400 font-mono">ID: {inv._id.slice(-6).toUpperCase()}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <div className="w-6 h-6 bg-slate-100 text-slate-500 rounded-full flex items-center justify-center">
                                                    <User size={12} />
                                                </div>
                                                <span className="text-sm font-medium text-slate-700">{inv.customer_id?.name || 'Walk-in'}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2 text-slate-500 text-sm">
                                                <Calendar size={14} />
                                                {new Date(inv.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-sm font-black text-slate-900">₹{inv.total_amount.toLocaleString()}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2 py-1 text-[10px] font-bold rounded-full uppercase tracking-tight ${
                                                inv.status === 'paid' ? 'bg-green-100 text-green-700' : 
                                                inv.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'
                                            }`}>
                                                {inv.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button 
                                                onClick={() => showToast('Invoice Details coming in next update', 'info')}
                                                className="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                                            >
                                                <ChevronRight size={18} />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default BillingHistory;
