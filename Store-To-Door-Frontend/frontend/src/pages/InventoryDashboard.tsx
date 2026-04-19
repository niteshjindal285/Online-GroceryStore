import React, { useState, useEffect, useCallback } from 'react';
import api from '../api/config';
import { 
    Plus, Search, Filter, Download, Edit2, Trash2, 
    Boxes, Package, AlertTriangle, X, Loader2 
} from 'lucide-react';
import { useToast } from '../contexts/ToastContext';

interface Item {
    _id: string;
    name: string;
    code: string;
    barcode?: string;
    price: number;
    average_cost: number;
    countInStock: number;
    reorder_level: number;
    category_id?: { _id: string, name: string };
    unit_id?: { _id: string, code: string };
    supplier_id?: { _id: string, name: string };
    inStock: boolean;
}

interface Category {
    _id: string;
    name: string;
}

interface Unit {
    _id: string;
    code: string;
}

interface Supplier {
    _id: string;
    name: string;
}

const InventoryDashboard = () => {
    const { showToast } = useToast();
    const [items, setItems] = useState<Item[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const [categories, setCategories] = useState<Category[]>([]);
    const [units, setUnits] = useState<Unit[]>([]);
    const [suppliers, setSuppliers] = useState<Supplier[]>([]);

    // Modal State
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingItemId, setEditingItemId] = useState<string | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        code: '',
        barcode: '',
        price: 0,
        average_cost: 0,
        countInStock: 0,
        reorder_level: 0,
        category_id: '',
        unit_id: '',
        supplier_id: '',
        description: '',
        inStock: true
    });

    const fetchInitialData = useCallback(async () => {
        setLoading(true);
        try {
            const [itemsRes, catsRes, unitsRes, suppsRes] = await Promise.all([
                api.get('/inventory/items'),
                api.get('/inventory/categories'),
                api.get('/inventory/units'),
                api.get('/suppliers')
            ]);
            setItems(Array.isArray(itemsRes.data) ? itemsRes.data : []);
            setCategories(Array.isArray(catsRes.data) ? catsRes.data : []);
            setUnits(Array.isArray(unitsRes.data) ? unitsRes.data : []);
            setSuppliers(Array.isArray(suppsRes.data) ? suppsRes.data : []);
        } catch (err) {
            console.error('Error fetching inventory data:', err);
            showToast('Failed to load inventory data', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchInitialData();
    }, [fetchInitialData]);

    const handleOpenAddModal = () => {
        setEditingItemId(null);
        setFormData({
            name: '',
            code: '',
            barcode: '',
            price: 0,
            average_cost: 0,
            countInStock: 0,
            reorder_level: 0,
            category_id: '',
            unit_id: '',
            supplier_id: '',
            description: '',
            inStock: true
        });
        setIsModalOpen(true);
    };

    const handleOpenEditModal = (item: Item) => {
        setEditingItemId(item._id);
        setFormData({
            name: item.name,
            code: item.code,
            barcode: item.barcode || '',
            price: item.price,
            average_cost: item.average_cost,
            countInStock: item.countInStock,
            reorder_level: item.reorder_level,
            category_id: item.category_id?._id || '',
            unit_id: item.unit_id?._id || '',
            supplier_id: item.supplier_id?._id || '',
            description: '',
            inStock: item.inStock
        });
        setIsModalOpen(true);
    };

    const handleDeleteItem = async (id: string) => {
        if (!window.confirm('Are you sure you want to delete this item?')) return;
        try {
            await api.delete(`/inventory/items/${id}`);
            showToast('Item deleted successfully', 'success');
            fetchInitialData();
        } catch {
            showToast('Failed to delete item', 'error');
        }
    };

    const handleSaveItem = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            if (editingItemId) {
                await api.put(`/inventory/items/${editingItemId}`, formData);
                showToast('Item updated successfully', 'success');
            } else {
                await api.post('/inventory/items', formData);
                showToast('Item added successfully', 'success');
            }
            setIsModalOpen(false);
            fetchInitialData();
        } catch (err: unknown) {
            const error = err as { response?: { data?: { error?: string } } };
            showToast(error.response?.data?.error || 'Failed to save item', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        try {
            const res = await api.get(`/inventory/items?search=${searchTerm}&category=${selectedCategory}`);
            setItems(res.data);
        } catch (err) {
            console.error('Error searching inventory:', err);
        } finally {
            setLoading(false);
        }
    };

    const getStatusColor = (item: Item) => {
        if (item.countInStock <= 0) return 'text-red-600 bg-red-100';
        if (item.countInStock <= item.reorder_level) return 'text-yellow-600 bg-yellow-100';
        return 'text-green-600 bg-green-100';
    };

    if (loading && items.length === 0) {
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
                            <Boxes className="text-indigo-600" />
                            Inventory Management
                        </h1>
                        <p className="text-slate-500 mt-1">Manage your products, stock levels, and procurement.</p>
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
                            <div className="p-3 bg-blue-50 text-blue-600 rounded-lg">
                                <Package size={24} />
                            </div>
                            <div>
                                <p className="text-sm text-slate-500 font-medium cap">Total Items</p>
                                <p className="text-2xl font-bold text-slate-900">{items.length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-red-50 text-red-600 rounded-lg">
                                <AlertTriangle size={24} />
                            </div>
                            <div>
                                <p className="text-sm text-slate-500 font-medium">Out of Stock</p>
                                <p className="text-2xl font-bold text-slate-900">
                                    {items.filter(i => i.countInStock <= 0).length}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-yellow-50 text-yellow-600 rounded-lg">
                                <AlertTriangle size={24} />
                            </div>
                            <div>
                                <p className="text-sm text-slate-500 font-medium">Low Stock</p>
                                <p className="text-2xl font-bold text-slate-900">
                                    {items.filter(i => i.countInStock > 0 && i.countInStock <= i.reorder_level).length}
                                </p>
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
                                placeholder="Search by name, code, or barcode..."
                                className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                        <div className="w-full md:w-48 relative">
                            <Filter className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                            <select
                                className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 appearance-none bg-white"
                                value={selectedCategory}
                                onChange={(e) => setSelectedCategory(e.target.value)}
                            >
                                <option value="">All Categories</option>
                                {categories.map(cat => (
                                    <option key={cat._id} value={cat.name}>{cat.name}</option>
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
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Item Details</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock Level</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Price (Avg/Sell)</th>
                                    <th className="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {items.length > 0 ? (
                                    items.map((item) => (
                                        <tr key={item._id} className="hover:bg-slate-50/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <span className="font-semibold text-slate-900">{item.name}</span>
                                                    <span className="text-xs text-slate-400 font-mono">{item.code}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="px-2 py-1 text-xs font-medium bg-slate-100 text-slate-600 rounded-md">
                                                    {item.category_id?.name || 'General'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col">
                                                    <span className={`text-sm font-bold px-2 py-0.5 rounded-full inline-block w-fit ${getStatusColor(item)}`}>
                                                        {item.countInStock} {item.unit_id?.code || 'pcs'}
                                                    </span>
                                                    {item.countInStock <= item.reorder_level && (
                                                        <span className="text-[10px] text-red-500 mt-1 font-medium italic">Reorder needed!</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-col text-sm">
                                                    <span className="text-slate-400 font-medium">Cost: ₹{item.average_cost.toFixed(2)}</span>
                                                    <span className="text-slate-900 font-bold">Sell: ₹{item.price.toFixed(2)}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button 
                                                        onClick={() => handleOpenEditModal(item)}
                                                        className="p-2 text-slate-400 hover:text-indigo-600 transition-colors"
                                                    >
                                                        <Edit2 size={16} />
                                                    </button>
                                                    <button 
                                                        onClick={() => handleDeleteItem(item._id)}
                                                        className="p-2 text-slate-400 hover:text-red-500 transition-colors"
                                                    >
                                                        <Trash2 size={16} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                                            <div className="flex flex-col items-center gap-2">
                                                <Package size={48} className="text-slate-200" />
                                                <p>No inventory items found matches your criteria.</p>
                                            </div>
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
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[95vh] overflow-y-auto">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                    <Plus size={20} />
                                </div>
                                <div>
                                    <h2 className="font-bold text-slate-900">{editingItemId ? 'Edit Item' : 'Add New Item'}</h2>
                                    <p className="text-xs text-slate-400">Update your product information</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                <X size={20} className="text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveItem} className="p-6 space-y-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="col-span-2">
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Product Name</label>
                                    <input 
                                        type="text" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="e.g. Fresh Milk 1L"
                                        value={formData.name}
                                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Item Code</label>
                                    <input 
                                        type="text" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="MLK-001"
                                        value={formData.code}
                                        onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Barcode</label>
                                    <input 
                                        type="text"
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Scan or enter barcode"
                                        value={formData.barcode}
                                        onChange={(e) => setFormData({ ...formData, barcode: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Selling Price (₹)</label>
                                    <input 
                                        type="number" step="0.01" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        value={formData.price}
                                        onChange={(e) => setFormData({ ...formData, price: parseFloat(e.target.value) })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Average Cost (₹)</label>
                                    <input 
                                        type="number" step="0.01" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        value={formData.average_cost}
                                        onChange={(e) => setFormData({ ...formData, average_cost: parseFloat(e.target.value) })}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                                    <select 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 bg-white"
                                        value={formData.category_id}
                                        onChange={(e) => setFormData({ ...formData, category_id: e.target.value })}
                                    >
                                        <option value="">Select Category</option>
                                        {categories.map(cat => (
                                            <option key={cat._id} value={cat._id}>{cat.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Unit</label>
                                    <select 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 bg-white"
                                        value={formData.unit_id}
                                        onChange={(e) => setFormData({ ...formData, unit_id: e.target.value })}
                                    >
                                        <option value="">Select Unit</option>
                                        {units.map(u => (
                                            <option key={u._id} value={u._id}>{u.code}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Supplier</label>
                                    <select 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 bg-white"
                                        value={formData.supplier_id}
                                        onChange={(e) => setFormData({ ...formData, supplier_id: e.target.value })}
                                    >
                                        <option value="">Select Supplier</option>
                                        {suppliers.map(s => (
                                            <option key={s._id} value={s._id}>{s.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Stock Level</label>
                                    <input 
                                        type="number" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        value={formData.countInStock}
                                        onChange={(e) => setFormData({ ...formData, countInStock: parseInt(e.target.value) })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reorder Level</label>
                                    <input 
                                        type="number" required 
                                        className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        value={formData.reorder_level}
                                        onChange={(e) => setFormData({ ...formData, reorder_level: parseInt(e.target.value) })}
                                    />
                                </div>
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

