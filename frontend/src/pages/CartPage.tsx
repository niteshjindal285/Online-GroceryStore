import React from 'react';
import { Link } from 'react-router-dom';
import { Plus, Minus, Trash2, ShoppingBag, ArrowRight, Tag, Truck, ShieldCheck, ChevronRight } from 'lucide-react';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';

const CartPage: React.FC = () => {
  const { items, updateQuantity, removeFromCart, getTotalPrice, clearCart } = useCart();
  const { user } = useAuth();
  const { showToast } = useToast();

  const handleRemoveFromCart = (id: string, name: string) => {
    removeFromCart(id);
    showToast(`${name} removed from cart`, 'info');
  };

  const handleClearCart = () => {
    if (window.confirm('Are you sure you want to clear your cart?')) {
      clearCart();
      showToast('Cart cleared', 'info');
    }
  };

  const subtotal = getTotalPrice();
  const deliveryFee = subtotal > 500 ? 0 : 49;
  const tax = subtotal * 0.08;
  const total = subtotal + deliveryFee + tax;
  const totalQty = items.reduce((sum, item) => sum + item.quantity, 0);
  const amountToFreeDelivery = Math.max(0, 500 - subtotal);
  const freeDeliveryProgress = Math.min(100, (subtotal / 500) * 100);

  // ── Empty State ──
  if (items.length === 0) {
    return (
      <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center px-4">
        <div className="text-center max-w-sm">
          {/* Illustration */}
          <div className="relative w-36 h-36 mx-auto mb-8">
            <div className="absolute inset-0 bg-gradient-to-br from-emerald-100 to-teal-100 rounded-3xl rotate-6" />
            <div className="absolute inset-0 bg-white rounded-3xl shadow-sm flex items-center justify-center">
              <ShoppingBag className="h-16 w-16 text-gray-200" />
            </div>
          </div>
          <h2 className="text-2xl font-bold font-display text-gray-900 mb-2">Your cart is empty</h2>
          <p className="text-gray-500 mb-8 leading-relaxed">
            Looks like you haven't added anything yet.<br />Start shopping to fill it up!
          </p>
          <Link
            to="/dashboard"
            className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-8 py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5"
          >
            Browse Products <ArrowRight className="h-4 w-4" />
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

        {/* ── Page Header ── */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <div className="flex items-center gap-2 text-xs text-gray-400 mb-1">
              <Link to="/dashboard" className="hover:text-emerald-600 transition-colors">Shop</Link>
              <ChevronRight className="h-3 w-3" />
              <span className="text-gray-600 font-medium">Cart</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold font-display text-gray-900 flex items-center gap-3">
              <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
                <ShoppingBag className="h-5 w-5 text-white" />
              </div>
              Shopping Cart
              <span className="text-base font-semibold text-gray-400 bg-gray-100 px-2.5 py-0.5 rounded-full">
                {totalQty} {totalQty === 1 ? 'item' : 'items'}
              </span>
            </h1>
          </div>
          <button
            onClick={handleClearCart}
            className="text-xs text-rose-500 hover:text-rose-600 font-semibold hover:bg-rose-50 px-3 py-2 rounded-lg transition-all duration-200"
          >
            Clear Cart
          </button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

          {/* ── Cart Items ── */}
          <div className="lg:col-span-2 space-y-3">
            {items.map((item) => (
              <div
                key={item.id}
                className="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 flex items-center gap-4 hover:shadow-md hover:border-gray-200 transition-all duration-300 group"
              >
                {/* Product image */}
                <div className="relative flex-shrink-0">
                  <img
                    src={item.image}
                    alt={item.name}
                    className="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-xl bg-gray-50"
                  />
                  {item.discount && item.discount > 0 ? (
                    <span className="absolute -top-2 -right-2 bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                      -{item.discount}%
                    </span>
                  ) : null}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                  <h3 className="font-bold text-gray-900 text-sm sm:text-base truncate">{item.name}</h3>
                  <p className="text-xs text-gray-400 capitalize mt-0.5">{item.category.replace(/-/g, ' ')}</p>
                  <div className="flex items-center gap-3 mt-2">
                    <span className="text-lg font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                      ₹{item.price}
                    </span>
                    <span className="text-xs text-gray-400">× {item.quantity} = </span>
                    <span className="text-sm font-bold text-gray-700">₹{(item.price * item.quantity).toFixed(2)}</span>
                  </div>
                </div>

                {/* Controls */}
                <div className="flex flex-col sm:flex-row items-end sm:items-center gap-3">
                  {/* Qty stepper */}
                  <div className="flex items-center bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
                    <button
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                      className="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors"
                    >
                      <Minus className="h-3.5 w-3.5" />
                    </button>
                    <span className="w-8 text-center text-sm font-bold text-gray-800">{item.quantity}</span>
                    <button
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      className="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center hover:bg-emerald-50 text-gray-500 hover:text-emerald-600 transition-colors"
                    >
                      <Plus className="h-3.5 w-3.5" />
                    </button>
                  </div>

                  {/* Remove */}
                  <button
                    onClick={() => handleRemoveFromCart(item.id, item.name)}
                    className="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-rose-50 text-rose-400 hover:bg-rose-100 hover:text-rose-600 flex items-center justify-center transition-all duration-200"
                    title="Remove"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            ))}

            {/* Continue Shopping */}
            <Link
              to="/dashboard"
              className="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-700 text-sm font-semibold mt-2 group"
            >
              <span className="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                <ArrowRight className="h-3.5 w-3.5 rotate-180" />
              </span>
              Continue Shopping
            </Link>
          </div>

          {/* ── Order Summary ── */}
          <div className="lg:col-span-1">
            <div className="bg-white border border-gray-100 rounded-2xl p-6 sticky top-24 shadow-sm">

              <h2 className="text-lg font-bold font-display text-gray-900 mb-5 pb-4 border-b border-gray-100">
                Order Summary
              </h2>

              {/* Free delivery progress */}
              {subtotal < 500 && (
                <div className="mb-5 p-3.5 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-100 rounded-xl">
                  <div className="flex items-center gap-2 mb-2">
                    <Truck className="h-4 w-4 text-amber-600 flex-shrink-0" />
                    <p className="text-xs text-amber-700 font-semibold">
                      Add <span className="font-extrabold">₹{amountToFreeDelivery.toFixed(0)}</span> more for free delivery!
                    </p>
                  </div>
                  <div className="h-1.5 bg-amber-100 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-gradient-to-r from-amber-400 to-orange-400 rounded-full transition-all duration-500"
                      style={{ width: `${freeDeliveryProgress}%` }}
                    />
                  </div>
                </div>
              )}

              {subtotal >= 500 && (
                <div className="mb-5 p-3.5 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center gap-2">
                  <Truck className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                  <p className="text-xs text-emerald-700 font-semibold">🎉 You've unlocked free delivery!</p>
                </div>
              )}

              {/* Line items */}
              <div className="space-y-3 mb-5">
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Subtotal ({totalQty} items)</span>
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
              </div>

              {/* Total */}
              <div className="border-t border-gray-100 pt-4 mb-5">
                <div className="flex justify-between items-center">
                  <span className="font-bold text-gray-900 text-base">Total</span>
                  <span className="text-xl font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                    ₹{total.toFixed(2)}
                  </span>
                </div>
                <p className="text-[11px] text-gray-400 mt-1 text-right">Inclusive of all taxes</p>
              </div>

              {/* CTA */}
              {user ? (
                <Link
                  to="/checkout"
                  className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 mb-3"
                >
                  Proceed to Checkout <ArrowRight className="h-4 w-4" />
                </Link>
              ) : (
                <Link
                  to="/login"
                  className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 mb-3"
                >
                  Login to Checkout <ArrowRight className="h-4 w-4" />
                </Link>
              )}

              <Link
                to="/dashboard"
                className="w-full flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-semibold py-3 rounded-xl transition-all duration-200 text-sm"
              >
                Continue Shopping
              </Link>

              {/* Trust badges */}
              <div className="mt-5 pt-5 border-t border-gray-100 grid grid-cols-2 gap-3">
                {[
                  { icon: <ShieldCheck className="h-4 w-4 text-emerald-600" />, label: 'Secure Checkout' },
                  { icon: <Truck className="h-4 w-4 text-blue-500" />, label: 'Fast Delivery' },
                  { icon: <Tag className="h-4 w-4 text-amber-500" />, label: 'Best Prices' },
                  { icon: <ShoppingBag className="h-4 w-4 text-purple-500" />, label: 'Easy Returns' },
                ].map(({ icon, label }) => (
                  <div key={label} className="flex items-center gap-2">
                    <div className="w-7 h-7 bg-gray-50 border border-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      {icon}
                    </div>
                    <span className="text-[11px] text-gray-500 font-medium leading-tight">{label}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CartPage;