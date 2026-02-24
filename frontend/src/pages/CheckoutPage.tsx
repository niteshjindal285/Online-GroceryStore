import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MapPin, Check, CreditCard, Truck, AlertCircle, ExternalLink } from 'lucide-react';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import api from '../api/config';

const CheckoutPage: React.FC = () => {
  const { items, getTotalPrice, clearCart } = useCart();
  const { user } = useAuth();
  const navigate = useNavigate();

  const [deliveryAddress, setDeliveryAddress] = useState({
    street: '', city: '', state: '', zipCode: '', phone: ''
  });

  const [isProcessing, setIsProcessing] = useState(false);
  const [distance, setDistance] = useState<number | null>(null);

  // Shop Location: Murlipura (302039)
  // Distance lookup map in kilometers (approximations based on Jaipur geography)
  const ZIP_DISTANCES: Record<string, number> = {
    '302039': 0.5, // Murlipura (Our location)
    '302013': 1.8, // Vishwakarma Industrial Area
    '302032': 2.5, // Vidyadhar Nagar
    '302012': 3.0, // Khatipura
    '302023': 3.5, // Jhotwara
    '302016': 4.0, // Shastri Nagar
    '302001': 5.0, // C-Scheme / Secretariat
    '302002': 6.0, // Bani Park
    '302004': 7.5, // Raja Park
    '302015': 8.0, // Vaishali Nagar
    '302006': 9.0, // Civil Lines
    '302019': 10.0, // Shyam Nagar
    '302021': 11.5, // Mansarovar
    '302017': 12.0, // Malviya Nagar
    '302033': 14.0, // Pratap Nagar
    '302022': 15.0, // Sanganer
    '302020': 18.0, // Sitapura Industrial Area
  };

  // eslint-disable-next-line react-hooks/exhaustive-deps
  React.useEffect(() => {
    if (deliveryAddress.zipCode.length === 6) {
      if (ZIP_DISTANCES[deliveryAddress.zipCode]) {
        setDistance(ZIP_DISTANCES[deliveryAddress.zipCode]);
      } else {
        // If zip code is not in our direct map but is still a Jaipur zip code (starts with 3020), 
        // we'll assume it's out of bounds (> 2km) for safety, or prompt to contact shop.
        if (deliveryAddress.zipCode.startsWith('3020')) {
          setDistance(8.0); // Assume it's out of range since it's not a neighboring area
        } else {
          setDistance(99.0); // Completely out of city
        }
      }
    } else {
      setDistance(null);
    }
  }, [deliveryAddress.zipCode]);

  // Completely disable all deliveries for now as per user request
  const isEligibleForDelivery = false;

  const subtotal = getTotalPrice();
  const deliveryFee = subtotal > 500 ? 0 : 49;
  const tax = subtotal * 0.08;
  const total = subtotal + deliveryFee + tax;

  const handleAddressChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setDeliveryAddress(prev => ({ ...prev, [name]: value }));
  };

  const isAddressComplete = Object.values(deliveryAddress).every(field => field.trim() !== '');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isAddressComplete) { alert('Please fill complete delivery address'); return; }
    if (distance !== null && !isEligibleForDelivery) {
      alert('We apologize, but we only deliver within 2km. Please visit our physical shop to purchase these items.');
      return;
    }

    setIsProcessing(true);
    try {
      if (!user) {
        alert("Please log in to place an order.");
        navigate('/login');
        return;
      }

      const orderPayload = {
        items: items.map(i => ({ product: i.id, qty: i.quantity, price: i.price })),
        shippingAddress: deliveryAddress,
        totalPrice: total
      };

      await api.post('/orders', orderPayload);
      clearCart();
      navigate('/dashboard'); // Temporarily navigating to dashboard since orders page might need rewrite
    } catch (err) {
      console.error("Checkout failed", err);
      alert("Failed to place order. Please try again.");
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="min-h-screen">
      <div className="max-w-7xl mx-auto px-4 py-8 lg:py-12">
        {/* Progress Steps */}
        <div className="flex items-center justify-center mb-10">
          {[
            { label: 'Cart', icon: <Truck className="h-4 w-4" />, done: true },
            { label: 'Address', icon: <MapPin className="h-4 w-4" />, done: false },
            { label: 'Payment', icon: <CreditCard className="h-4 w-4" />, done: false },
          ].map((step, i) => (
            <React.Fragment key={i}>
              {i > 0 && <div className={`w-12 lg:w-24 h-0.5 ${step.done ? 'bg-emerald-500' : 'bg-gray-200'} mx-2`}></div>}
              <div className="flex flex-col items-center">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-md ${step.done ? 'bg-gradient-to-br from-emerald-500 to-teal-500' : i === 1 ? 'bg-gradient-to-br from-emerald-500 to-teal-500 animate-pulse-glow' : 'bg-gray-200 text-gray-500'
                  }`}>
                  {step.icon}
                </div>
                <span className={`text-xs mt-1.5 font-medium ${i <= 1 ? 'text-emerald-600' : 'text-gray-400'}`}>{step.label}</span>
              </div>
            </React.Fragment>
          ))}
        </div>

        <h1 className="text-3xl font-bold font-display text-gray-900 mb-8">Checkout</h1>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Address Section */}
          <div className="lg:col-span-2 space-y-6">
            <div className="glass-card-solid p-5 sm:p-6 lg:p-8">
              <div className="flex items-center mb-6">
                <div className="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center text-white shadow-md mr-3">
                  <MapPin className="h-5 w-5" />
                </div>
                <h2 className="text-lg font-semibold font-display">Delivery Address</h2>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">Street Address</label>
                  <input type="text" name="street" placeholder="Street Address" value={deliveryAddress.street} onChange={handleAddressChange} required className="input" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">City</label>
                  <input type="text" name="city" placeholder="City" value={deliveryAddress.city} onChange={handleAddressChange} required className="input" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">State</label>
                  <input type="text" name="state" placeholder="State" value={deliveryAddress.state} onChange={handleAddressChange} required className="input" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">ZIP Code</label>
                  <input type="text" name="zipCode" placeholder="ZIP Code" value={deliveryAddress.zipCode} onChange={handleAddressChange} required className="input" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                  <input type="tel" name="phone" placeholder="Phone Number" value={deliveryAddress.phone} onChange={handleAddressChange} required className="input" />
                </div>
              </div>

              {/* Distance Warning UI */}
              <div className="mt-6">
                {distance !== null && (
                  <div className={`p-4 rounded-xl border ${isEligibleForDelivery ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'}`}>
                    <div className="flex items-start">
                      {isEligibleForDelivery ? (
                        <Check className="h-5 w-5 mr-2 mt-0.5 flex-shrink-0 text-emerald-600" />
                      ) : (
                        <AlertCircle className="h-5 w-5 mr-2 mt-0.5 flex-shrink-0 text-amber-600" />
                      )}
                      <div>
                        <h3 className="font-semibold text-sm mb-1">
                          Delivery Not Available
                        </h3>
                        <p className="text-sm mb-3 text-balance">
                          {distance <= 2.0
                            ? `Your location is approximately ${distance.toFixed(1)} km away. Right now, we cannot deliver orders even to nearby locations. Please visit our physical shop to purchase your items and explore more exclusive in-store deals! We'd love to see you there.`
                            : `Your location is approximately ${distance.toFixed(1)} km away. Currently, we cannot deliver orders to every location, and unfortunately, your address is outside our standard delivery reach. However, we'd love for you to go to the shop and purchase the products directly! Make a visit to explore our full range of fresh groceries, exclusive in-store deals, and premium selections unavailable online. We would be thrilled to serve you in person!`
                          }
                        </p>
                        <a
                          href="https://www.google.com/maps/search/?api=1&query=Balaji+Trading+Company,+Bank+Colony,+Murlipura,+Jaipur,+Rajasthan+302039"
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center text-sm font-semibold text-amber-800 hover:text-amber-900 transition-colors bg-amber-200/50 hover:bg-amber-200 px-3 py-1.5 rounded-lg"
                        >
                          <MapPin className="h-4 w-4 mr-1.5" />
                          View Shop on Google Maps
                          <ExternalLink className="h-3 w-3 ml-1.5" />
                        </a>
                      </div>
                    </div>
                  </div>
                )}
              </div>

            </div>
          </div>

          {/* Order Summary */}
          <div>
            <div className="glass-card-solid p-5 sm:p-6 sticky top-24">
              <h2 className="text-lg font-semibold font-display mb-5">Order Summary</h2>

              <div className="space-y-2 mb-4 max-h-48 overflow-y-auto pr-1">
                {items.map(item => (
                  <div key={item.id} className="flex justify-between text-sm">
                    <span className="text-gray-500 truncate mr-2">{item.name} x {item.quantity}</span>
                    <span className="font-medium flex-shrink-0">₹{(item.price * item.quantity).toFixed(2)}</span>
                  </div>
                ))}
              </div>

              <div className="border-t border-gray-100 pt-4 space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Subtotal</span>
                  <span className="font-medium">₹{subtotal.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Delivery Fee</span>
                  <span className="font-medium">{deliveryFee === 0 ? <span className="text-emerald-600">FREE</span> : `₹${deliveryFee}`}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Tax</span>
                  <span className="font-medium">₹{tax.toFixed(2)}</span>
                </div>
                <div className="flex justify-between font-bold text-lg pt-2 border-t border-gray-100">
                  <span>Total</span>
                  <span className="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">₹{total.toFixed(2)}</span>
                </div>
              </div>

              <button
                onClick={handleSubmit}
                disabled={isProcessing || distance === null || !isEligibleForDelivery || !isAddressComplete}
                className="btn-primary w-full mt-6 flex justify-center items-center shimmer-btn disabled:opacity-50"
              >
                {isProcessing ? (
                  <div className="flex items-center">
                    <div className="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent mr-2"></div>
                    Processing...
                  </div>
                ) : (
                  <>
                    <Check className="h-5 w-5 mr-2" />
                    Place Order (Cash on Delivery)
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CheckoutPage;
