import React from 'react';
import { Link } from 'react-router-dom';
import { Star, Heart } from 'lucide-react';
import { Product } from '../data/mockProducts';
import { useCart } from '../contexts/CartContext';
import { useToast } from '../contexts/ToastContext';

interface ProductCardProps {
  product: Product;
  onAddToCart?: () => void;
}

export const ProductCard: React.FC<ProductCardProps> = ({ product, onAddToCart }) => {
  const { addToCart } = useCart();
  const { showToast } = useToast();
  const [isWishlisted, setIsWishlisted] = React.useState(false);

  const handleAddToCart = (e: React.MouseEvent) => {
    e.preventDefault();
    addToCart({
      id: product.id,
      name: product.name,
      price: product.price,
      image: product.image,
      category: product.category,
      rating: product.rating ? Number(product.rating) : undefined,
      discount: product.discount ? Number(product.discount) : undefined,
    });
    showToast(`${product.name} added to cart!`, 'success');
    onAddToCart?.();
  };

  const handleWishlist = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsWishlisted(!isWishlisted);
    showToast(
      isWishlisted ? 'Removed from wishlist' : 'Added to wishlist',
      isWishlisted ? 'info' : 'success'
    );
  };

  const rating = typeof product.rating === 'string' ? parseFloat(product.rating) : (product.rating || 0);
  const originalPrice = product.discount > 0 
    ? Math.round(product.price / (1 - product.discount / 100))
    : product.price;

  return (
    <div className="bg-white border border-gray-100 hover:border-emerald-200 hover:shadow-md rounded-xl p-4 transition-all duration-300 flex flex-col relative group">
      {product.discount > 0 && (
        <div className="absolute top-0 left-0 bg-rose-500 text-white text-[10px] font-bold px-2 py-1 rounded-br-xl rounded-tl-xl z-10 w-max shadow-sm">
          {product.discount}% OFF
        </div>
      )}

      {/* Wishlist Button */}
      <button
        onClick={handleWishlist}
        className="absolute top-2 right-2 z-10 p-2 bg-white/90 backdrop-blur-sm rounded-full shadow-sm hover:bg-white transition-all opacity-0 group-hover:opacity-100"
        aria-label={isWishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
      >
        <Heart
          className={`h-4 w-4 transition-colors ${
            isWishlisted ? 'fill-rose-500 text-rose-500' : 'text-gray-400 hover:text-rose-500'
          }`}
        />
      </button>

      <Link
        to={`/products/${product.id}`}
        state={{ product }}
        className="relative h-36 md:h-44 mb-4 block overflow-hidden rounded-xl mx-auto w-full group-hover:scale-[1.03] transition-transform duration-500 bg-gray-50/50"
      >
        <img
          src={product.image}
          alt={product.name}
          className="w-full h-full object-contain mix-blend-multiply p-2"
          loading="lazy"
        />
      </Link>

      <div className="flex flex-col flex-1 text-left">
        <div className="flex items-center mb-1 text-[10px] text-gray-500 bg-gray-50 w-max px-2 py-0.5 rounded-full uppercase font-bold tracking-wider">
          {product.category.replace(/-/g, ' ')}
        </div>

        <Link to={`/products/${product.id}`} state={{ product }}>
          <h3 className="font-semibold text-gray-900 text-sm mb-2 line-clamp-2 hover:text-emerald-600 transition-colors leading-snug">
            {product.name}
          </h3>
        </Link>

        {/* Rating */}
        {rating > 0 && (
          <div className="flex items-center gap-1 mb-2">
            <div className="flex items-center">
              {[1, 2, 3, 4, 5].map((star) => (
                <Star
                  key={star}
                  className={`h-3 w-3 ${
                    star <= Math.round(rating)
                      ? 'fill-amber-400 text-amber-400'
                      : 'text-gray-300'
                  }`}
                />
              ))}
            </div>
            <span className="text-xs text-gray-500 ml-1">({rating.toFixed(1)})</span>
          </div>
        )}

        <div className="mt-auto pt-3 flex items-center justify-between border-t border-gray-50">
          <div>
            {product.discount > 0 && (
              <div className="text-xs text-gray-400 line-through">
                ₹{originalPrice}
              </div>
            )}
            <div className="text-base font-bold text-gray-900 leading-none">
              ₹{product.price}
            </div>
          </div>
          <button
            onClick={handleAddToCart}
            disabled={!product.inStock}
            className="bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white px-3 py-1.5 rounded-lg text-sm font-bold transition-colors shadow-sm whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {product.inStock ? 'ADD' : 'OUT'}
          </button>
        </div>
      </div>
    </div>
  );
};
