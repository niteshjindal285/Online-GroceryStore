import React, { useState, useEffect, useRef } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  ShoppingCart, Menu, X, Search, MapPin, ShoppingBag,
  LayoutGrid, User, ReceiptText, LogOut, ChevronDown,
  Home, Phone, Info, Shield
} from 'lucide-react';
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
  const dropRef = useRef<HTMLDivElement>(null);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/dashboard?search=${encodeURIComponent(searchQuery.trim())}`);
      setIsMenuOpen(false);
    }
  };

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // Close dropdown on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (dropRef.current && !dropRef.current.contains(e.target as Node))
        setIsUserMenuOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // Close mobile menu on route change
  useEffect(() => { setIsMenuOpen(false); setIsUserMenuOpen(false); }, [location.pathname]);

  const navItems = [
    { name: 'Home', href: '/', icon: <Home className="h-4 w-4" /> },
    { name: 'Products', href: '/dashboard', icon: <LayoutGrid className="h-4 w-4" /> },
    { name: 'About', href: '/about', icon: <Info className="h-4 w-4" /> },
    { name: 'Contact', href: '/contact', icon: <Phone className="h-4 w-4" /> },
  ];

  const getDashboardLink = () => {
    if (user?.role === 'admin') return '/admin';
    if (user?.role === 'vendor') return '/vendor';
    return '/dashboard';
  };

  const initials = user?.name?.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2) || 'U';
  const totalItems = getTotalItems();
  const totalPrice = getTotalPrice();

  const isActive = (href: string) =>
    href === '/' ? location.pathname === '/' : location.pathname.startsWith(href);

  return (
    <>
      <nav className={`sticky top-0 z-50 transition-all duration-500 ${scrolled
        ? 'bg-white/80 backdrop-blur-xl shadow-lg shadow-black/5'
        : 'bg-white shadow-sm'
        }`}>
        {/* Emerald top accent */}
        <div className="h-[2px] bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500" />

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center h-16 gap-4 lg:gap-6">

            {/* ── Logo ── */}
            <Link to="/" className="flex items-center gap-2.5 group flex-shrink-0">
              <div className="bg-gradient-to-br from-emerald-500 to-teal-500 text-white p-2 rounded-xl shadow-md shadow-emerald-500/20 group-hover:shadow-emerald-500/30 group-hover:scale-105 transition-all duration-300">
                <ShoppingBag className="h-5 w-5" />
              </div>
              <div className="hidden sm:block leading-tight">
                <span className="block text-base font-extrabold font-display bg-gradient-to-r from-emerald-700 to-teal-600 bg-clip-text text-transparent leading-none">
                  Balaji Trading Company
                </span>
              </div>
            </Link>

            {/* ── Desktop Nav Links ── */}
            <div className="hidden lg:flex items-center gap-1">
              {navItems.map(item => (
                <Link key={item.name} to={item.href}
                  className={`relative px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${isActive(item.href)
                    ? 'text-emerald-700 bg-emerald-50'
                    : 'text-gray-600 hover:text-emerald-600 hover:bg-gray-50'
                    }`}>
                  {item.name}
                  {isActive(item.href) && (
                    <span className="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-4 h-0.5 rounded-full bg-emerald-500" />
                  )}
                </Link>
              ))}
            </div>

            {/* ── Pincode / Location ── */}
            <div className="hidden xl:flex items-center">
              <button onClick={() => setIsPincodeModalOpen(true)}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-all duration-200 border border-emerald-100">
                <MapPin className="h-3.5 w-3.5 text-emerald-600 flex-shrink-0" />
                <div className="text-left leading-none">
                  <div className="text-[9px] font-bold uppercase text-emerald-500/80 tracking-wider">Deliver to</div>
                  <div className="text-xs font-bold text-emerald-700">{pincode}</div>
                </div>
                <ChevronDown className="h-3 w-3 text-emerald-500 ml-0.5" />
              </button>
            </div>

            {/* ── Search Bar ── */}
            <form onSubmit={handleSearch} className="hidden lg:flex flex-1 relative group max-w-lg mx-auto">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 group-focus-within:text-emerald-500 transition-colors pointer-events-none" />
              <input type="text" value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                placeholder="Search groceries, essentials and more…"
                className="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 focus:bg-white hover:border-gray-300 transition-all duration-300 placeholder-gray-400" />
            </form>

            {/* ── Right Actions ── */}
            <div className="flex items-center gap-2 ml-auto lg:ml-0">

              {/* Cart */}
              <Link to="/cart"
                className="relative flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 px-3 py-2 rounded-xl text-emerald-700 transition-all duration-200 group">
                <div className="relative">
                  <ShoppingCart className="h-4.5 w-4.5 h-5 w-5" />
                  {totalItems > 0 && (
                    <span className="absolute -top-2 -right-2 min-w-[18px] h-[18px] bg-rose-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-0.5 shadow-md shadow-rose-500/30 animate-bounce">
                      {totalItems > 99 ? '99+' : totalItems}
                    </span>
                  )}
                </div>
                <div className="hidden sm:block text-left leading-none">
                  <div className="text-[10px] font-semibold text-emerald-600/70 uppercase tracking-wide">My Cart</div>
                  <div className="text-sm font-extrabold text-emerald-700">₹{totalPrice}</div>
                </div>
              </Link>

              {/* User Menu / Auth Buttons */}
              {user ? (
                <div className="relative" ref={dropRef}>
                  <button onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                    className={`flex items-center gap-2 px-2 py-1.5 rounded-xl transition-all duration-200 ${isUserMenuOpen ? 'bg-gray-100' : 'hover:bg-gray-50'}`}>
                    <div className="w-8 h-8 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl flex items-center justify-center text-white text-sm font-bold shadow-sm flex-shrink-0">
                      {initials}
                    </div>
                    <div className="hidden lg:block text-left leading-none">
                      <div className="text-xs font-bold text-gray-900 truncate max-w-[80px]">{user.name}</div>
                      <div className="text-[10px] text-gray-400 capitalize">{user.role}</div>
                    </div>
                    <ChevronDown className={`hidden lg:block h-3.5 w-3.5 text-gray-400 transition-transform duration-200 ${isUserMenuOpen ? 'rotate-180' : ''}`} />
                  </button>

                  {/* Dropdown */}
                  {isUserMenuOpen && (
                    <div className="absolute right-0 mt-2 w-56 bg-white/95 backdrop-blur-xl border border-gray-100 rounded-2xl shadow-xl shadow-black/10 p-2 z-50">
                      {/* User header */}
                      <div className="flex items-center gap-3 px-3 py-3 mb-1 border-b border-gray-100">
                        <div className="w-9 h-9 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                          {initials}
                        </div>
                        <div className="min-w-0">
                          <p className="text-sm font-bold text-gray-900 truncate">{user.name}</p>
                          <p className="text-xs text-gray-400 truncate">{user.email}</p>
                        </div>
                      </div>

                      {[
                        { href: getDashboardLink(), label: 'Dashboard', icon: <LayoutGrid className="h-4 w-4" /> },
                        { href: '/profile', label: 'My Profile', icon: <User className="h-4 w-4" /> },
                        { href: '/orders', label: 'Order History', icon: <ReceiptText className="h-4 w-4" /> },
                      ].map(({ href, label, icon }) => (
                        <Link key={label} to={href} onClick={() => setIsUserMenuOpen(false)}
                          className="flex items-center gap-3 px-3 py-2.5 text-gray-700 hover:text-emerald-700 hover:bg-emerald-50 rounded-xl transition-all duration-200 text-sm font-medium">
                          <span className="text-gray-400">{icon}</span>
                          {label}
                        </Link>
                      ))}

                      <div className="border-t border-gray-100 my-1" />
                      <button onClick={() => { logout(); setIsUserMenuOpen(false); }}
                        className="w-full flex items-center gap-3 px-3 py-2.5 text-rose-600 hover:bg-rose-50 rounded-xl transition-all duration-200 text-sm font-medium">
                        <LogOut className="h-4 w-4" />
                        Sign Out
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                <div className="hidden lg:flex items-center gap-2">
                  <Link to="/login"
                    className="px-4 py-2 text-gray-600 hover:text-emerald-600 font-semibold text-sm transition-colors rounded-xl hover:bg-gray-50">
                    Sign In
                  </Link>
                  <Link to="/signup"
                    className="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold text-sm rounded-xl shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 hover:shadow-emerald-500/30 transition-all duration-300">
                    Sign Up
                  </Link>
                </div>
              )}

              {/* Mobile hamburger */}
              <button onClick={() => setIsMenuOpen(!isMenuOpen)}
                className="lg:hidden w-9 h-9 flex items-center justify-center rounded-xl text-gray-600 hover:text-emerald-600 hover:bg-emerald-50 transition-all duration-200">
                {isMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </button>
            </div>
          </div>
        </div>

        {/* ── Mobile Drawer ── */}
        {isMenuOpen && (
          <div className="lg:hidden border-t border-gray-100 bg-white">
            <div className="px-4 py-4 space-y-2">

              {/* Mobile Search */}
              <form onSubmit={handleSearch} className="relative mb-3">
                <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                <input type="text" value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  placeholder="Search products…"
                  className="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 text-sm" />
              </form>

              {navItems.map(item => (
                <Link key={item.name} to={item.href} onClick={() => setIsMenuOpen(false)}
                  className={`flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm transition-all duration-200 ${isActive(item.href)
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                    : 'text-gray-700 hover:bg-gray-50 hover:text-emerald-600'
                    }`}>
                  <span className={isActive(item.href) ? 'text-emerald-500' : 'text-gray-400'}>{item.icon}</span>
                  {item.name}
                </Link>
              ))}

              {/* Pincode */}
              <button onClick={() => { setIsPincodeModalOpen(true); setIsMenuOpen(false); }}
                className="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                <MapPin className="h-4 w-4 text-gray-400" />
                <span>Deliver to <strong>{pincode}</strong></span>
              </button>

              {/* Auth */}
              {!user ? (
                <div className="pt-2 border-t border-gray-100 space-y-2">
                  <Link to="/login" onClick={() => setIsMenuOpen(false)}
                    className="flex items-center justify-center gap-2 w-full px-4 py-3 border border-emerald-200 text-emerald-700 font-semibold text-sm rounded-xl hover:bg-emerald-50 transition-colors">
                    <User className="h-4 w-4" /> Sign In
                  </Link>
                  <Link to="/signup" onClick={() => setIsMenuOpen(false)}
                    className="flex items-center justify-center gap-2 w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold text-sm rounded-xl shadow-md shadow-emerald-500/20">
                    <ShoppingBag className="h-4 w-4" /> Create Account
                  </Link>
                </div>
              ) : (
                <div className="pt-2 border-t border-gray-100 space-y-1">
                  <div className="flex items-center gap-3 px-4 py-3">
                    <div className="w-9 h-9 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                      {initials}
                    </div>
                    <div>
                      <p className="font-bold text-gray-900 text-sm">{user.name}</p>
                      <p className="text-xs text-gray-400 capitalize">{user.role} account</p>
                    </div>
                  </div>
                  {[
                    { href: getDashboardLink(), label: 'Dashboard', icon: <LayoutGrid className="h-4 w-4" /> },
                    { href: '/profile', label: 'My Profile', icon: <User className="h-4 w-4" /> },
                    { href: '/orders', label: 'Order History', icon: <ReceiptText className="h-4 w-4" /> },
                  ].map(({ href, label, icon }) => (
                    <Link key={label} to={href} onClick={() => setIsMenuOpen(false)}
                      className="flex items-center gap-3 px-4 py-2.5 text-gray-700 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl text-sm font-medium transition-colors">
                      <span className="text-gray-400">{icon}</span>{label}
                    </Link>
                  ))}
                  <button onClick={() => { logout(); setIsMenuOpen(false); }}
                    className="w-full flex items-center gap-3 px-4 py-2.5 text-rose-600 hover:bg-rose-50 rounded-xl text-sm font-medium transition-colors">
                    <LogOut className="h-4 w-4" /> Sign Out
                  </button>
                </div>
              )}
            </div>
          </div>
        )}
      </nav>

      {/* ── Pincode Modal ── */}
      {isPincodeModalOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4"
          onClick={() => setIsPincodeModalOpen(false)}>
          <div className="bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl border border-gray-100"
            onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-center">
                  <MapPin className="h-5 w-5 text-emerald-600" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-gray-900">Choose Location</h3>
                  <p className="text-xs text-gray-400">Set your delivery pincode</p>
                </div>
              </div>
              <button onClick={() => setIsPincodeModalOpen(false)}
                className="w-8 h-8 flex items-center justify-center rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <X className="h-4 w-4" />
              </button>
            </div>

            <p className="text-sm text-gray-500 mb-4 leading-relaxed">
              Delivery options may vary by location. Enter your 6-digit pincode to check availability.
            </p>

            {/* Current pincode chip */}
            <div className="flex items-center gap-2 bg-emerald-50 border border-emerald-100 rounded-xl px-3 py-2 mb-4">
              <Shield className="h-3.5 w-3.5 text-emerald-500 flex-shrink-0" />
              <span className="text-xs text-emerald-700">Current: <strong>{pincode}</strong></span>
            </div>

            <div className="flex gap-2">
              <input type="text" placeholder="Enter 6-digit pincode" value={tempPincode}
                onChange={e => setTempPincode(e.target.value.replace(/[^0-9]/g, ''))}
                maxLength={6}
                className="flex-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-center font-bold text-gray-900 tracking-[0.3em] placeholder:tracking-normal placeholder:font-normal placeholder:text-gray-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-400 transition-all" />
              <button
                onClick={() => { if (tempPincode.length === 6) { setPincode(tempPincode); setIsPincodeModalOpen(false); setTempPincode(''); } }}
                disabled={tempPincode.length !== 6}
                className="px-5 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold rounded-xl shadow-md shadow-emerald-500/20 disabled:opacity-40 disabled:cursor-not-allowed hover:-translate-y-0.5 transition-all duration-300 text-sm whitespace-nowrap">
                Apply
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default Navbar;