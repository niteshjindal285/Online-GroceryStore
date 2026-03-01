import React, { useState, useEffect } from "react";
import { Search, AlertCircle, SlidersHorizontal, RefreshCw, LayoutGrid } from "lucide-react";
import { Product } from "../data/mockProducts";
import { getProducts } from "../utils/productUtils";
import { useCart } from "../contexts/CartContext";
import { useToast } from "../contexts/ToastContext";
import { Link, useSearchParams } from "react-router-dom";
import { ProductGridSkeleton } from "../components/ProductSkeleton";

const UserDashboard: React.FC = () => {
  const [searchParams] = useSearchParams();
  const categoryParam = searchParams.get("category");
  const searchParam = searchParams.get("search");

  const [searchTerm, setSearchTerm] = useState(searchParam || "");
  const [selectedCategory, setSelectedCategory] = useState(categoryParam || "all");
  const [sortBy, setSortBy] = useState("name");
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [products, setProducts] = useState<Product[]>([]);

  const { addToCart } = useCart();
  const { showToast } = useToast();

  const categories = [
    { id: "all", name: "All Products" },
    { id: "spices herbs", name: "Spices & Herbs" },
    { id: "cooking oil", name: "Cooking Oil" },
    { id: "sugar-salt-jaggery", name: "Sugar, Salt & Jaggery" },
    { id: "flours grains", name: "Flours & Grains" },
    { id: "rice products", name: "Rice Products" },
    { id: "dals pulses", name: "Dals & Pulses" },
    { id: "ghee vanaspati", name: "Ghee & Vanaspati" },
    { id: "dry fruits-nuts", name: "Dry Fruits & Nuts" },
    { id: "beverages", name: "Beverages" },
    { id: "cleaningn home-care", name: "Cleaning & Home Care" },
    { id: "personal care", name: "Personal Care" },
    { id: "fruits veggies", name: "Fruits & Veggies" },
    { id: "electronics", name: "Electronics" },
  ];

  const fetchProducts = async () => {
    try {
      setIsLoading(true); setError(null);
      const data = await getProducts();
      setProducts(data);
      if (data.length === 0) setError("No products available at the moment.");
    } catch {
      const msg = "Failed to load products. Please try again later.";
      setError(msg); showToast(msg, "error");
    } finally { setIsLoading(false); }
  };

  useEffect(() => { fetchProducts(); }, [showToast]);
  useEffect(() => { if (categoryParam) setSelectedCategory(categoryParam); }, [categoryParam]);
  useEffect(() => { if (searchParam) setSearchTerm(searchParam); }, [searchParam]);

  const filteredProducts = products.filter(p =>
    p.inStock &&
    p.name.toLowerCase().includes(searchTerm.toLowerCase()) &&
    (selectedCategory === "all" || p.category === selectedCategory)
  );

  const sortedProducts = [...filteredProducts].sort((a, b) => {
    switch (sortBy) {
      case "price-low": return a.price - b.price;
      case "price-high": return b.price - a.price;
      case "rating": return (Number(b.rating) || 0) - (Number(a.rating) || 0);
      default: return a.name.localeCompare(b.name);
    }
  });

  const handleAddToCart = (product: Product) => {
    addToCart({
      id: product.id, name: product.name, price: product.price,
      image: product.image, category: product.category,
      rating: product.rating ? Number(product.rating) : undefined,
      discount: product.discount ? Number(product.discount) : undefined,
    });
    showToast(`${product.name} added to cart!`, "success");
  };

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">

        {/* ── Page Header ── */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl lg:text-3xl font-bold font-display text-gray-900 flex items-center gap-3">
              <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
                <LayoutGrid className="h-5 w-5 text-white" />
              </div>
              All Products
            </h1>
            <p className="text-gray-400 text-sm mt-1">Discover fresh products for your daily needs</p>
          </div>
          <div className="flex items-center gap-2 bg-white border border-gray-100 rounded-xl px-4 py-2 text-sm shadow-sm">
            <span className="text-gray-400">Showing</span>
            <span className="font-bold text-gray-900">{filteredProducts.length}</span>
            <span className="text-gray-400">of</span>
            <span className="font-bold text-gray-900">{products.length}</span>
            <span className="text-gray-400">items</span>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row gap-6">

          {/* ── Sidebar ── */}
          <div className="w-full lg:w-56 flex-shrink-0">
            <div className="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm lg:sticky lg:top-24">
              <h3 className="hidden lg:flex items-center gap-2 font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100 text-sm">
                <SlidersHorizontal className="h-4 w-4 text-gray-400" /> Categories
              </h3>
              <div className="flex lg:flex-col overflow-x-auto lg:overflow-y-auto hide-scrollbar lg:max-h-[65vh] space-x-2 lg:space-x-0 lg:space-y-0.5 pb-2 lg:pb-0">
                {[{ id: "all", name: "All Products" }, ...categories.slice(1)].map(cat => (
                  <button key={cat.id} onClick={() => setSelectedCategory(cat.id)}
                    className={`text-left whitespace-nowrap shrink-0 lg:w-full px-3 py-2 rounded-xl text-sm font-medium transition-all duration-200 ${selectedCategory === cat.id
                        ? 'bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-700 font-semibold border border-emerald-100'
                        : 'text-gray-600 hover:bg-gray-50 hover:text-emerald-600'
                      }`}>
                    {cat.name}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* ── Main Content ── */}
          <div className="flex-1 min-w-0">

            {/* Search + Sort bar */}
            <div className="bg-white border border-gray-100 p-4 rounded-2xl mb-5 shadow-sm flex flex-col sm:flex-row gap-3">
              <div className="relative flex-1 group">
                <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 group-focus-within:text-emerald-500 transition-colors pointer-events-none" />
                <input type="text" placeholder="Search products…"
                  value={searchTerm} onChange={e => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all duration-300 text-sm placeholder-gray-400" />
              </div>
              <select value={sortBy} onChange={e => setSortBy(e.target.value)}
                className="w-full sm:w-44 shrink-0 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm text-gray-700 appearance-none cursor-pointer">
                <option value="name">Sort: Name</option>
                <option value="price-low">Price: Low → High</option>
                <option value="price-high">Price: High → Low</option>
                <option value="rating">Highest Rated</option>
              </select>
            </div>

            {/* Active filter pill */}
            {(searchTerm || selectedCategory !== "all") && (
              <div className="flex items-center gap-2 mb-4 flex-wrap">
                {selectedCategory !== "all" && (
                  <span className="inline-flex items-center gap-1.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-full">
                    {categories.find(c => c.id === selectedCategory)?.name}
                    <button onClick={() => setSelectedCategory("all")} className="ml-1 hover:text-red-500 transition-colors">×</button>
                  </span>
                )}
                {searchTerm && (
                  <span className="inline-flex items-center gap-1.5 text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200 px-3 py-1.5 rounded-full">
                    "{searchTerm}"
                    <button onClick={() => setSearchTerm("")} className="ml-1 hover:text-red-500 transition-colors">×</button>
                  </span>
                )}
                <button onClick={() => { setSearchTerm(""); setSelectedCategory("all"); }}
                  className="text-xs text-gray-400 hover:text-gray-600 transition-colors underline">
                  Clear all
                </button>
              </div>
            )}

            {/* States */}
            {isLoading ? (
              <ProductGridSkeleton count={9} />
            ) : error && products.length === 0 ? (
              <div className="text-center py-20 bg-white border border-gray-100 rounded-2xl">
                <div className="relative w-24 h-24 mx-auto mb-5">
                  <div className="absolute inset-0 bg-red-50 rounded-2xl rotate-6" />
                  <div className="absolute inset-0 bg-white rounded-2xl flex items-center justify-center shadow-sm">
                    <AlertCircle className="h-10 w-10 text-red-300" />
                  </div>
                </div>
                <h2 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Products</h2>
                <p className="text-gray-500 text-sm mb-6 max-w-xs mx-auto">{error}</p>
                <button onClick={fetchProducts}
                  className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold px-6 py-2.5 rounded-xl shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 transition-all text-sm">
                  <RefreshCw className="h-4 w-4" /> Retry
                </button>
              </div>
            ) : sortedProducts.length > 0 ? (
              <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4">
                {sortedProducts.map(product => (
                  <div key={product.id}
                    className="bg-white border border-gray-100 hover:border-emerald-200 hover:shadow-md rounded-2xl p-4 transition-all duration-300 flex flex-col relative group">
                    {product.discount > 0 && (
                      <div className="absolute top-3 left-3 bg-rose-500 text-white text-[10px] font-bold px-2 py-1 rounded-lg z-10 shadow-sm shadow-rose-500/20">
                        -{product.discount}% OFF
                      </div>
                    )}
                    <Link to={`/products/${product.id}`} state={{ product }}
                      className="relative h-36 md:h-44 mb-3 block overflow-hidden rounded-xl bg-gray-50 group-hover:bg-gray-50/70 transition-colors">
                      <img src={product.image} alt={product.name}
                        className="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-500" />
                    </Link>
                    <div className="flex flex-col flex-1">
                      <span className="text-[9px] font-bold uppercase tracking-widest text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full w-max mb-2">
                        {product.category.replace(/-/g, ' ')}
                      </span>
                      <Link to={`/products/${product.id}`} state={{ product }}>
                        <h3 className="font-semibold text-gray-900 text-sm mb-1 line-clamp-2 hover:text-emerald-600 transition-colors leading-snug">
                          {product.name}
                        </h3>
                      </Link>
                      <div className="mt-auto pt-3 flex items-center justify-between border-t border-gray-50">
                        <div>
                          {product.discount > 0 && (
                            <div className="text-[10px] text-gray-400 line-through">
                              ₹{Math.round(product.price / (1 - product.discount / 100))}
                            </div>
                          )}
                          <div className="text-base font-extrabold text-gray-900 leading-none">₹{product.price}</div>
                        </div>
                        <button onClick={e => { e.preventDefault(); handleAddToCart(product); }}
                          disabled={!product.inStock}
                          className="bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 px-3 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                          {product.inStock ? "ADD" : "OUT"}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-20 bg-white border border-gray-100 rounded-2xl">
                <div className="relative w-24 h-24 mx-auto mb-5">
                  <div className="absolute inset-0 bg-gray-100 rounded-2xl rotate-6" />
                  <div className="absolute inset-0 bg-white rounded-2xl flex items-center justify-center shadow-sm">
                    <Search className="h-10 w-10 text-gray-200" />
                  </div>
                </div>
                <h2 className="text-xl font-bold text-gray-900 mb-2">No products found</h2>
                <p className="text-gray-500 text-sm mb-6 max-w-xs mx-auto">Try adjusting your search or filters.</p>
                <button onClick={() => { setSearchTerm(""); setSelectedCategory("all"); }}
                  className="inline-flex items-center gap-2 border border-gray-200 text-gray-700 hover:bg-gray-50 font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors">
                  Clear All Filters
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default UserDashboard;
