import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  ChevronRight, Loader2, AlertCircle, Package, ShoppingBag,
  Calendar, Clock, CheckCircle, Truck, ReceiptText, RefreshCw
} from 'lucide-react';
import api from '../api/config';
import { useToast } from '../contexts/ToastContext';

interface OrderItem {
  product?: { _id?: string; id?: string; name?: string; image?: string };
  qty?: number;
  price?: number;
}

interface Order {
  _id?: string;
  id?: string;
  items: OrderItem[];
  totalPrice: number;
  shippingAddress?: { name?: string; street?: string; city?: string; state?: string; zipCode?: string };
  status?: string;
  createdAt?: string;
}

const statusConfig: Record<string, { label: string; color: string; bg: string; border: string; icon: React.ReactNode }> = {
  delivered: { label: 'Delivered', color: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200', icon: <CheckCircle className="h-3.5 w-3.5" /> },
  completed: { label: 'Completed', color: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200', icon: <CheckCircle className="h-3.5 w-3.5" /> },
  preparing: { label: 'Preparing', color: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200', icon: <Clock className="h-3.5 w-3.5" /> },
  processing: { label: 'Processing', color: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200', icon: <Clock className="h-3.5 w-3.5" /> },
  confirmed: { label: 'Confirmed', color: 'text-blue-700', bg: 'bg-blue-50', border: 'border-blue-200', icon: <CheckCircle className="h-3.5 w-3.5" /> },
  pending: { label: 'Pending', color: 'text-gray-700', bg: 'bg-gray-100', border: 'border-gray-200', icon: <Clock className="h-3.5 w-3.5" /> },
  shipped: { label: 'Shipped', color: 'text-purple-700', bg: 'bg-purple-50', border: 'border-purple-200', icon: <Truck className="h-3.5 w-3.5" /> },
  cancelled: { label: 'Cancelled', color: 'text-red-700', bg: 'bg-red-50', border: 'border-red-200', icon: <AlertCircle className="h-3.5 w-3.5" /> },
};

const OrderHistory: React.FC = () => {
  const navigate = useNavigate();
  const { showToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchOrders = async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get('/orders');
      const fetched = Array.isArray(response.data) ? response.data : [];
      setOrders(fetched);
      if (fetched.length > 0) localStorage.setItem('orders', JSON.stringify(fetched));
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Failed to load orders';
      setError(msg);
      const cached = localStorage.getItem('orders');
      if (cached) {
        try { setOrders(JSON.parse(cached)); showToast('Loaded orders from cache', 'info'); }
        catch { /* ignore */ }
      } else {
        showToast(msg, 'error');
      }
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => { fetchOrders(); }, [showToast]);

  const getStatus = (status?: string) =>
    statusConfig[status?.toLowerCase() ?? ''] ?? statusConfig['pending'];

  const formatDate = (d?: string) => {
    if (!d) return 'N/A';
    try {
      return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch { return 'N/A'; }
  };

  // ── Loading ──
  if (isLoading) {
    return (
      <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center">
        <div className="text-center space-y-4">
          <div className="w-16 h-16 bg-white border border-gray-100 rounded-2xl flex items-center justify-center mx-auto shadow-sm">
            <Loader2 className="h-8 w-8 animate-spin text-emerald-500" />
          </div>
          <p className="text-gray-500 font-medium text-sm">Loading your orders…</p>
        </div>
      </div>
    );
  }

  // ── Error (no fallback) ──
  if (error && orders.length === 0) {
    return (
      <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center px-4">
        <div className="text-center max-w-sm">
          <div className="relative w-32 h-32 mx-auto mb-6">
            <div className="absolute inset-0 bg-gradient-to-br from-red-100 to-rose-50 rounded-3xl rotate-6" />
            <div className="absolute inset-0 bg-white rounded-3xl shadow-sm flex items-center justify-center">
              <AlertCircle className="h-14 w-14 text-red-300" />
            </div>
          </div>
          <h2 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Orders</h2>
          <p className="text-gray-500 text-sm mb-7">{error}</p>
          <button
            onClick={fetchOrders}
            className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold px-7 py-3 rounded-xl shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 transition-all duration-300"
          >
            <RefreshCw className="h-4 w-4" /> Retry
          </button>
        </div>
      </div>
    );
  }

  // ── Empty ──
  if (orders.length === 0) {
    return (
      <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center px-4">
        <div className="text-center max-w-sm">
          <div className="relative w-36 h-36 mx-auto mb-8">
            <div className="absolute inset-0 bg-gradient-to-br from-emerald-100 to-teal-100 rounded-3xl rotate-6" />
            <div className="absolute inset-0 bg-white rounded-3xl shadow-sm flex items-center justify-center">
              <Package className="h-16 w-16 text-gray-200" />
            </div>
          </div>
          <h2 className="text-2xl font-bold font-display text-gray-900 mb-2">No Orders Yet</h2>
          <p className="text-gray-500 leading-relaxed mb-8">
            You haven't placed any orders yet.<br />Start shopping to see your history here.
          </p>
          <Link
            to="/dashboard"
            className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-8 py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5"
          >
            <ShoppingBag className="h-4 w-4" /> Browse Products
          </Link>
        </div>
      </div>
    );
  }

  // ── Orders list ──
  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

        {/* ── Header ── */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl sm:text-3xl font-bold font-display text-gray-900 flex items-center gap-3">
              <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
                <ReceiptText className="h-5 w-5 text-white" />
              </div>
              Order History
            </h1>
            <p className="text-gray-400 text-sm mt-1">
              {orders.length} order{orders.length !== 1 ? 's' : ''} placed
            </p>
          </div>

          {error && (
            <div className="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 px-3 py-2 rounded-xl text-xs font-semibold">
              <AlertCircle className="h-3.5 w-3.5 flex-shrink-0" />
              Showing cached data
            </div>
          )}
        </div>

        {/* ── Order Cards ── */}
        <div className="space-y-4">
          {orders.map((order) => {
            const orderId = order._id || order.id || '';
            const status = getStatus(order.status);
            const itemCount = order.items?.reduce((s, i) => s + (i.qty ?? 1), 0) ?? 0;
            const visibleItems = order.items?.slice(0, 4) ?? [];
            const extraCount = Math.max(0, (order.items?.length ?? 0) - 4);

            return (
              <div
                key={orderId}
                onClick={() => navigate(`/orders/${orderId}`)}
                className="bg-white border border-gray-100 hover:border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-md cursor-pointer transition-all duration-300 hover:-translate-y-0.5 group"
              >
                {/* Top row */}
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                  <div className="flex items-center gap-3 flex-wrap">
                    {/* Status badge */}
                    <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold ${status.bg} ${status.color} ${status.border}`}>
                      {status.icon}
                      {status.label}
                    </div>
                    {/* Order ID */}
                    <span className="font-mono text-xs text-gray-400 font-semibold">
                      #{orderId.slice(-8).toUpperCase()}
                    </span>
                  </div>
                  <ChevronRight className="h-5 w-5 text-gray-300 group-hover:text-emerald-500 group-hover:translate-x-0.5 transition-all duration-300 flex-shrink-0" />
                </div>

                {/* Meta row */}
                <div className="flex flex-wrap items-center gap-4 mb-4 text-sm">
                  <div className="flex items-center gap-1.5 text-gray-500">
                    <Calendar className="h-3.5 w-3.5 flex-shrink-0 text-gray-400" />
                    <span>{formatDate(order.createdAt)}</span>
                  </div>
                  <div className="flex items-center gap-1.5 text-gray-500">
                    <Package className="h-3.5 w-3.5 flex-shrink-0 text-gray-400" />
                    <span>{itemCount} item{itemCount !== 1 ? 's' : ''}</span>
                  </div>
                  <div className="ml-auto font-extrabold text-gray-900 text-base bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                    ₹{order.totalPrice?.toFixed(2) ?? '0.00'}
                  </div>
                </div>

                {/* Product thumbnails */}
                <div className="flex items-center gap-2">
                  {visibleItems.map((item, idx) => {
                    const img = item.product?.image;
                    const name = item.product?.name ?? 'Product';
                    return (
                      <div
                        key={idx}
                        title={name}
                        className="w-12 h-12 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden flex items-center justify-center flex-shrink-0 hover:scale-105 transition-transform duration-200"
                      >
                        {img ? (
                          <img src={img} alt={name} className="w-full h-full object-cover"
                            onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                        ) : (
                          <ShoppingBag className="h-5 w-5 text-gray-300" />
                        )}
                      </div>
                    );
                  })}
                  {extraCount > 0 && (
                    <div className="w-12 h-12 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-center text-xs font-bold text-gray-500 flex-shrink-0">
                      +{extraCount}
                    </div>
                  )}

                  {/* View details nudge */}
                  <div className="ml-auto text-xs text-gray-400 font-medium group-hover:text-emerald-600 transition-colors flex items-center gap-1">
                    View details <ChevronRight className="h-3.5 w-3.5" />
                  </div>
                </div>

                {/* Shipping destination (if available) */}
                {order.shippingAddress?.city && (
                  <div className="mt-4 pt-4 border-t border-gray-50 text-xs text-gray-400 flex items-center gap-1.5">
                    <Truck className="h-3.5 w-3.5 flex-shrink-0" />
                    Deliver to{' '}
                    <span className="font-semibold text-gray-600">
                      {order.shippingAddress.city}, {order.shippingAddress.state}
                    </span>
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Bottom CTA */}
        <div className="text-center mt-10">
          <Link
            to="/dashboard"
            className="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-700 font-bold text-sm transition-colors group"
          >
            <ShoppingBag className="h-4 w-4" />
            Continue Shopping
            <ChevronRight className="h-4 w-4 group-hover:translate-x-0.5 transition-transform" />
          </Link>
        </div>
      </div>
    </div>
  );
};

export default OrderHistory;
