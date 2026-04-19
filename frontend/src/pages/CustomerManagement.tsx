import React, { useState, useEffect, useCallback } from 'react';
import api from '../api/config';
import {
    Plus, Search, Edit2, Trash2, Users,
    X, Loader2, Phone, Mail, MapPin, Building2
} from 'lucide-react';
import { useToast } from '../contexts/ToastContext';

interface Customer {
    _id: string;
    code: string;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    city?: string;
    state?: string;
    country?: string;
    postal_code?: string;
    credit_limit: number;
    is_active: boolean;
}

const CustomerManagement: React.FC = () => {
    const { showToast } = useToast();
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    // Modal State
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingCustomerId, setEditingCustomerId] = useState<string | null>(null);
    const [formData, setFormData] = useState({
        code: '',
        name: '',
        email: '',
        phone: '',
        address: '',
        city: '',
        state: '',
        country: 'India',
        postal_code: '',
        credit_limit: 0,
        is_active: true
    });

    const fetchCustomers = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/customers');
            setCustomers(Array.isArray(res.data) ? res.data : []);
        } catch (error: unknown) {
            console.error('Error fetching customers:', error);
            showToast('Failed to load customers', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchCustomers();
    }, [fetchCustomers]);

    const handleOpenAddModal = () => {
        setEditingCustomerId(null);
        setFormData({
            code: `CUST-${Date.now().toString().slice(-6)}`,
            name: '',
            email: '',
            phone: '',
            address: '',
            city: '',
            state: '',
            country: 'India',
            postal_code: '',
            credit_limit: 0,
            is_active: true
        });
        setIsModalOpen(true);
    };

    const handleOpenEditModal = (customer: Customer) => {
        setEditingCustomerId(customer._id);
        setFormData({
            code: customer.code,
            name: customer.name,
            email: customer.email || '',
            phone: customer.phone || '',
            address: customer.address || '',
            city: customer.city || '',
            state: customer.state || '',
            country: customer.country || 'India',
            postal_code: customer.postal_code || '',
            credit_limit: customer.credit_limit,
            is_active: customer.is_active
        });
        setIsModalOpen(true);
    };

    const handleDeleteCustomer = async (id: string) => {
        if (!window.confirm('Are you sure you want to delete this customer?')) return;
        try {
            await api.delete(`/customers/${id}`);
            showToast('Customer deleted successfully', 'success');
            fetchCustomers();
        } catch (error: unknown) {
            console.error('Delete error:', error);
            showToast('Failed to delete customer', 'error');
        }
    };

    const handleSaveCustomer = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            if (editingCustomerId) {
                await api.put(`/customers/${editingCustomerId}`, formData);
                showToast('Customer updated successfully', 'success');
            } else {
                await api.post('/customers', formData);
                showToast('Customer added successfully', 'success');
            }
            setIsModalOpen(false);
            fetchCustomers();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { error?: string } } };
            showToast(err.response?.data?.error || 'Failed to save customer', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    const filteredCustomers = customers.filter(c =>
        c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        c.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (c.phone && c.phone.includes(searchTerm))
    );

    if (loading && customers.length === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 text-indigo-600">
                <Loader2 className="animate-spin" size={40} />
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 p-6 lg:p-10 text-slate-900">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 flex items-center gap-3">
                            <Users className="text-indigo-600" />
                            Customer Management
                        </h1>
                        <p className="text-slate-500 mt-1">Manage your customer database and credit limits.</p>
                    </div>
                    <button
                        onClick={handleOpenAddModal}
                        className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors shadow-sm font-medium"
                    >
                        <Plus size={18} />
                        Add Customer
                    </button>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex items-center justify-between">
                    <div className="relative max-w-md w-full">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                        <input
                            type="text"
                            placeholder="Search by name, code, or phone..."
                            className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {filteredCustomers.length > 0 ? (
                        filteredCustomers.map((customer) => (
                            <div key={customer._id} className="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow p-6 flex flex-col h-full">
                                <div className="flex justify-between items-start mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="p-3 bg-indigo-50 text-indigo-600 rounded-xl">
                                            <Users size={24} />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-slate-900 text-lg">{customer.name}</h3>
                                            <p className="text-xs font-mono text-slate-400 uppercase tracking-wider">{customer.code}</p>
                                        </div>
                                    </div>
                                    <span className={`px-2 py-1 text-[10px] font-bold rounded-full uppercase ${customer.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-400'}`}>
                                        {customer.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </div>

                                <div className="space-y-3 mb-6 bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                                    <div className="flex items-center gap-2 text-sm text-slate-600 font-medium">
                                        <Phone size={14} className="text-indigo-500" />
                                        {customer.phone || 'No phone'}
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-slate-600 font-medium">
                                        <Mail size={14} className="text-indigo-500" />
                                        {customer.email || 'No email'}
                                    </div>
                                    <div className="flex items-start gap-2 text-sm text-slate-600 font-medium">
                                        <MapPin size={14} className="text-indigo-500 mt-0.5" />
                                        <span className="line-clamp-2">
                                            {customer.address ? `${customer.address}, ${customer.city}` : 'No address'}
                                        </span>
                                    </div>
                                    <div className="pt-2 flex justify-between items-center border-t border-slate-200">
                                        <span className="text-xs text-slate-400 uppercase font-bold tracking-tight">Credit Limit</span>
                                        <span className="text-sm font-bold text-slate-900">₹{customer.credit_limit.toLocaleString()}</span>
                                    </div>
                                </div>

                                <div className="flex gap-2 pt-2 border-slate-50">
                                    <button
                                        onClick={() => handleOpenEditModal(customer)}
                                        className="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 border border-slate-200 rounded-lg transition-all"
                                    >
                                        <Edit2 size={14} />
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDeleteCustomer(customer._id)}
                                        className="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-bold text-red-600 hover:bg-red-50 border border-red-200 rounded-lg transition-all"
                                    >
                                        <Trash2 size={14} />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-full py-20 text-center bg-white rounded-xl border border-dashed border-slate-300 shadow-sm">
                            <Users size={64} className="mx-auto text-slate-200 mb-4" />
                            <h3 className="text-lg font-bold text-slate-900">No Customers Found</h3>
                            <p className="text-slate-500 max-w-xs mx-auto mb-6">Your customer database is empty. Add your first customer to start billing.</p>
                            <button
                                onClick={handleOpenAddModal}
                                className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold transition-all shadow-lg shadow-indigo-600/20 hover:-translate-y-0.5"
                            >
                                <Plus size={18} /> Add First Customer
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white text-slate-900">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                    <Users size={20} />
                                </div>
                                <div>
                                    <h2 className="font-bold text-slate-900">{editingCustomerId ? 'Edit Customer' : 'Add New Customer'}</h2>
                                    <p className="text-xs text-slate-400 font-medium">{editingCustomerId ? 'Update existing profile' : 'Create a new customer profile'}</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                <X size={20} className="text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveCustomer} className="p-6 space-y-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Customer Code</label>
                                    <input
                                        type="text" required readOnly={!!editingCustomerId}
                                        className={`w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 font-mono text-sm ${editingCustomerId ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : ''}`}
                                        value={formData.code}
                                        onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Full Name</label>
                                    <input
                                        type="text" required
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm font-medium"
                                        placeholder="e.g. John Doe / Global Industries"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Email Address</label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300" size={14} />
                                        <input
                                            type="email"
                                            className="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                            placeholder="john@example.com"
                                            value={formData.email}
                                            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Phone Number</label>
                                    <div className="relative">
                                        <Phone className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300" size={14} />
                                        <input
                                            type="tel"
                                            className="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                            placeholder="+91 XXXXX XXXXX"
                                            value={formData.phone}
                                            onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1 -mb-2">Address Details</label>
                                <div className="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-4">
                                    <input
                                        type="text"
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                        placeholder="Street Address"
                                        value={formData.address}
                                        onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                                    />
                                    <div className="grid grid-cols-3 gap-3">
                                        <input
                                            type="text"
                                            className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                            placeholder="City"
                                            value={formData.city}
                                            onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                                        />
                                        <input
                                            type="text"
                                            className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                            placeholder="State"
                                            value={formData.state}
                                            onChange={(e) => setFormData({ ...formData, state: e.target.value })}
                                        />
                                        <input
                                            type="text"
                                            className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                                            placeholder="Pin Code"
                                            value={formData.postal_code}
                                            onChange={(e) => setFormData({ ...formData, postal_code: e.target.value })}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Credit Limit (₹)</label>
                                    <input
                                        type="number" required
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm font-bold"
                                        value={formData.credit_limit}
                                        onChange={(e) => setFormData({ ...formData, credit_limit: parseFloat(e.target.value) })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Account Status</label>
                                    <select
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 bg-white text-sm font-medium"
                                        value={formData.is_active ? 'true' : 'false'}
                                        onChange={(e) => setFormData({ ...formData, is_active: e.target.value === 'true' })}
                                    >
                                        <option value="true">Active Account</option>
                                        <option value="false">Inactive / Suspended</option>
                                    </select>
                                </div>
                            </div>

                            <div className="pt-4 flex gap-3">
                                <button
                                    type="button" onClick={() => setIsModalOpen(false)}
                                    className="flex-1 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50 border border-slate-200 rounded-xl transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit" disabled={isSubmitting}
                                    className="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-colors flex items-center justify-center gap-2 shadow-xl shadow-indigo-600/30 hover:-translate-y-0.5"
                                >
                                    {isSubmitting ? <Loader2 size={18} className="animate-spin" /> : <Plus size={18} />}
                                    {editingCustomerId ? 'Update Customer' : 'Add to Database'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default CustomerManagement;
