import React, { useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { Star, Plus, Minus, ShoppingCart, ArrowLeft, Shield, Truck, Clock, Users, Share2 } from "lucide-react";
import { useCart } from "../contexts/CartContext";

const ProductDetail: React.FC = () => {
  const navigate = useNavigate();
  const { addToCart } = useCart();
  const { state } = useLocation();
  const [quantity, setQuantity] = useState(1);

  const product = state?.product;

  if (!product) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center animate-fade-in-up">
          <div className="text-6xl font-bold text-gray-200 mb-4">🔍</div>
          <p className="text-gray-500 text-lg mb-4">Product not found.</p>
          <button onClick={() => navigate("/")} className="btn-primary">
            Back to Home
          </button>
        </div>
      </div>
    );
  }

  const handleAddToCart = () => {
    for (let i = 0; i < quantity; i++) {
      addToCart({
        id: product.id,
        name: product.name,
        price: product.price,
        image: product.image,
        category: product.category,
        rating: product.rating,
        discount: product.discount,
      });
    }
  };

  return (
    <div className="min-h-screen">
      <div className="max-w-6xl mx-auto px-4 py-8 lg:py-12">
        <button
          onClick={() => navigate(-1)}
          className="flex items-center text-gray-500 hover:text-gray-900 mb-8 transition-colors duration-200 group"
        >
          <ArrowLeft className="h-5 w-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200" />
          <span className="font-medium">Back</span>
        </button>

        <div className="glass-card-solid overflow-hidden animate-fade-in-up">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-0">
            {/* Product Image */}
            <div className="relative bg-gradient-to-br from-gray-50 to-gray-100 p-8 flex items-center justify-center">
              {product.discount > 0 && (
                <div className="absolute top-4 left-4 badge-discount text-xs px-3 py-1.5 z-10">
                  -{product.discount}% OFF
                </div>
              )}
              <img
                src={product.image}
                alt={product.name}
                className="w-full max-w-sm h-80 object-contain hover:scale-105 transition-transform duration-700"
              />
            </div>

            {/* Product Info */}
            <div className="p-8 lg:p-10 space-y-6">
              <div>
                <h1 className="text-2xl lg:text-3xl font-bold font-display text-gray-900 mb-3">{product.name}</h1>
                <div className="flex items-center gap-2">
                  <div className="flex items-center bg-amber-50 px-2.5 py-1 rounded-lg">
                    <Star className="h-4 w-4 text-amber-400 fill-current" />
                    <span className="ml-1 text-sm font-semibold text-amber-700">{product.rating}</span>
                  </div>
                  <span className="text-gray-400 text-sm">|</span>
                  <span className="badge-stock text-xs">In Stock</span>
                </div>
              </div>

              <p className="text-gray-500 leading-relaxed">
                High-quality product at an affordable price. Fresh and carefully selected for the best quality.
              </p>

              {/* Price */}
              <div className="flex items-baseline gap-3 pt-2">
                <span className="text-3xl font-bold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                  ₹{product.price}
                </span>
                {product.discount > 0 && (
                  <span className="text-lg text-gray-400 line-through">
                    ₹{Math.round(product.price / (1 - product.discount / 100))}
                  </span>
                )}
              </div>

              {/* Quantity & Add to Cart */}
              <div className="flex items-center gap-4 pt-2">
                <div className="flex items-center bg-gray-50 rounded-xl overflow-hidden border border-gray-200">
                  <button onClick={() => setQuantity(Math.max(1, quantity - 1))} className="p-3 hover:bg-gray-100 transition">
                    <Minus className="h-4 w-4 text-gray-500" />
                  </button>
                  <span className="px-5 py-2 text-lg font-semibold min-w-[48px] text-center">{quantity}</span>
                  <button onClick={() => setQuantity(quantity + 1)} className="p-3 hover:bg-gray-100 transition">
                    <Plus className="h-4 w-4 text-gray-500" />
                  </button>
                </div>
                <button onClick={handleAddToCart} className="btn-primary flex-1 flex items-center justify-center shimmer-btn">
                  <ShoppingCart className="h-5 w-5 mr-2" />
                  Add to Cart
                </button>
              </div>

              {/* DealShare Group Buying & Sharing */}
              <div className="flex flex-col sm:flex-row gap-3 pt-4 border-t border-emerald-100/50">
                <button
                  onClick={() => {
                    alert(`✅ Invite sent via WhatsApp! Once your friend buys ${product.name}, you both get an extra 10% off.`);
                  }}
                  className="flex-1 btn-secondary flex items-center justify-center bg-emerald-50 text-emerald-700 hover:bg-emerald-100 !border-0 transform hover:-translate-y-0.5 transition-all shadow-sm"
                >
                  <Users className="h-5 w-5 mr-2" />
                  <div className="flex flex-col items-start text-left">
                    <span className="text-sm font-bold leading-tight">Buy with a Friend</span>
                    <span className="text-[10px] font-semibold opacity-80 leading-tight">Get Extra 10% Off</span>
                  </div>
                </button>
                <button
                  onClick={() => {
                    alert(`🔗 Link copied to clipboard! Share ${product.name} with your network to earn ₹50.`);
                  }}
                  className="flex-1 btn-secondary flex items-center justify-center bg-gray-50 text-gray-700 hover:bg-gray-100 !border-0 transform hover:-translate-y-0.5 transition-all shadow-sm"
                >
                  <Share2 className="h-5 w-5 mr-2 text-emerald-600" />
                  <div className="flex flex-col items-start text-left">
                    <span className="text-sm font-bold leading-tight">Share & Earn</span>
                    <span className="text-[10px] font-semibold text-emerald-600 leading-tight">Win ₹50 Cashback</span>
                  </div>
                </button>
              </div>

              {/* Guarantees */}
              <div className="grid grid-cols-3 gap-3 pt-4 border-t border-gray-100">
                {[
                  { icon: <Truck className="h-4 w-4" />, text: 'Fast Delivery' },
                  { icon: <Shield className="h-4 w-4" />, text: 'Quality Assured' },
                  { icon: <Clock className="h-4 w-4" />, text: '30 Min Delivery' },
                ].map((g, i) => (
                  <div key={i} className="flex flex-col items-center text-center gap-1.5">
                    <div className="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center text-emerald-600">
                      {g.icon}
                    </div>
                    <span className="text-xs text-gray-500 font-medium">{g.text}</span>
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
