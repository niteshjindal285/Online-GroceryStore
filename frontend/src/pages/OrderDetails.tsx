import React from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
    ArrowLeft, Package, MapPin, Phone, Calendar,
    CreditCard, CheckCircle, Clock, Truck, ChevronRight,
    ShoppingBag, AlertCircle
} from 'lucide-react';

interface OrderItem {
    name: string;
    quantity: number;
    price: number;
    image?: string;
    category?: string;
}

interface Order {
    id: string;
    total: number;
    status?: string;
    items: OrderItem[];
    deliveryAddress: {
        street: string;
        city: string;
        state: string;
        zipCode: string;
        phone: string;
    };
}

const statusConfig: Record<string, { label: string; color: string; bg: string; border: string; icon: React.ReactNode }> = {
    delivered: { label: 'Delivered', color: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200', icon: <CheckCircle className="h-4 w-4" /> },
    preparing: { label: 'Preparing', color: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200', icon: <Clock className="h-4 w-4" /> },
    confirmed: { label: 'Confirmed', color: 'text-blue-700', bg: 'bg-blue-50', border: 'border-blue-200', icon: <CheckCircle className="h-4 w-4" /> },
    pending: { label: 'Pending', color: 'text-gray-700', bg: 'bg-gray-100', border: 'border-gray-200', icon: <Clock className="h-4 w-4" /> },
    shipped: { label: 'Shipped', color: 'text-purple-700', bg: 'bg-purple-50', border: 'border-purple-200', icon: <Truck className="h-4 w-4" /> },
};

const OrderDetails: React.FC = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const orders: Order[] = JSON.parse(localStorage.getItem('orders') || '[]');
    const order = orders.find((o) => o.id === id);

    const orderDate = id ? new Date(parseInt(id)).toLocaleDateString('en-IN', {
        day: 'numeric', month: 'long', year: 'numeric'
    }) : '—';

    const statusKey = order?.status?.toLowerCase() ?? 'delivered';
    const status = statusConfig[statusKey] ?? statusConfig['pending'];

    const subtotal = order?.items.reduce((sum, i) => sum + i.price * i.quantity, 0) ?? 0;
    const deliveryFee = subtotal > 500 ? 0 : 49;
    const tax = subtotal * 0.08;

    // ── Not found ──
    if (!order) {
        return (
            <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center px-4">
                <div className="text-center max-w-sm">
                    <div className="relative w-32 h-32 mx-auto mb-6">
                        <div className="absolute inset-0 bg-gradient-to-br from-gray-100 to-gray-50 rounded-3xl rotate-6" />
                        <div className="absolute inset-0 bg-white rounded-3xl shadow-sm flex items-center justify-center">
                            <AlertCircle className="h-14 w-14 text-gray-200" />
                        </div>
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mb-2">Order Not Found</h2>
                    <p className="text-gray-500 mb-8">We couldn't find order #{id?.slice(-6).toUpperCase()}. It may have been removed or doesn't exist.</p>
                    <Link to="/dashboard"
                        className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold px-7 py-3.5 rounded-xl shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 transition-all duration-300">
                        <ShoppingBag className="h-4 w-4" /> Back to Shop
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-[#f8fafc]">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

                {/* ── Breadcrumb / Back ── */}
                <div className="flex items-center gap-2 text-xs text-gray-400 mb-6">
                    <Link to="/" className="hover:text-emerald-600 transition-colors">Home</Link>
                    <ChevronRight className="h-3 w-3" />
                    <span className="text-gray-600 font-medium">Order Details</span>
                </div>

                <button
                    onClick={() => navigate(-1)}
                    className="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 mb-6 group transition-colors"
                >
                    <span className="w-7 h-7 rounded-full bg-white border border-gray-200 flex items-center justify-center group-hover:bg-gray-50 transition-colors shadow-sm">
                        <ArrowLeft className="h-3.5 w-3.5" />
                    </span>
                    Back
                </button>

                {/* ── Page Header ── */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold font-display text-gray-900 flex items-center gap-3">
                            <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
                                <Package className="h-5 w-5 text-white" />
                            </div>
                            Order Details
                        </h1>
                        <p className="text-gray-400 text-sm mt-1">
                            Order <span className="font-mono font-semibold text-gray-600">#{id?.slice(-8).toUpperCase()}</span>
                        </p>
                    </div>

                    <div className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border font-semibold text-sm ${status.bg} ${status.color} ${status.border}`}>
                        {status.icon}
                        {status.label}
                    </div>
                </div>

                {/* ── Main grid ── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Left — Items */}
                    <div className="lg:col-span-2 space-y-5">

                        {/* Order meta strip */}
                        <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <Calendar className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide font-bold text-gray-400">Order Date</p>
                                    <p className="text-sm font-semibold text-gray-800 mt-0.5">{orderDate}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <CreditCard className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide font-bold text-gray-400">Payment</p>
                                    <p className="text-sm font-semibold text-gray-800 mt-0.5">Cash on Delivery</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <Package className="h-4 w-4" />
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide font-bold text-gray-400">Items</p>
                                    <p className="text-sm font-semibold text-gray-800 mt-0.5">
                                        {order.items.reduce((s, i) => s + i.quantity, 0)} items
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Items list */}
                        <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h2 className="font-bold text-gray-900 flex items-center gap-2">
                                    <Package className="h-4 w-4 text-gray-400" />
                                    Ordered Items
                                </h2>
                            </div>
                            <div className="divide-y divide-gray-50">
                                {order.items.map((item, index) => (
                                    <div key={index} className="flex items-center gap-4 px-6 py-4 hover:bg-gray-50/60 transition-colors">
                                        {/* Image or placeholder */}
                                        <div className="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            {item.image ? (
                                                <img src={item.image} alt={item.name} className="w-full h-full object-cover" />
                                            ) : (
                                                <ShoppingBag className="h-6 w-6 text-gray-300" />
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-semibold text-gray-900 text-sm truncate">{item.name}</p>
                                            {item.category && (
                                                <p className="text-xs text-gray-400 capitalize mt-0.5">{item.category.replace(/-/g, ' ')}</p>
                                            )}
                                            <p className="text-xs text-gray-500 mt-1">
                                                ₹{item.price} × {item.quantity}
                                            </p>
                                        </div>
                                        <div className="text-right flex-shrink-0">
                                            <p className="font-bold text-gray-900">₹{(item.price * item.quantity).toFixed(2)}</p>
                                            <span className="text-xs text-gray-400">{item.quantity} unit{item.quantity > 1 ? 's' : ''}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Delivery Address */}
                        <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                            <h2 className="font-bold text-gray-900 flex items-center gap-2 mb-4">
                                <MapPin className="h-4 w-4 text-gray-400" />
                                Delivery Address
                            </h2>
                            <div className="bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-600 space-y-1.5">
                                <p className="font-semibold text-gray-800">{order.deliveryAddress.street}</p>
                                <p>{order.deliveryAddress.city}, {order.deliveryAddress.state} — {order.deliveryAddress.zipCode}</p>
                                <div className="flex items-center gap-2 pt-1 text-gray-500">
                                    <Phone className="h-3.5 w-3.5 flex-shrink-0" />
                                    <a href={`tel:${order.deliveryAddress.phone}`} className="hover:text-emerald-600 transition-colors font-medium">
                                        {order.deliveryAddress.phone}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right — Summary */}
                    <div>
                        <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm sticky top-24">
                            <h2 className="font-bold text-gray-900 mb-5 pb-4 border-b border-gray-100 flex items-center gap-2">
                                <CreditCard className="h-4 w-4 text-gray-400" />
                                Price Summary
                            </h2>

                            <div className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500">Subtotal</span>
                                    <span className="font-semibold text-gray-800">₹{subtotal.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500">Delivery Fee</span>
                                    <span className={`font-semibold ${deliveryFee === 0 ? 'text-emerald-600' : 'text-gray-800'}`}>
                                        {deliveryFee === 0 ? 'FREE' : `₹${deliveryFee}`}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500">GST (8%)</span>
                                    <span className="font-semibold text-gray-800">₹{tax.toFixed(2)}</span>
                                </div>

                                <div className="border-t border-gray-100 pt-3 flex justify-between items-center">
                                    <span className="font-bold text-gray-900">Total Paid</span>
                                    <span className="text-xl font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                                        ₹{order.total.toFixed(2)}
                                    </span>
                                </div>
                                <p className="text-[11px] text-gray-400 text-right">Paid via Cash on Delivery</p>
                            </div>

                            {/* Status timeline */}
                            <div className="mt-6 pt-5 border-t border-gray-100">
                                <p className="text-xs font-bold text-gray-500 uppercase tracking-wide mb-4">Order Timeline</p>
                                <ol className="space-y-3">
                                    {[
                                        { label: 'Order Placed', done: true },
                                        { label: 'Confirmed', done: statusKey !== 'pending' },
                                        { label: 'Preparing', done: ['preparing', 'shipped', 'delivered'].includes(statusKey) },
                                        { label: 'Out for Delivery', done: ['shipped', 'delivered'].includes(statusKey) },
                                        { label: 'Delivered', done: statusKey === 'delivered' },
                                    ].map((step, i) => (
                                        <li key={i} className="flex items-center gap-3">
                                            <div className={`w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 transition-colors ${step.done ? 'bg-gradient-to-br from-emerald-500 to-teal-500 shadow-sm shadow-emerald-500/30' : 'bg-gray-100 border border-gray-200'}`}>
                                                {step.done && <CheckCircle className="h-3 w-3 text-white" />}
                                            </div>
                                            <span className={`text-sm ${step.done ? 'text-gray-800 font-semibold' : 'text-gray-400'}`}>{step.label}</span>
                                        </li>
                                    ))}
                                </ol>
                            </div>

                            <Link to="/dashboard"
                                className="mt-6 w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3 rounded-xl transition-all duration-300 shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 text-sm">
                                <ShoppingBag className="h-4 w-4" /> Continue Shopping
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default OrderDetails;
