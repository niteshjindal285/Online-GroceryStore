import React, { useState, useEffect } from "react";
import { Search } from "lucide-react";
import { mockProducts, Product } from "../data/mockProducts";
import { useCart } from "../contexts/CartContext";
import { Link, useSearchParams } from "react-router-dom";

const UserDashboard: React.FC = () => {
  const [searchParams] = useSearchParams();
  const categoryParam = searchParams.get("category");
  const searchParam = searchParams.get("search");

  const [searchTerm, setSearchTerm] = useState(searchParam || "");
  const [selectedCategory, setSelectedCategory] = useState(categoryParam || "all");
  const [sortBy, setSortBy] = useState("name");
  const { addToCart } = useCart();

  const categories = [
    { id: "all", name: "All Products" },
    { id: "spices-herbs", name: "Spices & Herbs" },
    { id: "cooking-oil", name: "Cooking Oil" },
    { id: "sugar-salt-jaggery", name: "Sugar, Salt & Jaggery" },
    { id: "flours-grains", name: "Flours & Grains" },
    { id: "rice-products", name: "Rice & Rice Products" },
    { id: "dals-pulses", name: "Dals & Pulses" },
    { id: "ghee-vanaspati", name: "Ghee & Vanaspati" },
    { id: "dry-fruits-nuts", name: "Dry Fruits & Nuts" },
    { id: "beverages", name: "Beverages" },
    { id: "cleaning-home-care", name: "Cleaning & Home Care" },
    { id: "personal-care", name: "Personal Care" },
    { id: "fruits-veggies", name: "Fruits & Veggies" },
    { id: "electronics", name: "Electronics & Accessories" },
  ];

  const products = mockProducts;

  const filteredProducts = products.filter((product) => {
    const matchesSearch = product.name.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = selectedCategory === "all" || product.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const sortedProducts = [...filteredProducts].sort((a, b) => {
    switch (sortBy) {
      case "price-low": return a.price - b.price;
      case "price-high": return b.price - a.price;
      case "rating": return (Number(b.rating) || 0) - (Number(a.rating) || 0);
      default: return a.name.localeCompare(b.name);
    }
  });

  useEffect(() => {
    console.log("Dashboard Category Param:", categoryParam);
    if (categoryParam) {
      setSelectedCategory(categoryParam);
    }
  }, [categoryParam]);

  useEffect(() => {
    if (searchParam) {
      setSearchTerm(searchParam);
    }
  }, [searchParam]);

  useEffect(() => {
    console.log("Current Selected Category:", selectedCategory);
    console.log("Filtered Products Count:", filteredProducts.length);
  }, [selectedCategory, filteredProducts.length]);

  const handleAddToCart = (product: Product) => {
    addToCart({
      id: product.id,
      name: product.name,
      price: product.price,
      image: product.image,
      category: product.category,
      rating: product.rating ? Number(product.rating) : undefined,
      discount: product.discount ? Number(product.discount) : undefined,
    });
  };

  return (
    <div className="min-h-screen">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl lg:text-3xl font-bold font-display text-gray-900 border-b-2 border-emerald-500 pb-1 inline-block">
              All Products
            </h1>
            <p className="text-gray-500 mt-2 text-sm">Discover fresh products for your daily needs</p>
          </div>
          <div className="text-sm font-medium text-gray-500 bg-white px-4 py-2 rounded-lg border border-gray-200">
            Showing {filteredProducts.length} of {products.length} items
          </div>
        </div>

        <div className="flex flex-col lg:flex-row gap-8">
          {/* Sidebar / Category Strip */}
          <div className="w-full lg:w-64 flex-shrink-0">
            <div className="bg-white border border-gray-100 rounded-2xl p-4 lg:sticky lg:top-24 shadow-sm">
              <h3 className="hidden lg:block font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">Categories</h3>
              <div className="flex lg:flex-col overflow-x-auto lg:overflow-y-auto hide-scrollbar lg:custom-scrollbar space-x-2 lg:space-x-0 lg:space-y-1 lg:max-h-[60vh] pb-2 lg:pb-0 lg:pr-2">
                <button
                  onClick={() => setSelectedCategory('all')}
                  className={`text-left whitespace-nowrap shrink-0 lg:w-full px-4 py-2 lg:px-3 rounded-lg text-sm font-medium transition-colors ${selectedCategory === 'all' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 bg-gray-50/50 lg:bg-transparent hover:bg-gray-50 hover:text-emerald-600'}`}
                >
                  All Categories
                </button>
                {categories.map((category) => (
                  <button
                    key={category.id}
                    onClick={() => setSelectedCategory(category.id)}
                    className={`text-left whitespace-nowrap shrink-0 lg:w-full px-4 py-2 lg:px-3 rounded-lg text-sm font-medium transition-colors flex justify-between items-center ${selectedCategory === category.id ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/50' : 'text-gray-600 bg-gray-50/50 lg:bg-transparent hover:bg-gray-50 hover:text-emerald-600'}`}
                  >
                    <span className="line-clamp-1">{category.name}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Main Content */}
          <div className="flex-1">

            {/* Search Top Bar */}
            <div className="bg-white border border-gray-100 p-4 rounded-xl mb-6 shadow-sm flex flex-col sm:flex-row gap-4">
              <div className="relative flex-1 group">
                <Search className="absolute left-3.5 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400 group-focus-within:text-emerald-500 transition-colors" />
                <input
                  type="text"
                  placeholder="Search products..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all duration-300 text-sm"
                />
              </div>

              <div className="w-full sm:w-48 shrink-0">
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value)}
                  className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm text-gray-700 appearance-none bg-no-repeat bg-[right_1rem_center] transition-all duration-300 hover:bg-gray-100"
                  style={{ backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`, backgroundSize: '1.25rem' }}
                >
                  <option value="name">Sort by: Name</option>
                  <option value="price-low">Price: Low to High</option>
                  <option value="price-high">Price: High to Low</option>
                  <option value="rating">Highest Rated</option>
                </select>
              </div>
            </div>

            {/* Products Grid */}
            {sortedProducts.length > 0 ? (
              <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4 lg:gap-6">
                {sortedProducts.map((product) => (
                  <div
                    key={product.id}
                    className="bg-white border border-gray-100 hover:border-emerald-200 hover:shadow-md rounded-xl p-4 transition-all duration-300 flex flex-col relative group"
                  >
                    {product.discount > 0 && (
                      <div className="absolute top-0 left-0 bg-rose-500 text-white text-[10px] font-bold px-2 py-1 rounded-br-xl rounded-tl-xl z-10 w-max shadow-sm">
                        {product.discount}% OFF
                      </div>
                    )}

                    <Link to={`/products/${product.id}`} state={{ product }} className="relative h-36 md:h-44 mb-4 block overflow-hidden rounded-xl mx-auto w-full group-hover:scale-[1.03] transition-transform duration-500 bg-gray-50/50">
                      <img
                        src={product.image}
                        alt={product.name}
                        className="w-full h-full object-contain mix-blend-multiply p-2"
                      />
                    </Link>

                    <div className="flex flex-col flex-1 text-left">
                      <div className="flex items-center mb-1 text-[10px] text-gray-500 bg-gray-50 w-max px-2 py-0.5 rounded-full uppercase font-bold tracking-wider">
                        {product.category.replace('-', ' ')}
                      </div>
                      <Link to={`/products/${product.id}`} state={{ product }}>
                        <h3 className="font-semibold text-gray-900 text-sm mb-1 line-clamp-2 hover:text-emerald-600 transition-colors leading-snug">
                          {product.name}
                        </h3>
                      </Link>

                      <div className="mt-auto pt-3 flex items-center justify-between border-t border-gray-50">
                        <div>
                          <div className="text-xs text-gray-400 line-through">
                            ₹{Math.round(product.price / (1 - product.discount / 100))}
                          </div>
                          <div className="text-base font-bold text-gray-900 leading-none">
                            ₹{product.price}
                          </div>
                        </div>
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            handleAddToCart(product);
                          }}
                          disabled={!product.inStock}
                          className="bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white px-3 py-1.5 rounded-lg text-sm font-bold transition-colors shadow-sm whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {product.inStock ? "ADD" : "OUT"}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-20 bg-white border border-gray-100 rounded-xl mt-4">
                <div className="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                  <Search className="h-10 w-10 text-gray-300" />
                </div>
                <h2 className="text-xl font-bold font-display text-gray-900 mb-2">No products found</h2>
                <p className="text-gray-500 mb-6 text-sm">Try adjusting your search or filters to find what you're looking for.</p>
                <button
                  onClick={() => { setSearchTerm(""); setSelectedCategory("all"); }}
                  className="btn-secondary text-sm !px-6"
                >
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
