import React, { useState } from "react";
import { useLocation, useNavigate, Link } from "react-router-dom";
import { Star, Plus, Minus, ShoppingCart, ArrowLeft, Shield, Truck, Clock, Users, Share2, ChevronRight, CheckCircle, Package } from "lucide-react";
import { useCart } from "../contexts/CartContext";
import { useToast } from "../contexts/ToastContext";

const ProductDetail: React.FC = () => {
  const navigate = useNavigate();
  const { addToCart } = useCart();
  const { showToast } = useToast();
  const { state } = useLocation();
  const [quantity, setQuantity] = useState(1);

  const product = state?.product;

  if (!product) {
    return (
      <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center px-4">
        <div className="text-center max-w-sm">
          <div className="relative w-32 h-32 mx-auto mb-6">
            <div className="absolute inset-0 bg-gradient-to-br from-gray-100 to-gray-50 rounded-3xl rotate-6" />
            <div className="absolute inset-0 bg-white rounded-3xl shadow-sm flex items-center justify-center text-5xl">🔍</div>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Product Not Found</h2>
          <p className="text-gray-500 mb-7">We couldn't find this product. It may have been removed.</p>
          <Link to="/dashboard"
            className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold px-7 py-3.5 rounded-xl shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 transition-all duration-300">
            Browse Products
          </Link>
        </div>
      </div>
    );
  }

  const originalPrice = product.discount > 0 ? Math.round(product.price / (1 - product.discount / 100)) : null;

  const handleAddToCart = () => {
    for (let i = 0; i < quantity; i++) {
      addToCart({ id: product.id, name: product.name, price: product.price, image: product.image, category: product.category, rating: product.rating, discount: product.discount });
    }
    showToast(`${quantity} ${quantity === 1 ? 'item' : 'items'} added to cart!`, 'success');
  };

  const guarantees = [
    { icon: <Truck className="h-4 w-4" />, label: "Fast Delivery", sub: "Same day", color: "text-blue-600", bg: "bg-blue-50" },
    { icon: <Shield className="h-4 w-4" />, label: "Quality Assured", sub: "100% fresh", color: "text-emerald-600", bg: "bg-emerald-50" },
    { icon: <Clock className="h-4 w-4" />, label: "30 Min Delivery", sub: "Nearby areas", color: "text-amber-600", bg: "bg-amber-50" },
    { icon: <Package className="h-4 w-4" />, label: "Easy Returns", sub: "No questions", color: "text-purple-600", bg: "bg-purple-50" },
  ];

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

        {/* Breadcrumb */}
        <div className="flex items-center gap-2 text-xs text-gray-400 mb-5">
          <Link to="/" className="hover:text-emerald-600 transition-colors">Home</Link>
          <ChevronRight className="h-3 w-3" />
          <Link to="/dashboard" className="hover:text-emerald-600 transition-colors">Products</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-gray-600 font-medium truncate max-w-[180px]">{product.name}</span>
        </div>

        <button onClick={() => navigate(-1)}
          className="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 mb-7 group transition-colors">
          <span className="w-7 h-7 rounded-full bg-white border border-gray-200 flex items-center justify-center group-hover:bg-gray-50 transition-colors shadow-sm">
            <ArrowLeft className="h-3.5 w-3.5" />
          </span>
          Back
        </button>

        {/* Main Card */}
        <div className="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
          <div className="grid grid-cols-1 md:grid-cols-2">

            {/* Image Panel */}
            <div className="relative bg-gradient-to-br from-gray-50 to-gray-100 p-10 flex items-center justify-center min-h-[340px]">
              {product.discount > 0 && (
                <div className="absolute top-4 left-4 bg-rose-500 text-white text-xs font-bold px-3 py-1.5 rounded-xl z-10 shadow-md shadow-rose-500/25">
                  -{product.discount}% OFF
                </div>
              )}
              {!product.inStock && (
                <div className="absolute inset-0 bg-white/60 backdrop-blur-sm z-10 flex items-center justify-center rounded-l-3xl">
                  <span className="bg-gray-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl shadow-lg">Out of Stock</span>
                </div>
              )}
              <img src={product.image} alt={product.name}
                className="w-full max-w-[280px] h-72 object-contain hover:scale-105 transition-transform duration-700" />
            </div>

            {/* Info Panel */}
            <div className="p-8 lg:p-10 space-y-6 flex flex-col">

              {/* Category + Name */}
              <div>
                <span className="inline-block bg-gray-100 text-gray-500 text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-lg mb-3">
                  {product.category?.replace(/-/g, ' ')}
                </span>
                <h1 className="text-2xl lg:text-3xl font-bold font-display text-gray-900 leading-tight mb-3">{product.name}</h1>
                <div className="flex items-center gap-3">
                  <div className="flex items-center gap-1 bg-amber-50 border border-amber-100 px-2.5 py-1 rounded-lg">
                    <Star className="h-3.5 w-3.5 text-amber-500 fill-amber-500" />
                    <span className="text-xs font-bold text-amber-700">{product.rating}</span>
                  </div>
                  {product.inStock ? (
                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">
                      <CheckCircle className="h-3.5 w-3.5" /> In Stock
                    </span>
                  ) : (
                    <span className="text-xs font-semibold text-red-700 bg-red-50 border border-red-200 px-2.5 py-1 rounded-lg">Out of Stock</span>
                  )}
                </div>
              </div>

              {/* Description */}
              <p className="text-gray-500 text-sm leading-relaxed">
                High-quality product at an affordable price. Carefully sourced and selected to ensure freshness and best quality for your family.
              </p>

              {/* Price */}
              <div className="flex items-baseline gap-3">
                <span className="text-3xl font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                  ₹{product.price}
                </span>
                {originalPrice && (
                  <span className="text-lg text-gray-400 line-through">₹{originalPrice}</span>
                )}
                {product.discount > 0 && (
                  <span className="text-sm font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg">Save ₹{originalPrice! - product.price}</span>
                )}
              </div>

              {/* Qty + Cart */}
              <div className="flex items-center gap-3">
                <div className="flex items-center bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
                  <button onClick={() => setQuantity(Math.max(1, quantity - 1))}
                    className="w-10 h-11 flex items-center justify-center hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors">
                    <Minus className="h-4 w-4" />
                  </button>
                  <span className="w-10 text-center font-bold text-gray-900 text-base">{quantity}</span>
                  <button onClick={() => setQuantity(quantity + 1)}
                    className="w-10 h-11 flex items-center justify-center hover:bg-emerald-50 text-gray-500 hover:text-emerald-600 transition-colors">
                    <Plus className="h-4 w-4" />
                  </button>
                </div>
                <button onClick={handleAddToCart} disabled={!product.inStock}
                  className="flex-1 flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 text-sm">
                  <ShoppingCart className="h-4 w-4" />
                  Add to Cart · ₹{(product.price * quantity).toFixed(0)}
                </button>
              </div>

              {/* Social buttons */}
              <div className="flex gap-3 pt-2 border-t border-gray-100">
                <button onClick={() => alert(`✅ Invite sent! Once your friend buys ${product.name}, you both get an extra 10% off.`)}
                  className="flex-1 flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-100 font-semibold py-2.5 rounded-xl transition-colors text-sm">
                  <Users className="h-4 w-4" />
                  <div className="text-left leading-tight">
                    <div className="font-bold text-xs">Buy with Friend</div>
                    <div className="text-[10px] opacity-75">Extra 10% Off</div>
                  </div>
                </button>
                <button onClick={() => alert(`🔗 Link copied! Share ${product.name} and earn ₹50.`)}
                  className="flex-1 flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-100 font-semibold py-2.5 rounded-xl transition-colors text-sm">
                  <Share2 className="h-4 w-4 text-emerald-600" />
                  <div className="text-left leading-tight">
                    <div className="font-bold text-xs">Share & Earn</div>
                    <div className="text-[10px] text-emerald-600">Win ₹50 Cashback</div>
                  </div>
                </button>
              </div>

              {/* Guarantees */}
              <div className="grid grid-cols-4 gap-2 pt-2 border-t border-gray-100 mt-auto">
                {guarantees.map((g, i) => (
                  <div key={i} className="flex flex-col items-center text-center gap-1.5">
                    <div className={`w-9 h-9 ${g.bg} ${g.color} rounded-xl flex items-center justify-center`}>{g.icon}</div>
                    <span className="text-[10px] font-bold text-gray-700 leading-tight">{g.label}</span>
                    <span className="text-[9px] text-gray-400 leading-none">{g.sub}</span>
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

export default ProductDetail;
