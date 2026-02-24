import React, { useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ShoppingCart, Menu, X, Search, MapPin } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { useCart } from '../contexts/CartContext';

const Navbar: React.FC = () => {
  const { user, logout } = useAuth();
  const { getTotalItems, getTotalPrice } = useCart();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [isPincodeModalOpen, setIsPincodeModalOpen] = useState(false);
  const [pincode, setPincode] = useState('302001');
  const [tempPincode, setTempPincode] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const location = useLocation();
  const navigate = useNavigate();

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/dashboard?search=${encodeURIComponent(searchQuery.trim())}`);
      setIsMenuOpen(false);
    }
  };

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const navItems = [
    { name: 'Home', href: '/' },
    { name: 'Products', href: '/dashboard' },
    { name: 'About', href: '/about' },
    { name: 'Contact', href: '/contact' },
  ];

  const getDashboardLink = () => {
    if (user?.role === 'admin') return '/admin';
    if (user?.role === 'vendor') return '/vendor';
    return '/dashboard';
  };

  return (
    <nav className={`sticky top-0 z-50 transition-all duration-500 ${scrolled
      ? 'bg-white/80 backdrop-blur-xl shadow-glass'
      : 'bg-white/95 backdrop-blur-sm shadow-sm'
      }`}>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16 lg:h-18">
          {/* Logo */}
          <Link to="/" className="flex items-center space-x-3 group">
            <div className="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-2.5 rounded-xl shadow-md group-hover:shadow-glow transition-all duration-300 group-hover:scale-105">
              <MapPin className="h-5 w-5" />
            </div>
            <span className="text-xl lg:text-2xl font-bold font-display bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-500 bg-clip-text text-transparent">
              Balaji Trading Company
            </span>
          </Link>

          {/* Desktop Navigation */}
          <div className="hidden lg:flex items-center space-x-8">``
            {navItems.map((item) => (
              <Link
                key={item.name}
                to={item.href}
                className={`nav-link py-1 ${location.pathname === item.href ? 'active' : ''
                  }`}
              >
                {item.name}
              </Link>
            ))}
          </div>

          {/* Location Selector */}
          <div className="hidden lg:flex items-center ml-6 mr-auto">
            <button
              onClick={() => setIsPincodeModalOpen(true)}
              className="flex items-center space-x-1 p-2 rounded-xl text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-all duration-300 shadow-sm"
              title="Change Delivery Location"
            >
              <div className="w-7 h-7 bg-white rounded-lg flex items-center justify-center shadow-sm text-emerald-600">
                <MapPin className="h-4 w-4" />
              </div>
              <div className="flex flex-col items-start px-1">
                <span className="text-[10px] font-bold uppercase text-emerald-600/70 leading-tight">Deliver to</span>
                <span className="text-xs font-bold leading-tight">{pincode}</span>
              </div>
            </button>
          </div>

          {/* Search Bar */}
          <div className="hidden lg:flex items-center flex-1 max-w-2xl mx-8">
            <form onSubmit={handleSearch} className="relative w-full group">
              <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-500 group-focus-within:text-emerald-600 transition-colors duration-300" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search for groceries, essentials and more..."
                className="w-full pl-12 pr-4 py-3 bg-gray-100 border border-transparent rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent focus:bg-white transition-all duration-300 text-sm shadow-sm"
              />
            </form>
          </div>

          {/* Right Side Actions */}
          <div className="flex items-center space-x-4">
            {/* Cart */}
            <Link
              to="/cart"
              className="flex items-center gap-2 bg-emerald-50 px-4 py-2 rounded-xl text-emerald-700 hover:bg-emerald-100 transition-all duration-300 shadow-sm"
            >
              <div className="relative">
                <ShoppingCart className="h-5 w-5" />
                {getTotalItems() > 0 && (
                  <span className="absolute -top-2 -right-2 bg-rose-500 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-md animate-scale-in">
                    {getTotalItems()}
                  </span>
                )}
              </div>
              <div className="hidden sm:flex flex-col items-start leading-none ml-1">
                <span className="text-[10px] font-semibold opacity-80">My Cart</span>
                <span className="text-sm font-bold">₹{getTotalPrice()}</span>
              </div>
            </Link>

            {/* User Menu */}
            {user ? (
              <div className="relative">
                <button
                  onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                  className="flex items-center space-x-2 p-2 rounded-xl hover:bg-gray-50 transition-all duration-300"
                >
                  <div className="w-8 h-8 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-full flex items-center justify-center text-white text-sm font-semibold shadow-md">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                  <span className="hidden lg:block text-gray-700 font-medium text-sm">{user.name}</span>
                </button>

                {isUserMenuOpen && (
                  <div className="absolute right-0 mt-2 w-52 glass-card-solid p-2 animate-slide-down">
                    <Link
                      to={getDashboardLink()}
                      className="flex items-center px-3 py-2.5 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 rounded-lg transition-all duration-200 text-sm"
                      onClick={() => setIsUserMenuOpen(false)}
                    >
                      Dashboard
                    </Link>
                    <Link
                      to="/profile"
                      className="flex items-center px-3 py-2.5 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 rounded-lg transition-all duration-200 text-sm"
                      onClick={() => setIsUserMenuOpen(false)}
                    >
                      Profile
                    </Link>
                    <Link
                      to="/orders"
                      className="flex items-center px-3 py-2.5 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 rounded-lg transition-all duration-200 text-sm"
                      onClick={() => setIsUserMenuOpen(false)}
                    >
                      Order History
                    </Link>
                    <div className="border-t border-gray-100 my-1"></div>
                    <button
                      onClick={() => {
                        logout();
                        setIsUserMenuOpen(false);
                      }}
                      className="w-full text-left px-3 py-2.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-all duration-200 text-sm"
                    >
                      Logout
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <div className="hidden lg:flex items-center space-x-2">
                <Link
                  to="/login"
                  className="px-4 py-2 text-emerald-600 hover:text-emerald-700 font-medium text-sm transition-colors duration-300"
                >
                  Login
                </Link>
                <Link
                  to="/signup"
                  className="btn-primary text-sm !py-2 !px-5"
                >
                  Sign Up
                </Link>
              </div>
            )}

            {/* Mobile Menu Button */}
            <button
              onClick={() => setIsMenuOpen(!isMenuOpen)}
              className="lg:hidden p-2.5 rounded-xl text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-300"
            >
              {isMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </button>
          </div>
        </div>

        {/* Mobile Menu */}
        {isMenuOpen && (
          <div className="lg:hidden border-t border-gray-100 animate-slide-down">
            <div className="px-2 pt-3 pb-4 space-y-1">
              {/* Mobile Search */}
              <form onSubmit={handleSearch} className="relative mb-3">
                <Search className="absolute left-3.5 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search products..."
                  className="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 text-sm"
                />
              </form>
              {navItems.map((item, i) => (
                <Link
                  key={item.name}
                  to={item.href}
                  className={`block px-4 py-3 rounded-xl text-gray-700 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-200 font-medium animate-fade-in-up opacity-0`}
                  style={{ animationDelay: `${i * 80}ms`, animationFillMode: 'forwards' }}
                  onClick={() => setIsMenuOpen(false)}
                >
                  {item.name}
                </Link>
              ))}
              {!user && (
                <div className="pt-3 border-t border-gray-100 space-y-2 mt-2">
                  <Link
                    to="/login"
                    className="block px-4 py-3 text-emerald-600 hover:bg-emerald-50 rounded-xl font-medium transition-all duration-200"
                    onClick={() => setIsMenuOpen(false)}
                  >
                    Login
                  </Link>
                  <Link
                    to="/signup"
                    className="block px-4 py-3 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-xl font-semibold text-center shadow-md"
                    onClick={() => setIsMenuOpen(false)}
                  >
                    Sign Up
                  </Link>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Pincode Modal */}
        {isPincodeModalOpen && (
          <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm animate-fade-in">
            <div className="glass-card-solid p-6 w-full max-w-sm shadow-xl animate-scale-in mx-4">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-bold font-display text-gray-900">Choose your location</h3>
                <button onClick={() => setIsPincodeModalOpen(false)} className="text-gray-400 hover:text-gray-600 transition-colors p-1 hover:bg-gray-100 rounded-full">
                  <X className="h-5 w-5" />
                </button>
              </div>
              <p className="text-sm text-gray-500 mb-5 leading-relaxed">
                Delivery options and speeds may vary for different locations. Enter your pincode to check availability.
              </p>
              <div className="flex space-x-2">
                <input
                  type="text"
                  placeholder="Enter 6-digit Pincode"
                  value={tempPincode}
                  onChange={(e) => setTempPincode(e.target.value.replace(/[^0-9]/g, ''))}
                  maxLength={6}
                  className="input flex-1 !py-2.5 text-center font-bold tracking-widest placeholder:tracking-normal"
                />
                <button
                  onClick={() => {
                    if (tempPincode.length === 6) {
                      setPincode(tempPincode);
                      setIsPincodeModalOpen(false);
                      setTempPincode('');
                    }
                  }}
                  disabled={tempPincode.length !== 6}
                  className="btn-primary !py-2.5 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Apply
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </nav>
  );
};

export default Navbar;