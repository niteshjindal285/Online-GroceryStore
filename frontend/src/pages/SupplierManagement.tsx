import React, { useState, useEffect, useCallback } from 'react';
import api from '../api/config';
import { 
    Plus, Search, Mail, Phone, MapPin, Edit2, 
    Trash2, Truck, User, X, Loader2 
} from 'lucide-react';
import { useToast } from '../contexts/ToastContext';

interface Supplier {
    _id: string;
    name: string;
    code: string;
    contact_person: string;
    email: string;
    phone: string;
    address: string;
    is_active: boolean;
}

const SupplierManagement = () => {
    const { showToast } = useToast();
    const [suppliers, setSuppliers] = useState<Supplier[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    
    // Modal State
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingSupplierId, setEditingSupplierId] = useState<string | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        code: '',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        is_active: true
    });

    const fetchSuppliers = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/suppliers');
            setSuppliers(Array.isArray(res.data) ? res.data : []);
        } catch (error: unknown) {
            console.error('Error fetching suppliers:', error);
            showToast('Failed to load suppliers', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchSuppliers();
    }, [fetchSuppliers]);

    const handleOpenAddModal = () => {
        setEditingSupplierId(null);
        setFormData({
            name: '',
            code: '',
            contact_person: '',
            email: '',
            phone: '',
            address: '',
            is_active: true
        });
        setIsModalOpen(true);
    };

    const handleOpenEditModal = (supplier: Supplier) => {
        setEditingSupplierId(supplier._id);
        setFormData({
            name: supplier.name,
            code: supplier.code,
            contact_person: supplier.contact_person || '',
            email: supplier.email || '',
            phone: supplier.phone || '',
            address: supplier.address || '',
            is_active: supplier.is_active
        });
        setIsModalOpen(true);
    };

    const handleDeleteSupplier = async (id: string) => {
        if (!window.confirm('Are you sure you want to delete this supplier?')) return;
        try {
            await api.delete(`/suppliers/${id}`);
            showToast('Supplier deleted successfully', 'success');
            fetchSuppliers();
        } catch {
            showToast('Failed to delete supplier', 'error');
        }
    };

    const handleSaveSupplier = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            if (editingSupplierId) {
                await api.put(`/suppliers/${editingSupplierId}`, formData);
                showToast('Supplier updated successfully', 'success');
            } else {
                await api.post('/suppliers', formData);
                showToast('Supplier added successfully', 'success');
            }
            setIsModalOpen(false);
            fetchSuppliers();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { error?: string } } };
            showToast(err.response?.data?.error || 'Failed to save supplier', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    const filteredSuppliers = suppliers.filter(s => 
        s.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        s.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        s.email?.toLowerCase().includes(searchTerm.toLowerCase())
    );

    if (loading && suppliers.length === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 p-6 lg:p-10">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 flex items-center gap-3">
                            <Truck className="text-indigo-600" />
                            Supplier Management
                        </h1>
                        <p className="text-slate-500 mt-1">Manage your vendors and procurement partners.</p>
                    </div>
                    <button 
                        onClick={handleOpenAddModal}
                        className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors shadow-sm font-medium"
                    >
                        <Plus size={18} />
                        Add Supplier
                    </button>
                </div>

                {/* Search */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
                    <div className="relative max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                        <input
                            type="text"
                            placeholder="Search suppliers..."
                            className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {filteredSuppliers.length > 0 ? (
                        filteredSuppliers.map((supplier) => (
                            <div key={supplier._id} className="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow p-6">
                                <div className="flex justify-between items-start mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                            <Truck size={20} />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-slate-900">{supplier.name}</h3>
                                            <span className="text-xs text-slate-400 font-mono">{supplier.code}</span>
                                        </div>
                                    </div>
                                    <span className={`px-2 py-1 text-[10px] font-bold rounded-full uppercase ${supplier.is_active ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-400'}`}>
                                        {supplier.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </div>

                                <div className="space-y-3 mb-6">
                                    <div className="flex items-center gap-2 text-sm text-slate-600">
                                        <User size={16} className="text-slate-400" />
                                        <span>{supplier.contact_person || 'No contact person'}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-slate-600">
                                        <Mail size={16} className="text-slate-400" />
                                        <a href={`mailto:${supplier.email}`} className="hover:text-indigo-600 transition-colors">
                                            {supplier.email || 'No email'}
                                        </a>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-slate-600">
                                        <Phone size={16} className="text-slate-400" />
                                        <span>{supplier.phone || 'No phone'}</span>
                                    </div>
                                    <div className="flex items-start gap-2 text-sm text-slate-600">
                                        <MapPin size={16} className="text-slate-400 mt-0.5 shrink-0" />
                                        <span className="line-clamp-2">{supplier.address || 'No address provided'}</span>
                                    </div>
                                </div>

                                <div className="flex border-t border-slate-100 pt-4 gap-2">
                                    <button 
                                        onClick={() => handleOpenEditModal(supplier)}
                                        className="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition-colors"
                                    >
                                        <Edit2 size={14} />
                                        Edit
                                    </button>
                                    <button 
                                        onClick={() => handleDeleteSupplier(supplier._id)}
                                        className="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    >
                                        <Trash2 size={14} />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-full py-20 text-center bg-white rounded-xl border border-dashed border-slate-300">
                            <Truck size={48} className="mx-auto text-slate-200 mb-4" />
                            <p className="text-slate-500">No suppliers found matching your search.</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                    <Plus size={20} />
                                </div>
                                <div>
                                    <h2 className="font-bold text-slate-900">{editingSupplierId ? 'Edit Supplier' : 'Add New Supplier'}</h2>
                                    <p className="text-xs text-slate-400">Fill in the supplier details below</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                <X size={20} className="text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveSupplier} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Supplier Name</label>
                                    <input 
                                        type="text" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="e.g. Balaji Trading"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Supplier Code</label>
                                    <input 
                                        type="text" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="e.g. BTC-001"
                                        value={formData.code}
                                        onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Contact Person</label>
                                    <input 
                                        type="text"
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Niteshwar Jindal"
                                        value={formData.contact_person}
                                        onChange={(e) => setFormData({ ...formData, contact_person: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                                    <select 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 bg-white"
                                        value={formData.is_active ? 'true' : 'false'}
                                        onChange={(e) => setFormData({ ...formData, is_active: e.target.value === 'true' })}
                                    >
                                        <option value="true">Active</option>
                                        <option value="false">Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                                    <input 
                                        type="email"
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="vendor@example.com"
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone</label>
                                    <input 
                                        type="tel"
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="+91 8107205038"
                                        value={formData.phone}
                                        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Address</label>
                                <textarea 
                                    rows={3}
                                    className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 resize-none"
                                    placeholder="Enter full address..."
                                    value={formData.address}
                                    onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                                />
                            </div>

                            <div className="pt-4 flex gap-3">
                                <button 
                                    type="button" onClick={() => setIsModalOpen(false)}
                                    className="flex-1 py-2 text-sm font-bold text-slate-500 hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit" disabled={isSubmitting}
                                    className="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition-colors flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20"
                                >
                                    {isSubmitting ? <Loader2 size={16} className="animate-spin" /> : <Plus size={16} />}
                                    {editingSupplierId ? 'Update Supplier' : 'Save Supplier'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default SupplierManagement;
