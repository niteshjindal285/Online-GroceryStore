import React, { useState, useEffect, useCallback } from 'react';
import api from '../api/config';
import { 
    Plus, Search, Edit2, Trash2, Tag, 
    X, Loader2 
} from 'lucide-react';
import { useToast } from '../contexts/ToastContext';

interface Category {
    _id: string;
    name: string;
    description?: string;
    is_active: boolean;
}

const CategoryManagement: React.FC = () => {
    const { showToast } = useToast();
    const [categories, setCategories] = useState<Category[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    
    // Modal State
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingCategoryId, setEditingCategoryId] = useState<string | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        is_active: true
    });

    const fetchCategories = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/inventory/categories');
            setCategories(Array.isArray(res.data) ? res.data : []);
        } catch (error: unknown) {
            console.error('Error fetching categories:', error);
            showToast('Failed to load categories', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchCategories();
    }, [fetchCategories]);

    const handleOpenAddModal = () => {
        setEditingCategoryId(null);
        setFormData({
            name: '',
            description: '',
            is_active: true
        });
        setIsModalOpen(true);
    };

    const handleOpenEditModal = (category: Category) => {
        setEditingCategoryId(category._id);
        setFormData({
            name: category.name,
            description: category.description || '',
            is_active: category.is_active
        });
        setIsModalOpen(true);
    };

    const handleDeleteCategory = async (id: string) => {
        if (!window.confirm('Are you sure you want to delete this category? This may affect items assigned to it.')) return;
        try {
            await api.delete(`/inventory/categories/${id}`);
            showToast('Category deleted successfully', 'success');
            fetchCategories();
        } catch (error: unknown) {
            console.error('Delete error:', error);
            showToast('Failed to delete category', 'error');
        }
    };

    const handleSaveCategory = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            if (editingCategoryId) {
                await api.put(`/inventory/categories/${editingCategoryId}`, formData);
                showToast('Category updated successfully', 'success');
            } else {
                await api.post('/inventory/categories', formData);
                showToast('Category added successfully', 'success');
            }
            setIsModalOpen(false);
            fetchCategories();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { error?: string } } };
            showToast(err.response?.data?.error || 'Failed to save category', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    const filteredCategories = categories.filter(c => 
        c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (c.description && c.description.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    if (loading && categories.length === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 text-indigo-600">
                <Loader2 className="animate-spin" size={40} />
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
                            <Tag className="text-indigo-600" />
                            Category Management
                        </h1>
                        <p className="text-slate-500 mt-1">Organize your products into meaningful groupings.</p>
                    </div>
                    <button 
                        onClick={handleOpenAddModal}
                        className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors shadow-sm font-medium"
                    >
                        <Plus size={18} />
                        Add Category
                    </button>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex items-center justify-between">
                    <div className="relative max-w-md w-full">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                        <input
                            type="text"
                            placeholder="Search categories..."
                            className="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {filteredCategories.length > 0 ? (
                        filteredCategories.map((category) => (
                            <div key={category._id} className="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow p-6 flex flex-col h-full">
                                <div className="flex justify-between items-start mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                            <Tag size={20} />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-slate-900">{category.name}</h3>
                                            <span className={`px-2 py-0.5 text-[10px] font-bold rounded-full uppercase ${category.is_active ? 'bg-green-50 text-green-600' : 'bg-slate-50 text-slate-400'}`}>
                                                {category.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <p className="text-sm text-slate-500 flex-grow mb-6 line-clamp-2">
                                    {category.description || 'No description provided.'}
                                </p>

                                <div className="flex gap-2 pt-4 border-t border-slate-50">
                                    <button 
                                        onClick={() => handleOpenEditModal(category)}
                                        className="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 border border-transparent hover:border-slate-100 rounded-lg transition-all"
                                    >
                                        <Edit2 size={14} />
                                        Edit
                                    </button>
                                    <button 
                                        onClick={() => handleDeleteCategory(category._id)}
                                        className="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-medium text-red-600 hover:bg-red-50 border border-transparent hover:border-red-100 rounded-lg transition-all"
                                    >
                                        <Trash2 size={14} />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="col-span-full py-20 text-center bg-white rounded-xl border border-dashed border-slate-300">
                            <Tag size={48} className="mx-auto text-slate-200 mb-4" />
                            <p className="text-slate-500">No categories found matching your search.</p>
                            <button onClick={handleOpenAddModal} className="mt-4 text-indigo-600 font-bold hover:underline">Add your first category</button>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                    <Plus size={20} />
                                </div>
                                <div>
                                    <h2 className="font-bold text-slate-900">{editingCategoryId ? 'Edit Category' : 'Add New Category'}</h2>
                                    <p className="text-xs text-slate-400">Define a product classification</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                <X size={20} className="text-slate-400" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveCategory} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category Name</label>
                                <input 
                                    type="text" required 
                                    className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                    placeholder="e.g. Vegetables, Dairy, Bakery"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
                                <textarea 
                                    rows={3}
                                    className="w-full px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 resize-none"
                                    placeholder="Brief details about this category..."
                                    value={formData.description}
                                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
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
                                    {editingCategoryId ? 'Update Category' : 'Save Category'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default CategoryManagement;
