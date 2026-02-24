import React from 'react';
import { Link } from 'react-router-dom';
import { Plus, Minus, Trash2, ShoppingBag } from 'lucide-react';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';

const CartPage: React.FC = () => {
  const { items, updateQuantity, removeFromCart, getTotalPrice, clearCart } = useCart();
  const { user } = useAuth();

  if (items.length === 0) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center animate-fade-in-up">
          <div className="w-24 h-24 bg-gray-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
            <ShoppingBag className="h-12 w-12 text-gray-300" />
          </div>
          <h2 className="text-2xl font-bold font-display text-gray-900 mb-2">Your cart is empty</h2>
          <p className="text-gray-500 mb-8">Add some products to get started!</p>
          <Link to="/dashboard" className="btn-primary inline-flex items-center">
            Continue Shopping
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <div className="flex items-center justify-between mb-8">
          <h1 className="text-3xl font-bold font-display text-gray-900">Shopping Cart</h1>
          <button onClick={clearCart} className="text-rose-500 hover:text-rose-600 text-sm font-medium transition-colors duration-200">
            Clear Cart
          </button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Cart Items */}
          <div className="lg:col-span-2">
            <div className="glass-card-solid overflow-hidden">
              <div className="p-5 border-b border-gray-100">
                <h2 className="text-lg font-semibold font-display text-gray-900">
                  Cart Items ({items.reduce((total, item) => total + item.quantity, 0)})
                </h2>
              </div>
              <div className="divide-y divide-gray-100">
                {items.map((item) => (
                  <div key={item.id} className="p-4 sm:p-5 flex items-center gap-3 sm:gap-4 hover:bg-gray-50/50 transition-colors duration-200">
                    <img src={item.image} alt={item.name} className="w-16 h-16 object-cover rounded-xl shadow-sm" />
                    <div className="flex-1 min-w-0">
                      <h3 className="font-semibold text-gray-900">{item.name}</h3>
                      <p className="text-sm text-gray-400 capitalize">{item.category}</p>
                      <p className="text-lg font-bold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">₹{item.price}</p>
                    </div>
                    <div className="flex flex-col sm:flex-row items-end sm:items-center gap-3 sm:gap-4">
                      <div className="flex items-center bg-gray-50 rounded-xl overflow-hidden border border-gray-200">
                        <button onClick={() => updateQuantity(item.id, item.quantity - 1)} className="p-1.5 sm:p-2 hover:bg-gray-100 transition-colors">
                          <Minus className="h-4 w-4 text-gray-500" />
                        </button>
                        <span className="px-2 sm:px-3 py-1 font-semibold text-sm">{item.quantity}</span>
                        <button onClick={() => updateQuantity(item.id, item.quantity + 1)} className="p-1.5 sm:p-2 hover:bg-gray-100 transition-colors">
                          <Plus className="h-4 w-4 text-gray-500" />
                        </button>
                      </div>
                      <button onClick={() => removeFromCart(item.id)} className="p-1.5 sm:p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all duration-200">
                        <Trash2 className="h-5 w-5" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Order Summary */}
          <div className="lg:col-span-1">
            <div className="glass-card-solid p-6 sticky top-24">
              <h2 className="text-lg font-semibold font-display text-gray-900 mb-5">Order Summary</h2>

              <div className="space-y-3 mb-6">
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Subtotal</span>
                  <span className="font-semibold">₹{getTotalPrice().toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Delivery Fee</span>
                  <span className="font-semibold">{getTotalPrice() > 500 ? <span className="text-emerald-600">FREE</span> : '₹49'}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Tax</span>
                  <span className="font-semibold">₹{(getTotalPrice() * 0.08).toFixed(2)}</span>
                </div>
                <div className="border-t border-gray-100 pt-3">
                  <div className="flex justify-between text-lg font-bold">
                    <span>Total</span>
                    <span className="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                      ₹{(getTotalPrice() + (getTotalPrice() > 500 ? 0 : 49) + getTotalPrice() * 0.08).toFixed(2)}
                    </span>
                  </div>
                </div>
              </div>

              {getTotalPrice() < 500 && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-5">
                  <p className="text-xs text-amber-700 font-medium">
                    Add ₹{(500 - getTotalPrice()).toFixed(2)} more for free delivery!
                  </p>
                </div>
              )}

              {user ? (
                <Link to="/checkout" className="btn-primary w-full flex justify-center shimmer-btn">
                  Proceed to Checkout
                </Link>
              ) : (
                <Link to="/login" className="btn-primary w-full flex justify-center shimmer-btn">
                  Login to Checkout
                </Link>
              )}

              <Link to="/dashboard" className="btn-secondary w-full flex justify-center mt-3">
                Continue Shopping
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CartPage;