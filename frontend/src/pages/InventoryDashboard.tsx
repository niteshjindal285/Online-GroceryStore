import React, { useState, useEffect, useCallback } from 'react';
import api from '../api/config';
import {
    Plus, Search, Filter, Download, Edit2, Trash2,
    Boxes, Package, AlertTriangle, X, Loader2, CheckCircle
} from 'lucide-react';
import { useToast } from '../contexts/ToastContext';

interface Item {
    _id: string;
    name: string;
    description?: string;
    price: number;
    image?: string;
    category: string;
    rating?: number;
    discount?: number;
    inStock: boolean;
    countInStock: number;
}

const CATEGORIES = [
    'grocery', 'spices herbs', 'cooking oil', 'sugar-salt-jaggery',
    'flours grains', 'rice products', 'dals pulses', 'ghee vanaspati',
    'dry fruits-nuts', 'beverages', 'cleaning home-care', 'personal care'
];

const InventoryDashboard = () => {
    const { showToast } = useToast();
    const [items, setItems] = useState<Item[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');

    // Modal State
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingItemId, setEditingItemId] = useState<string | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        price: 0,
        image: 'https://media.dealshare.in/img/no-image.jpg',
        category: 'grocery',
        countInStock: 0,
        inStock: true
    });

    const fetchItems = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (searchTerm) params.append('search', searchTerm);
            if (selectedCategory) params.append('category', selectedCategory);
            const res = await api.get(`/inventory/items?${params.toString()}`);
            setItems(Array.isArray(res.data) ? res.data : []);
        } catch (err) {
            console.error('Error fetching inventory:', err);
            showToast('Failed to load inventory data', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast, searchTerm, selectedCategory]);

    useEffect(() => {
        fetchItems();
    }, [fetchItems]);

    const handleOpenAddModal = () => {
        setEditingItemId(null);
        setFormData({
            name: '',
            description: '',
            price: 0,
            image: 'https://media.dealshare.in/img/no-image.jpg',
            category: 'grocery',
            countInStock: 0,
            inStock: true
        });
        setIsModalOpen(true);
    };

    const handleOpenEditModal = (item: Item) => {
        setEditingItemId(item._id);
        setFormData({
            name: item.name,
            description: item.description || '',
            price: item.price,
            image: item.image || '',
            category: item.category,
            countInStock: item.countInStock,
            inStock: item.inStock
        });
        setIsModalOpen(true);
    };

    const handleDeleteItem = async (id: string) => {
        if (!window.confirm('Are you sure you want to delete this item?')) return;
        try {
            await api.delete(`/inventory/items/${id}`);
            showToast('Item deleted successfully', 'success');
            fetchItems();
        } catch {
            showToast('Failed to delete item', 'error');
        }
    };

    const handleSaveItem = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            const payload = {
                ...formData,
                inStock: formData.countInStock > 0 ? formData.inStock : false
            };
            if (editingItemId) {
                await api.put(`/inventory/items/${editingItemId}`, payload);
                showToast('Item updated successfully', 'success');
            } else {
                await api.post('/inventory/items', payload);
                showToast('Item added successfully', 'success');
            }
            setIsModalOpen(false);
            fetchItems();
        } catch (err: unknown) {
            const error = err as { response?: { data?: { error?: string } } };
            showToast(error.response?.data?.error || 'Failed to save item', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        fetchItems();
    };

    const getStockStyle = (item: Item) => {
        if (item.countInStock <= 0) return 'text-red-600 bg-red-50 border border-red-200';
        if (item.countInStock <= 10) return 'text-amber-600 bg-amber-50 border border-amber-200';
        return 'text-emerald-600 bg-emerald-50 border border-emerald-200';
    };

    const outOfStock = items.filter(i => i.countInStock <= 0).length;
    const lowStock = items.filter(i => i.countInStock > 0 && i.countInStock <= 10).length;

    if (loading && items.length === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
        );
    }

    const inputClass = 'w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm';

    return (
        <div className="min-h-screen bg-slate-50 p-6 lg:p-10">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 flex items-center gap-3">
                            <Boxes className="text-indigo-600" />
                            Inventory Management
                        </h1>
                        <p className="text-slate-500 mt-1">Manage your products, stock levels, and catalogue.</p>
                    </div>
                    <div className="flex gap-3">
                        <button
                            onClick={handleOpenAddModal}
                            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors shadow-sm font-medium"
                        >
                            <Plus size={18} />
                            Add Item
                        </button>
                        <button className="flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg transition-colors shadow-sm font-medium">
                            <Download size={18} />
                            Export
                        </button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-blue-50 text-blue-600 rounded-lg"><Package size={24} /></div>
                            <div>
                                <p className="text-sm text-slate-500 font-medium">Total Items</p>
                                <p className="text-2xl font-bold text-slate-900">{items.length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-red-50 text-red-600 rounded-lg"><AlertTriangle size={24} /></div>
                            <div>
                                <p className="text-sm text-slate-500 font-medium">Out of Stock</p>
                                <p className="text-2xl font-bold text-slate-900">{outOfStock}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-amber-50 text-amber-600 rounded-lg"><AlertTriangle size={24} /></div>
                            <div>
                                <p className="text-sm text-slate-500 font-medium">Low Stock (≤10)</p>
                                <p className="text-2xl font-bold text-slate-900">{lowStock}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
                    <form onSubmit={handleSearch} className="flex flex-col md:flex-row gap-4">
                        <div className="flex-1 relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                            <input
                                type="text"
                                placeholder="Search by name or description..."
                                className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                        <div className="w-full md:w-52 relative">
                            <Filter className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                            <select
                                className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 appearance-none bg-white"
                                value={selectedCategory}
                                onChange={(e) => setSelectedCategory(e.target.value)}
                            >
                                <option value="">All Categories</option>
                                {CATEGORIES.map(cat => (
                                    <option key={cat} value={cat}>{cat.replace(/-/g, ' ')}</option>
                                ))}
                            </select>
                        </div>
                        <button
                            type="submit"
                            className="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2 rounded-lg font-medium transition-colors"
                        >
                            Search
                        </button>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.length > 0 ? (
                                    items.map((item) => (
                                        <tr key={item._id} className="hover:bg-slate-50/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <img
                                                        src={item.image || 'https://media.dealshare.in/img/no-image.jpg'}
                                                        alt={item.name}
                                                        className="w-10 h-10 rounded-lg object-cover bg-slate-100 flex-shrink-0"
                                                        onError={(e) => { (e.target as HTMLImageElement).src = 'https://media.dealshare.in/img/no-image.jpg'; }}
                                                    />
                                                    <div>
                                                        <p className="font-semibold text-slate-900 truncate max-w-[180px]">{item.name}</p>
                                                        <p className="text-xs text-slate-400 truncate max-w-[180px]">{item.description || '—'}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="px-2 py-1 text-xs font-medium bg-slate-100 text-slate-600 rounded-md capitalize">
                                                    {item.category.replace(/-/g, ' ')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`text-sm font-bold px-2.5 py-1 rounded-full inline-block ${getStockStyle(item)}`}>
                                                    {item.countInStock} units
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="font-bold text-slate-900">₹{item.price.toFixed(2)}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ${item.inStock ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
                                                    {item.inStock
                                                        ? <><CheckCircle size={12} /> In Stock</>
                                                        : <><AlertTriangle size={12} /> Out of Stock</>
                                                    }
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        onClick={() => handleOpenEditModal(item)}
                                                        className="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                                    >
                                                        <Edit2 size={16} />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteItem(item._id)}
                                                        className="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                                    >
                                                        <Trash2 size={16} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-16 text-center">
                                            <Package size={48} className="mx-auto text-slate-200 mb-3" />
                                            <p className="text-slate-500 font-medium">No inventory items found.</p>
                                            <button onClick={handleOpenAddModal} className="mt-3 text-indigo-600 font-bold hover:underline text-sm">
                                                Add your first item
                                            </button>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[95vh] overflow-y-auto">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                    {editingItemId ? <Edit2 size={20} /> : <Plus size={20} />}
                                </div>
                                <div>
                                    <h2 className="font-bold text-slate-900">{editingItemId ? 'Edit Item' : 'Add New Item'}</h2>
                                    <p className="text-xs text-slate-400">Fill in the product information</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                <X size={20} className="text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveItem} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Product Name</label>
                                <input type="text" required className={inputClass}
                                    placeholder="e.g. Tata Salt 1kg"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
                                <textarea rows={2} className={inputClass + ' resize-none'}
                                    placeholder="Brief description..."
                                    value={formData.description}
                                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Price (₹)</label>
                                    <input type="number" required min="0" step="0.01" className={inputClass}
                                        value={formData.price}
                                        onChange={(e) => setFormData({ ...formData, price: parseFloat(e.target.value) || 0 })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Stock Qty</label>
                                    <input type="number" required min="0" className={inputClass}
                                        value={formData.countInStock}
                                        onChange={(e) => setFormData({ ...formData, countInStock: parseInt(e.target.value) || 0 })}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                                    <select className={inputClass + ' bg-white'}
                                        value={formData.category}
                                        onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                                    >
                                        {CATEGORIES.map(cat => (
                                            <option key={cat} value={cat}>{cat.replace(/-/g, ' ')}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Stock Status</label>
                                    <select className={inputClass + ' bg-white'}
                                        value={formData.inStock ? 'true' : 'false'}
                                        onChange={(e) => setFormData({ ...formData, inStock: e.target.value === 'true' })}
                                    >
                                        <option value="true">In Stock</option>
                                        <option value="false">Out of Stock</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Image URL</label>
                                <input type="url" className={inputClass}
                                    placeholder="https://..."
                                    value={formData.image}
                                    onChange={(e) => setFormData({ ...formData, image: e.target.value })}
                                />
                                {formData.image && (
                                    <div className="mt-2 rounded-lg overflow-hidden bg-slate-50 border h-20 flex items-center justify-center">
                                        <img src={formData.image} alt="Preview" className="h-full object-contain p-1"
                                            onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="pt-4 flex gap-3">
                                <button type="button" onClick={() => setIsModalOpen(false)}
                                    className="flex-1 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50 border border-slate-200 rounded-xl transition-colors"
                                >
                                    Cancel
                                </button>
                                <button type="submit" disabled={isSubmitting}
                                    className="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-colors flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20"
                                >
                                    {isSubmitting ? <Loader2 size={16} className="animate-spin" /> : <Plus size={16} />}
                                    {editingItemId ? 'Update Item' : 'Add to Inventory'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default InventoryDashboard;
