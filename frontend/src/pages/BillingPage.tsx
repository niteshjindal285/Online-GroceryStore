import React, { useState, useEffect, useCallback, useMemo } from 'react';
import api from '../api/config';
import { 
    Search, Plus, Minus, Trash2, Receipt, 
    User, Package, Calculator, CheckCircle2, 
    X, Loader2, Printer, Save, AlertCircle
} from 'lucide-react';
import ReceiptTemplate from '../components/ReceiptTemplate';
import { useToast } from '../contexts/ToastContext';

interface Product {
    _id: string;
    name: string;
    code: string;
    price: number;
    countInStock: number;
    unit_id?: { code: string };
}

interface Customer {
    _id: string;
    name: string;
    code: string;
    phone?: string;
}

interface CartItem {
    item_id: string;
    name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    stock: number;
}

const BillingPage: React.FC = () => {
    const { showToast } = useToast();
    
    // Data state
    const [products, setProducts] = useState<Product[]>([]);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(true);
    
    // UI state
    const [productSearch, setProductSearch] = useState('');
    const [customerSearch, setCustomerSearch] = useState('');
    const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
    const [cart, setCart] = useState<CartItem[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [invoiceSuccess, setInvoiceSuccess] = useState<any | null>(null);
    const [showReceipt, setShowReceipt] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const [prodRes, custRes] = await Promise.all([
                api.get('/inventory/items'),
                api.get('/customers')
            ]);
            setProducts(Array.isArray(prodRes.data) ? prodRes.data : []);
            setCustomers(Array.isArray(custRes.data) ? custRes.data : []);
        } catch (error: unknown) {
            console.error('Fetch error:', error);
            showToast('Failed to load billing data', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Derived values
    const subtotal = useMemo(() => cart.reduce((sum, item) => sum + item.line_total, 0), [cart]);
    const taxRate = 0.18; // 18% GST example
    const taxAmount = subtotal * taxRate;
    const totalAmount = subtotal + taxAmount;

    const filteredProducts = useMemo(() => 
        products.filter(p => 
            p.name.toLowerCase().includes(productSearch.toLowerCase()) || 
            p.code.toLowerCase().includes(productSearch.toLowerCase())
        ).slice(0, 8),
        [products, productSearch]
    );

    const filteredCustomers = useMemo(() => 
        customers.filter(c => 
            c.name.toLowerCase().includes(customerSearch.toLowerCase()) || 
            c.code.toLowerCase().includes(customerSearch.toLowerCase())
        ).slice(0, 5),
        [customers, customerSearch]
    );

    // Handlers
    const addToCart = (product: Product) => {
        const existing = cart.find(c => c.item_id === product._id);
        if (existing) {
            if (existing.quantity >= product.countInStock) {
                showToast('Not enough stock available', 'warning');
                return;
            }
            setCart(cart.map(c => 
                c.item_id === product._id 
                ? { ...c, quantity: c.quantity + 1, line_total: (c.quantity + 1) * c.unit_price } 
                : c
            ));
        } else {
            if (product.countInStock <= 0) {
                showToast('Item is out of stock', 'warning');
                return;
            }
            setCart([...cart, {
                item_id: product._id,
                name: product.name,
                quantity: 1,
                unit_price: product.price,
                line_total: product.price,
                stock: product.countInStock
            }]);
        }
    };

    const updateQuantity = (itemId: string, delta: number) => {
        setCart(cart.map(item => {
            if (item.item_id === itemId) {
                const newQty = Math.max(1, item.quantity + delta);
                if (delta > 0 && newQty > item.stock) {
                    showToast('Max stock reached', 'warning');
                    return item;
                }
                return { ...item, quantity: newQty, line_total: newQty * item.unit_price };
            }
            return item;
        }));
    };

    const removeFromCart = (itemId: string) => {
        setCart(cart.filter(item => item.item_id !== itemId));
    };

    const handleCreateBill = async () => {
        if (!selectedCustomer) {
            showToast('Please select a customer first', 'error');
            return;
        }
        if (cart.length === 0) {
            showToast('Cart is empty', 'error');
            return;
        }

        setIsSubmitting(true);
        try {
            const invoiceData = {
                invoice_number: `INV-${Date.now().toString().slice(-8)}`,
                customer_id: selectedCustomer._id,
                items: cart,
                subtotal,
                tax_amount: taxAmount,
                total_amount: totalAmount,
                status: 'paid'
            };

            const res = await api.post('/invoices', invoiceData);
            
            // Re-fetch populated invoice for receipt
            const fullInv = await api.get(`/invoices/${res.data._id}`);
            setInvoiceSuccess(fullInv.data);
            
            setCart([]);
            setSelectedCustomer(null);
            showToast('Bill created successfully!', 'success');
            fetchData(); // Refresh stock levels
        } catch (error: unknown) {
            console.error('Invoice error:', error);
            showToast('Failed to create bill', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (loading && products.length === 0) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="flex flex-col items-center gap-4">
                    <Loader2 className="animate-spin text-indigo-600" size={40} />
                    <p className="text-slate-500 font-medium">Initializing POS System...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-100 p-4 lg:p-6 text-slate-900">
            <div className="max-w-[1600px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-80px)]">
                
                {/* Left Side: Product Selection */}
                <div className="lg:col-span-8 flex flex-col gap-6 overflow-hidden">
                    {/* Search & Header */}
                    <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <div className="flex items-center justify-between mb-6">
                            <h1 className="text-2xl font-black text-slate-900 flex items-center gap-3">
                                <Receipt className="text-indigo-600" size={32} />
                                Point of Sale
                            </h1>
                            <div className="px-4 py-1.5 bg-indigo-50 text-indigo-700 rounded-full text-xs font-bold uppercase tracking-widest border border-indigo-100">
                                Terminal #01
                            </div>
                        </div>

                        <div className="relative">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" size={20} />
                            <input 
                                type="text"
                                placeholder="Scan Barcode or Search Product Name/Code..."
                                className="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-lg font-medium"
                                value={productSearch}
                                onChange={(e) => setProductSearch(e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Product Grid */}
                    <div className="flex-1 overflow-y-auto pr-2 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 pb-4">
                        {filteredProducts.map(product => (
                            <button 
                                key={product._id}
                                onClick={() => addToCart(product)}
                                className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-indigo-300 hover:shadow-md transition-all text-left flex flex-col group relative overflow-hidden active:scale-95"
                            >
                                <div className="absolute top-0 right-0 p-2 opacity-0 group-hover:opacity-100 translate-x-2 group-hover:translate-x-0 transition-all">
                                    <div className="bg-indigo-600 text-white p-1 rounded-lg">
                                        <Plus size={16} />
                                    </div>
                                </div>
                                <div className="mb-3">
                                    <div className="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-colors">
                                        <Package size={24} />
                                    </div>
                                </div>
                                <h3 className="font-bold text-slate-800 line-clamp-1 mb-1">{product.name}</h3>
                                <p className="text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">{product.code}</p>
                                <div className="mt-auto flex items-center justify-between">
                                    <span className="text-lg font-black text-indigo-600">₹{product.price.toFixed(2)}</span>
                                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${product.countInStock > 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
                                        {product.countInStock} In Stock
                                    </span>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Right Side: Billing Summary */}
                <div className="lg:col-span-4 flex flex-col gap-6 h-full min-h-[600px]">
                    
                    {/* Customer Selection */}
                    <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 ml-1 flex items-center gap-2">
                            <User size={12} />
                            Customer Selection
                        </label>
                        {!selectedCustomer ? (
                            <div className="relative group">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 transition-colors group-focus-within:text-indigo-500" size={16} />
                                <input 
                                    type="text"
                                    placeholder="Search Customer..."
                                    className="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-sm font-medium"
                                    value={customerSearch}
                                    onChange={(e) => setCustomerSearch(e.target.value)}
                                />
                                {customerSearch && (
                                    <div className="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-xl border border-slate-100 z-10 overflow-hidden animate-in fade-in slide-in-from-top-2">
                                        {filteredCustomers.map(c => (
                                            <button 
                                                key={c._id}
                                                onClick={() => { setSelectedCustomer(c); setCustomerSearch(''); }}
                                                className="w-full px-4 py-3 text-left hover:bg-slate-50 flex flex-col border-b border-slate-50 last:border-0"
                                            >
                                                <span className="text-sm font-bold text-slate-900">{c.name}</span>
                                                <span className="text-[10px] text-slate-400 font-mono">{c.code} • {c.phone}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="flex items-center justify-between p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 bg-white text-indigo-600 rounded-lg shadow-sm">
                                        <User size={20} />
                                    </div>
                                    <div>
                                        <p className="font-bold text-slate-900 text-sm leading-none mb-1">{selectedCustomer.name}</p>
                                        <p className="text-[10px] text-indigo-500 font-bold uppercase tracking-wider leading-none">{selectedCustomer.code}</p>
                                    </div>
                                </div>
                                <button onClick={() => setSelectedCustomer(null)} className="p-1.5 hover:bg-red-50 text-slate-400 hover:text-red-500 rounded-lg transition-colors">
                                    <X size={16} />
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Cart Section */}
                    <div className="flex-1 bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col overflow-hidden">
                        <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-white text-slate-900">
                            <h2 className="font-bold text-slate-900 flex items-center gap-2">
                                Current Order
                                <span className="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{cart.length} items</span>
                            </h2>
                            <button onClick={() => setCart([])} className="text-[10px] font-bold text-red-500 hover:underline">CLEAR ALL</button>
                        </div>

                        {/* Order Items */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-3">
                            {cart.length > 0 ? (
                                cart.map(item => (
                                    <div key={item.item_id} className="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100 group">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-bold text-slate-900 line-clamp-1">{item.name}</p>
                                            <p className="text-xs text-slate-500">₹{item.unit_price.toFixed(2)} / unit</p>
                                        </div>
                                        <div className="flex items-center gap-2 bg-white px-2 py-1 rounded-lg border border-slate-200 shadow-sm">
                                            <button onClick={() => item.quantity > 1 ? updateQuantity(item.item_id, -1) : removeFromCart(item.item_id)} className="p-1 text-slate-400 hover:text-indigo-600 transition-colors">
                                                {item.quantity === 1 ? <Trash2 size={14} /> : <Minus size={14} />}
                                            </button>
                                            <span className="text-sm font-black text-slate-900 w-6 text-center">{item.quantity}</span>
                                            <button onClick={() => updateQuantity(item.item_id, 1)} className="p-1 text-slate-400 hover:text-indigo-600 transition-colors">
                                                <Plus size={14} />
                                            </button>
                                        </div>
                                        <div className="text-right min-w-[80px]">
                                            <p className="text-sm font-black text-slate-900">₹{item.line_total.toFixed(2)}</p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="h-full flex flex-col items-center justify-center text-slate-300 gap-3 grayscale opacity-50">
                                    <Receipt size={64} strokeWidth={1} />
                                    <p className="text-sm font-medium">No items in the cart</p>
                                </div>
                            )}
                        </div>

                        {/* Totals */}
                        <div className="p-6 bg-slate-900 text-white space-y-4">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between items-center text-slate-400">
                                    <span>Subtotal</span>
                                    <span>₹{subtotal.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between items-center text-slate-400">
                                    <span>Tax (18% GST)</span>
                                    <span>₹{taxAmount.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between items-center pt-2 border-t border-slate-800 text-xl font-black">
                                    <span className="text-indigo-400">Grand Total</span>
                                    <span className="text-white">₹{totalAmount.toFixed(2)}</span>
                                </div>
                            </div>
                            
                            <button 
                                onClick={handleCreateBill}
                                disabled={isSubmitting || cart.length === 0}
                                className={`w-full py-4 rounded-xl font-black text-lg transition-all flex items-center justify-center gap-3 shadow-xl ${
                                    isSubmitting || cart.length === 0 
                                    ? 'bg-slate-800 text-slate-500 cursor-not-allowed' 
                                    : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20 active:scale-95'
                                }`}
                            >
                                {isSubmitting ? (
                                    <>
                                        <Loader2 size={24} className="animate-spin" />
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle2 size={24} />
                                        Complete Payment
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Success Modal */}
            {invoiceSuccess && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md">
                    <div className="bg-white rounded-[32px] p-10 shadow-2xl w-full max-w-md text-center animate-in zoom-in fade-in duration-300">
                        <div className="w-24 h-24 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                            <CheckCircle2 size={48} strokeWidth={3} />
                        </div>
                        <h2 className="text-3xl font-black text-slate-900 mb-2">Invoice Generated!</h2>
                        <p className="text-slate-500 font-medium mb-8">Bill #{invoiceSuccess.invoice_number} has been confirmed and stock levels updated.</p>
                        
                        <div className="flex flex-col gap-3">
                            <button 
                                onClick={() => setShowReceipt(true)}
                                className="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-indigo-600/20 transition-all active:scale-95"
                            >
                                <Printer size={20} />
                                Print Receipt
                            </button>
                            <button 
                                onClick={() => setInvoiceSuccess(null)}
                                className="w-full py-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black rounded-2xl transition-all"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showReceipt && invoiceSuccess && (
                <ReceiptTemplate 
                    invoice={invoiceSuccess} 
                    onClose={() => setShowReceipt(false)} 
                />
            )}
        </div>
    );
};

export default BillingPage;
