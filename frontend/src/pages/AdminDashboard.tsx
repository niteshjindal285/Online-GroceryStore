import React, { useState, useEffect } from 'react';
import {
  Package, ShoppingCart, Users, TrendingUp,
  Plus, Edit, Trash2, X, Loader2, AlertCircle,
  LayoutDashboard, Settings, ChevronRight, Search, BarChart3
} from 'lucide-react';
import { getProducts, addProduct, editProduct, deleteProduct } from '../utils/productUtils';
import { Product } from '../data/mockProducts';
import api from '../api/config';
import { useToast } from '../contexts/ToastContext';

const inputClass =
  'w-full px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 hover:border-gray-300 transition-all duration-200 placeholder-gray-400';

const AdminDashboard: React.FC = () => {
  const [activeTab, setActiveTab] = useState('overview');
  const { showToast } = useToast();

  const [products, setProducts] = useState<Product[]>([]);
  const [productSearch, setProductSearch] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [newProduct, setNewProduct] = useState({
    name: '', category: 'grocery', price: 0, stock: 100,
    image: 'https://media.dealshare.in/img/no-image.jpg', inStock: true
  });

  const [isLoadingStats, setIsLoadingStats] = useState(true);
  const [statsData, setStatsData] = useState({
    totalOrders: 0, activeUsers: 0, totalRevenue: 0, recentOrders: [] as any[]
  });

  const totalProducts = products.length;
  const { totalOrders, activeUsers, totalRevenue, recentOrders } = statsData;

  const stats = [
    {
      name: 'Total Products', value: totalProducts.toLocaleString(),
      icon: Package, gradient: 'from-emerald-500 to-teal-500',
      glow: 'shadow-emerald-500/20', bg: 'bg-emerald-50', text: 'text-emerald-600',
      sub: 'In catalogue'
    },
    {
      name: 'Total Orders', value: totalOrders.toLocaleString(),
      icon: ShoppingCart, gradient: 'from-blue-500 to-indigo-500',
      glow: 'shadow-blue-500/20', bg: 'bg-blue-50', text: 'text-blue-600',
      sub: 'All time'
    },
    {
      name: 'Active Users', value: activeUsers.toLocaleString(),
      icon: Users, gradient: 'from-purple-500 to-violet-500',
      glow: 'shadow-purple-500/20', bg: 'bg-purple-50', text: 'text-purple-600',
      sub: 'Registered'
    },
    {
      name: 'Revenue', value: `₹${totalRevenue.toLocaleString()}`,
      icon: TrendingUp, gradient: 'from-amber-500 to-orange-500',
      glow: 'shadow-amber-500/20', bg: 'bg-amber-50', text: 'text-amber-600',
      sub: 'Total earned'
    },
  ];

  const tabs = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'products', label: 'Products', icon: Package },
    { id: 'orders', label: 'Orders', icon: ShoppingCart },
    { id: 'users', label: 'Users', icon: Users },
  ];

  useEffect(() => {
    const fetchProducts = async () => {
      try {
        const data = await getProducts();
        setProducts(data);
      } catch (error) {
        console.error('Error fetching products:', error);
        showToast('Failed to load products', 'error');
      }
    };
    fetchProducts();
  }, [showToast]);

  useEffect(() => {
    if (activeTab !== 'overview') return;
    const fetchStats = async () => {
      setIsLoadingStats(true);
      try {
        const ordersResponse = await api.get('/orders');
        const orders = Array.isArray(ordersResponse.data) ? ordersResponse.data : [];
        const revenue = orders.reduce((sum: number, o: any) => sum + (o.totalPrice || 0), 0);
        const recentOrdersData = orders.slice(0, 5).map((o: any) => ({
          id: o._id || o.id,
          customer: o.shippingAddress?.name || 'Customer',
          items: o.items?.length || 0,
          total: o.totalPrice || 0,
          status: o.status || 'pending'
        }));
        setStatsData({ totalOrders: orders.length, activeUsers: 0, totalRevenue: revenue, recentOrders: recentOrdersData });
      } catch {
        setStatsData({ totalOrders: 0, activeUsers: 0, totalRevenue: 0, recentOrders: [] });
      } finally {
        setIsLoadingStats(false);
      }
    };
    fetchStats();
  }, [activeTab]);

  const handleOpenAddModal = () => {
    setEditingId(null);
    setNewProduct({ name: '', category: 'grocery', price: 0, stock: 100, image: 'https://media.dealshare.in/img/no-image.jpg', inStock: true });
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (product: Product) => {
    setEditingId(product.id);
    setNewProduct({ name: product.name, category: product.category, price: product.price, stock: product.inStock ? 100 : 0, image: product.image, inStock: product.inStock });
    setIsModalOpen(true);
  };

  const handleDeleteProduct = async (id: string) => {
    if (!window.confirm('Are you sure you want to delete this product?')) return;
    try {
      await deleteProduct(id);
      setProducts(await getProducts());
      showToast('Product deleted successfully', 'success');
    } catch {
      showToast('Failed to delete product', 'error');
    }
  };

  const handleSaveProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const product: any = {
        ...(editingId ? { id: editingId } : {}),
        name: newProduct.name, category: newProduct.category,
        price: newProduct.price, image: newProduct.image,
        rating: 4.5, discount: 0, inStock: newProduct.inStock
      };
      if (editingId) { await editProduct(editingId, product); showToast('Product updated successfully', 'success'); }
      else { await addProduct(product); showToast('Product added successfully', 'success'); }
      setProducts(await getProducts());
      setIsModalOpen(false);
    } catch {
      showToast('Failed to save product', 'error');
    }
  };

  const getStatusStyle = (status: string) => {
    switch (status) {
      case 'delivered': return 'bg-emerald-100 text-emerald-700 border border-emerald-200';
      case 'preparing': return 'bg-amber-100 text-amber-700 border border-amber-200';
      case 'confirmed': return 'bg-blue-100 text-blue-700 border border-blue-200';
      default: return 'bg-gray-100 text-gray-600 border border-gray-200';
    }
  };

  const filteredProducts = products.filter(p =>
    p.name.toLowerCase().includes(productSearch.toLowerCase()) ||
    p.category.toLowerCase().includes(productSearch.toLowerCase())
  );

  // ── Shared table header style
  const th = 'px-5 py-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-widest';
  const td = 'px-5 py-4 whitespace-nowrap text-sm text-gray-700';

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {/* ── Header ── */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <div className="flex items-center gap-2 text-xs text-gray-400 mb-1">
              <span>Dashboard</span>
              <ChevronRight className="h-3 w-3" />
              <span className="text-emerald-600 font-medium capitalize">{activeTab}</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 font-display flex items-center gap-3">
              <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
                <BarChart3 className="h-5 w-5 text-white" />
              </div>
              Admin Dashboard
            </h1>
          </div>
          {activeTab === 'products' && (
            <button
              onClick={handleOpenAddModal}
              className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-semibold px-5 py-2.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 text-sm"
            >
              <Plus className="h-4 w-4" /> Add Product
            </button>
          )}
        </div>

        {/* ── Stats Cards ── */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          {stats.map((stat, i) => (
            <div key={i} className="bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
              <div className="flex items-start justify-between mb-4">
                <div className={`${stat.bg} ${stat.text} p-3 rounded-xl`}>
                  <stat.icon className="h-5 w-5" />
                </div>
                <span className="text-xs text-gray-400 font-medium">{stat.sub}</span>
              </div>
              {isLoadingStats && i > 0 ? (
                <div className="h-8 w-24 bg-gray-100 rounded-lg animate-pulse mb-1" />
              ) : (
                <p className="text-2xl font-extrabold text-gray-900 mb-0.5">{stat.value}</p>
              )}
              <p className="text-sm text-gray-500">{stat.name}</p>
            </div>
          ))}
        </div>

        {/* ── Tabs ── */}
        <div className="mb-6">
          <div className="flex items-center gap-1 bg-white border border-gray-100 p-1 rounded-2xl w-fit shadow-sm">
            {tabs.map(({ id, label, icon: Icon }) => (
              <button
                key={id}
                onClick={() => setActiveTab(id)}
                className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-300 ${activeTab === id
                    ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-md shadow-emerald-500/20'
                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                  }`}
              >
                <Icon className="h-4 w-4" />
                <span className="hidden sm:inline">{label}</span>
              </button>
            ))}
          </div>
        </div>

        {/* ── Overview Tab ── */}
        {activeTab === 'overview' && (
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
              <div>
                <h2 className="text-lg font-bold text-gray-900">Recent Orders</h2>
                <p className="text-xs text-gray-400 mt-0.5">Latest 5 orders from customers</p>
              </div>
              <span className="text-xs bg-emerald-100 text-emerald-700 font-semibold px-3 py-1 rounded-full">
                Live
              </span>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full">
                <thead>
                  <tr className="bg-gray-50/80 border-b border-gray-100">
                    <th className={th}>Order ID</th>
                    <th className={th}>Customer</th>
                    <th className={th}>Items</th>
                    <th className={th}>Total</th>
                    <th className={th}>Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {isLoadingStats ? (
                    <tr>
                      <td colSpan={5} className="px-6 py-12 text-center">
                        <Loader2 className="h-6 w-6 animate-spin mx-auto text-emerald-500" />
                        <p className="text-sm text-gray-400 mt-2">Loading orders...</p>
                      </td>
                    </tr>
                  ) : recentOrders.length > 0 ? (
                    recentOrders.map((order) => (
                      <tr key={order.id} className="hover:bg-gray-50/60 transition-colors">
                        <td className={td}>
                          <span className="font-mono font-semibold text-gray-500">#{order.id.slice(-6).toUpperCase()}</span>
                        </td>
                        <td className={td}>
                          <div className="flex items-center gap-2.5">
                            <div className="w-7 h-7 rounded-full bg-gradient-to-br from-emerald-400 to-teal-400 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                              {order.customer[0]?.toUpperCase()}
                            </div>
                            <span className="font-medium text-gray-800">{order.customer}</span>
                          </div>
                        </td>
                        <td className={td}><span className="font-medium">{order.items}</span> items</td>
                        <td className={td}><span className="font-bold text-gray-900">₹{order.total.toFixed(2)}</span></td>
                        <td className={td}>
                          <span className={`px-2.5 py-1 text-xs font-semibold rounded-full ${getStatusStyle(order.status)}`}>
                            {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                          </span>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="px-6 py-16 text-center">
                        <div className="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                          <AlertCircle className="h-7 w-7 text-gray-400" />
                        </div>
                        <p className="text-gray-500 font-medium">No orders found</p>
                        <p className="text-gray-400 text-sm mt-1">Orders will appear here once placed</p>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* ── Products Tab ── */}
        {activeTab === 'products' && (
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
              <div>
                <h2 className="text-lg font-bold text-gray-900">Product Management</h2>
                <p className="text-xs text-gray-400 mt-0.5">{products.length} products in catalogue</p>
              </div>
              {/* Search */}
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search products..."
                  value={productSearch}
                  onChange={(e) => setProductSearch(e.target.value)}
                  className="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 hover:border-gray-300 transition-all"
                />
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full">
                <thead>
                  <tr className="bg-gray-50/80 border-b border-gray-100">
                    <th className={th}>Product</th>
                    <th className={th}>Category</th>
                    <th className={th}>Price</th>
                    <th className={th}>Stock</th>
                    <th className={th}>Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {filteredProducts.length > 0 ? filteredProducts.map((product) => (
                    <tr key={product.id} className="hover:bg-gray-50/60 transition-colors group">
                      <td className={td}>
                        <div className="flex items-center gap-3">
                          <img src={product.image} alt={product.name} className="w-10 h-10 rounded-xl object-cover bg-gray-100 flex-shrink-0" />
                          <span className="font-semibold text-gray-800 truncate max-w-[180px]">{product.name}</span>
                        </div>
                      </td>
                      <td className={td}>
                        <span className="bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-lg capitalize">
                          {product.category.replace(/-/g, ' ')}
                        </span>
                      </td>
                      <td className={td}><span className="font-bold text-gray-900">₹{product.price}</span></td>
                      <td className={td}>
                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ${product.inStock ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-red-100 text-red-700 border border-red-200'}`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${product.inStock ? 'bg-emerald-500' : 'bg-red-500'}`} />
                          {product.inStock ? 'In Stock' : 'Out of Stock'}
                        </span>
                      </td>
                      <td className={td}>
                        <div className="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                          <button
                            onClick={() => handleOpenEditModal(product)}
                            className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition-colors"
                            title="Edit"
                          >
                            <Edit className="h-3.5 w-3.5" />
                          </button>
                          <button
                            onClick={() => handleDeleteProduct(product.id)}
                            className="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center transition-colors"
                            title="Delete"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )) : (
                    <tr>
                      <td colSpan={5} className="px-6 py-12 text-center text-gray-400 text-sm">
                        No products match your search.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* ── Orders Tab ── */}
        {activeTab === 'orders' && (
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div className="px-6 py-5 border-b border-gray-100">
              <h2 className="text-lg font-bold text-gray-900">All Orders</h2>
              <p className="text-xs text-gray-400 mt-0.5">Full order management panel</p>
            </div>
            <div className="py-20 text-center">
              <div className="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <ShoppingCart className="h-8 w-8 text-gray-400" />
              </div>
              <p className="text-gray-500 font-semibold">Order Management Coming Soon</p>
              <p className="text-gray-400 text-sm mt-1">Full order management is under development</p>
            </div>
          </div>
        )}

        {/* ── Users Tab ── */}
        {activeTab === 'users' && (
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div className="px-6 py-5 border-b border-gray-100">
              <h2 className="text-lg font-bold text-gray-900">User Management</h2>
              <p className="text-xs text-gray-400 mt-0.5">Manage registered users</p>
            </div>
            <div className="py-20 text-center">
              <div className="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <Users className="h-8 w-8 text-gray-400" />
              </div>
              <p className="text-gray-500 font-semibold">User Management Coming Soon</p>
              <p className="text-gray-400 text-sm mt-1">Full user management is under development</p>
            </div>
          </div>
        )}
      </div>

      {/* ── Add / Edit Product Modal ── */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md max-h-[92vh] overflow-y-auto">
            {/* Modal Header */}
            <div className="px-7 py-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 rounded-t-3xl">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
                  {editingId ? <Edit className="h-4 w-4 text-white" /> : <Plus className="h-4 w-4 text-white" />}
                </div>
                <div>
                  <h2 className="text-lg font-bold text-gray-900">{editingId ? 'Edit Product' : 'Add New Product'}</h2>
                  <p className="text-xs text-gray-400">{editingId ? 'Update product details' : 'Fill in the product details below'}</p>
                </div>
              </div>
              <button onClick={() => setIsModalOpen(false)} className="w-9 h-9 rounded-xl hover:bg-gray-100 flex items-center justify-center transition-colors">
                <X className="h-5 w-5 text-gray-500" />
              </button>
            </div>

            <form onSubmit={handleSaveProduct} className="p-7 space-y-5">
              <div>
                <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Product Name</label>
                <input type="text" required value={newProduct.name}
                  onChange={(e) => setNewProduct({ ...newProduct, name: e.target.value })}
                  className={inputClass} placeholder="e.g. Tata Salt 1kg" />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Price (₹)</label>
                  <input type="number" required min="0" step="0.01" value={newProduct.price}
                    onChange={(e) => setNewProduct({ ...newProduct, price: parseFloat(e.target.value) })}
                    className={inputClass} />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Initial Stock</label>
                  <input type="number" required min="0" value={newProduct.stock}
                    onChange={(e) => setNewProduct({ ...newProduct, stock: parseInt(e.target.value) })}
                    className={inputClass} />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Category</label>
                  <select value={newProduct.category}
                    onChange={(e) => setNewProduct({ ...newProduct, category: e.target.value })}
                    className={inputClass}>
                    {['spices herbs', 'cooking oil', 'sugar-salt-jaggery', 'flours grains', 'rice products', 'dals pulses', 'ghee vanaspati', 'dry fruits-nuts', 'beverages', 'cleaning home-care', 'personal care', 'grocery'].map(cat => (
                      <option key={cat} value={cat}>{cat.replace(/-/g, ' ')}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Stock Status</label>
                  <select value={newProduct.inStock ? 'true' : 'false'}
                    onChange={(e) => setNewProduct({ ...newProduct, inStock: e.target.value === 'true' })}
                    className={inputClass}>
                    <option value="true">In Stock</option>
                    <option value="false">Out of Stock</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Image URL</label>
                <input type="url" value={newProduct.image}
                  onChange={(e) => setNewProduct({ ...newProduct, image: e.target.value })}
                  className={inputClass} placeholder="https://..." />
              </div>

              {/* Image preview */}
              {newProduct.image && (
                <div className="rounded-xl overflow-hidden bg-gray-50 border border-gray-100 h-32 flex items-center justify-center">
                  <img src={newProduct.image} alt="Preview" className="h-full w-full object-contain p-2" onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                </div>
              )}

              <div className="pt-2 flex gap-3">
                <button type="button" onClick={() => setIsModalOpen(false)}
                  className="flex-1 py-2.5 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 font-semibold text-sm transition-colors">
                  Cancel
                </button>
                <button type="submit"
                  className="flex-1 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white rounded-xl font-semibold text-sm transition-all duration-300 shadow-md shadow-emerald-500/25 flex items-center justify-center gap-2">
                  <Settings className="h-4 w-4" />
                  {editingId ? 'Update Product' : 'Save Product'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminDashboard;