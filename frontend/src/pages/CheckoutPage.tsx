import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  MapPin, Check, CreditCard, ShoppingCart,
  AlertCircle, ExternalLink, Loader2, ShieldCheck,
  ChevronRight, Phone, Home, Package
} from 'lucide-react';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import api from '../api/config';

const inputClass =
  'w-full px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 hover:border-gray-300 transition-all duration-200 placeholder-gray-400';

const CheckoutPage: React.FC = () => {
  const { items, getTotalPrice, clearCart } = useCart();
  const { user } = useAuth();
  const navigate = useNavigate();

  const [deliveryAddress, setDeliveryAddress] = useState({
    street: '', city: '', state: '', zipCode: '', phone: ''
  });
  const [isProcessing, setIsProcessing] = useState(false);
  const [distance, setDistance] = useState<number | null>(null);

  const ZIP_DISTANCES: Record<string, number> = {
    '302039': 0.5, '302013': 1.8, '302032': 2.5, '302012': 3.0,
    '302023': 3.5, '302016': 4.0, '302001': 5.0, '302002': 6.0,
    '302004': 7.5, '302015': 8.0, '302006': 9.0, '302019': 10.0,
    '302021': 11.5, '302017': 12.0, '302033': 14.0, '302022': 15.0,
    '302020': 18.0,
  };

  // eslint-disable-next-line react-hooks/exhaustive-deps
  React.useEffect(() => {
    if (deliveryAddress.zipCode.length === 6) {
      if (ZIP_DISTANCES[deliveryAddress.zipCode]) {
        setDistance(ZIP_DISTANCES[deliveryAddress.zipCode]);
      } else {
        setDistance(deliveryAddress.zipCode.startsWith('3020') ? 8.0 : 99.0);
      }
    } else {
      setDistance(null);
    }
  }, [deliveryAddress.zipCode]);

  const isEligibleForDelivery = false; // Disabled as per business requirement

  const subtotal = getTotalPrice();
  const deliveryFee = subtotal > 500 ? 0 : 49;
  const tax = subtotal * 0.08;
  const total = subtotal + deliveryFee + tax;
  const totalQty = items.reduce((sum, i) => sum + i.quantity, 0);

  const handleAddressChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setDeliveryAddress(prev => ({ ...prev, [name]: value }));
  };

  const isAddressComplete = Object.values(deliveryAddress).every(f => f.trim() !== '');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isAddressComplete) { alert('Please fill in the complete delivery address.'); return; }
    if (distance !== null && !isEligibleForDelivery) {
      alert('We apologize — home delivery is currently unavailable. Please visit our shop directly.');
      return;
    }
    setIsProcessing(true);
    try {
      if (!user) { navigate('/login'); return; }
      const orderPayload = {
        items: items.map(i => ({ product: i.id, qty: i.quantity, price: i.price })),
        shippingAddress: deliveryAddress,
        totalPrice: total
      };
      await api.post('/orders', orderPayload);
      clearCart();
      navigate('/dashboard');
    } catch (err) {
      console.error('Checkout failed', err);
      alert('Failed to place order. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const MAPS_URL =
    'https://www.google.com/maps/place/Balaji+Trading+Company/@26.9719313,75.7559179,17z/data=!4m10!1m2!2m1!1sBalaji+Trading+Company,+Bank+Colony,+Murlipura,+Jaipur,+Rajasthan+302039!3m6!1s0x396db3ad15f8db4b:0xb86e769ea6c13a5b!8m2!3d26.9719313!4d75.7606815!15sCkhCYWxhamkgVHJhZGluZyBDb21wYW55LCBCYW5rIENvbG9ueSwgTXVybGlwdXJhLCBKYWlwdXIsIFJhamFzdGhhbiAzMDIwMzlaRiJEYmFsYWppIHRyYWRpbmcgY29tcGFueSBiYW5rIGNvbG9ueSBtdXJsaXB1cmEgamFpcHVyIHJhamFzdGhhbiAzMDIwMzmSARVmbWNnX2dvb2RzX3dob2xlc2FsZXLgAQA!16s%2Fg%2F11shj0ygd6?entry=ttu&g_ep=EgoyMDI2MDIyNS4wIKXMDSoASAFQAw%3D%3D';

  // ── Progress steps
  const steps = [
    { label: 'Cart', icon: <ShoppingCart className="h-4 w-4" />, done: true, active: false },
    { label: 'Address', icon: <MapPin className="h-4 w-4" />, done: false, active: true },
    { label: 'Payment', icon: <CreditCard className="h-4 w-4" />, done: false, active: false },
  ];

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

        {/* ── Breadcrumb ── */}
        <div className="flex items-center gap-2 text-xs text-gray-400 mb-6">
          <Link to="/" className="hover:text-emerald-600 transition-colors">Home</Link>
          <ChevronRight className="h-3 w-3" />
          <Link to="/cart" className="hover:text-emerald-600 transition-colors">Cart</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-gray-600 font-medium">Checkout</span>
        </div>

        {/* ── Progress Stepper ── */}
        <div className="flex items-center justify-center mb-10">
          {steps.map((step, i) => (
            <React.Fragment key={i}>
              {i > 0 && (
                <div className={`flex-1 max-w-[80px] h-0.5 mx-2 rounded-full ${step.done || steps[i - 1].done ? 'bg-emerald-400' : 'bg-gray-200'}`} />
              )}
              <div className="flex flex-col items-center gap-1.5">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center shadow-sm transition-all duration-300 ${step.done
                    ? 'bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-emerald-500/20'
                    : step.active
                      ? 'bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-md shadow-emerald-500/30 ring-4 ring-emerald-200'
                      : 'bg-gray-100 text-gray-400'
                  }`}>
                  {step.done ? <Check className="h-4 w-4" /> : step.icon}
                </div>
                <span className={`text-xs font-semibold ${step.done || step.active ? 'text-emerald-600' : 'text-gray-400'}`}>
                  {step.label}
                </span>
              </div>
            </React.Fragment>
          ))}
        </div>

        {/* ── Page Title ── */}
        <h1 className="text-2xl sm:text-3xl font-bold font-display text-gray-900 mb-8 flex items-center gap-3">
          <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
            <CreditCard className="h-5 w-5 text-white" />
          </div>
          Checkout
        </h1>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

          {/* ── Delivery Address Form ── */}
          <div className="lg:col-span-2 space-y-5">
            <div className="bg-white border border-gray-100 rounded-2xl p-6 sm:p-8 shadow-sm">

              {/* Section header */}
              <div className="flex items-center gap-3 mb-7">
                <div className="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center">
                  <Home className="h-5 w-5" />
                </div>
                <div>
                  <h2 className="text-lg font-bold font-display text-gray-900">Delivery Address</h2>
                  <p className="text-xs text-gray-400 mt-0.5">Enter your complete delivery details</p>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Street Address *</label>
                  <input type="text" name="street" placeholder="House / Flat no., Street, Colony"
                    value={deliveryAddress.street} onChange={handleAddressChange} required className={inputClass} />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">City *</label>
                  <input type="text" name="city" placeholder="e.g. Jaipur"
                    value={deliveryAddress.city} onChange={handleAddressChange} required className={inputClass} />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">State *</label>
                  <input type="text" name="state" placeholder="e.g. Rajasthan"
                    value={deliveryAddress.state} onChange={handleAddressChange} required className={inputClass} />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">ZIP Code *</label>
                  <input type="text" name="zipCode" placeholder="6-digit PIN code"
                    value={deliveryAddress.zipCode} onChange={handleAddressChange} required maxLength={6} className={inputClass} />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Phone Number *</label>
                  <div className="relative">
                    <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input type="tel" name="phone" placeholder="10-digit mobile number"
                      value={deliveryAddress.phone} onChange={handleAddressChange} required
                      className={`${inputClass} pl-10`} />
                  </div>
                </div>
              </div>

              {/* Distance / delivery notice */}
              {distance !== null && (
                <div className="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl">
                  <div className="flex items-start gap-3">
                    <div className="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                      <AlertCircle className="h-5 w-5 text-amber-600" />
                    </div>
                    <div className="flex-1">
                      <h3 className="font-bold text-amber-800 text-sm mb-1">
                        Home Delivery Currently Unavailable
                      </h3>
                      <p className="text-amber-700 text-sm leading-relaxed mb-3">
                        {distance <= 2.0
                          ? `Your location is ~${distance.toFixed(1)} km away. We currently cannot process home delivery orders even to nearby areas — but we'd love to see you in store! Explore our full range and exclusive in-store deals.`
                          : `Your location is ~${distance.toFixed(1)} km away, which is outside our current delivery reach. Please visit our physical store to purchase directly and enjoy exclusive in-store offers!`
                        }
                      </p>
                      <a
                        href={MAPS_URL}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 hover:text-amber-900 bg-amber-100 hover:bg-amber-200 px-3.5 py-2 rounded-xl transition-colors duration-200"
                      >
                        <MapPin className="h-4 w-4" />
                        View Shop on Google Maps
                        <ExternalLink className="h-3.5 w-3.5" />
                      </a>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Payment method card */}
            <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
              <div className="flex items-center gap-3 mb-5">
                <div className="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center">
                  <CreditCard className="h-5 w-5" />
                </div>
                <div>
                  <h2 className="text-lg font-bold font-display text-gray-900">Payment Method</h2>
                  <p className="text-xs text-gray-400 mt-0.5">Pay at the time of delivery</p>
                </div>
              </div>

              <div className="flex items-center gap-4 p-4 bg-emerald-50 border-2 border-emerald-300 rounded-2xl">
                <div className="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center text-white flex-shrink-0">
                  <Check className="h-5 w-5" />
                </div>
                <div>
                  <p className="font-bold text-gray-900 text-sm">Cash on Delivery</p>
                  <p className="text-gray-500 text-xs mt-0.5">Pay cash when your order arrives at your door</p>
                </div>
                <div className="ml-auto">
                  <span className="text-xs bg-emerald-100 text-emerald-700 font-semibold px-2.5 py-1 rounded-full">Selected</span>
                </div>
              </div>
            </div>
          </div>

          {/* ── Order Summary Sidebar ── */}
          <div>
            <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm sticky top-24">

              <h2 className="text-lg font-bold font-display text-gray-900 mb-5 pb-4 border-b border-gray-100 flex items-center gap-2">
                <Package className="h-5 w-5 text-gray-400" />
                Order Summary
                <span className="ml-auto text-sm font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                  {totalQty} items
                </span>
              </h2>

              {/* Item list */}
              <div className="space-y-3 mb-5 max-h-48 overflow-y-auto pr-1 -mr-1">
                {items.map(item => (
                  <div key={item.id} className="flex items-center gap-2.5">
                    <img src={item.image} alt={item.name} className="w-9 h-9 rounded-lg object-cover bg-gray-100 flex-shrink-0" />
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-800 truncate">{item.name}</p>
                      <p className="text-xs text-gray-400">× {item.quantity}</p>
                    </div>
                    <span className="text-sm font-bold text-gray-700 flex-shrink-0">₹{(item.price * item.quantity).toFixed(2)}</span>
                  </div>
                ))}
              </div>

              {/* Pricing breakdown */}
              <div className="space-y-2.5 border-t border-gray-100 pt-4 mb-5">
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
                <div className="flex justify-between items-center border-t border-gray-100 pt-3 mt-1">
                  <span className="font-bold text-gray-900 text-base">Total</span>
                  <span className="text-xl font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                    ₹{total.toFixed(2)}
                  </span>
                </div>
                <p className="text-[11px] text-gray-400 text-right">Inclusive of all taxes</p>
              </div>

              {/* Place order button */}
              <button
                onClick={handleSubmit}
                disabled={isProcessing || distance === null || !isEligibleForDelivery || !isAddressComplete}
                className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-emerald-500/25"
              >
                {isProcessing ? (
                  <>
                    <Loader2 className="h-5 w-5 animate-spin" />
                    Placing Order...
                  </>
                ) : (
                  <>
                    <Check className="h-5 w-5" />
                    Place Order · Cash on Delivery
                  </>
                )}
              </button>

              {/* Delivery notice when disabled */}
              {!isEligibleForDelivery && (
                <p className="text-xs text-center text-amber-600 mt-3 flex items-center justify-center gap-1.5">
                  <AlertCircle className="h-3.5 w-3.5 flex-shrink-0" />
                  Home delivery is currently unavailable
                </p>
              )}

              {/* Trust */}
              <div className="mt-5 pt-5 border-t border-gray-100 flex items-center justify-center gap-1.5 text-xs text-gray-400">
                <ShieldCheck className="h-4 w-4 text-emerald-500" />
                <span>Secure & encrypted checkout</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CheckoutPage;
