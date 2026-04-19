import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Mail, Lock, Eye, EyeOff, ShoppingBag, ArrowRight, Star, Truck, Shield } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';

const LoginPage: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);
    try {
      const loggedInUser = await login(email, password);
      if (loggedInUser) {
        if (loggedInUser.role === 'admin') navigate('/admin');
        else if (loggedInUser.role === 'vendor') navigate('/vendor');
        else navigate('/dashboard');
      } else {
        setError('Invalid email or password. Please try again.');
      }
    } catch {
      setError('Something went wrong. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const inputBase =
    'w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 hover:border-gray-300 transition-all duration-200 placeholder-gray-400';

  const perks = [
    { icon: <Truck className="h-4 w-4" />, text: 'Fast delivery to your door' },
    { icon: <Shield className="h-4 w-4" />, text: '100% quality guarantee' },
    { icon: <Star className="h-4 w-4" />, text: 'Exclusive member-only deals' },
  ];

  const stats = [
    { value: '50K+', label: 'Customers' },
    { value: '500+', label: 'Products' },
    { value: '99.5%', label: 'Delivery Rate' },
  ];

  return (
    <div className="min-h-screen flex">

      {/* ── Left Branding Panel ── */}
      <div className="hidden lg:flex lg:w-[52%] relative bg-[#0d1f17] text-white overflow-hidden flex-col justify-between p-12">

        {/* Ambient orbs */}
        <div className="absolute top-0 left-1/3 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[100px] pointer-events-none" />
        <div className="absolute bottom-0 right-0 w-[400px] h-[400px] bg-teal-500/8 rounded-full blur-[80px] pointer-events-none" />
        <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent" />

        {/* Top logo */}
        <div className="relative z-10 flex items-center gap-3">
          <div className="bg-gradient-to-br from-emerald-500 to-teal-500 p-2.5 rounded-xl shadow-lg shadow-emerald-500/20">
            <ShoppingBag className="h-5 w-5 text-white" />
          </div>
          <div>
            <span className="block text-white font-bold text-base font-display leading-tight">StoreToDoor</span>
            <span className="block text-emerald-400 text-[10px] font-semibold tracking-widest uppercase">Balaji Trading Co.</span>
          </div>
        </div>

        {/* Main content */}
        <div className="relative z-10 space-y-8 max-w-md">
          <div>
            <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3.5 py-1.5 mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">Welcome back</span>
            </div>

            <h2 className="text-4xl font-bold font-display leading-tight mb-4">
              Your trusted<br />
              <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                grocery partner
              </span>
              <br />in Jaipur
            </h2>

            <p className="text-gray-400 text-lg leading-relaxed">
              Sign in to explore fresh products, track your orders, and enjoy exclusive member deals.
            </p>
          </div>

          {/* Perks list */}
          <ul className="space-y-3">
            {perks.map(({ icon, text }) => (
              <li key={text} className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 flex-shrink-0">
                  {icon}
                </div>
                <span className="text-gray-300 text-sm">{text}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Stats bar */}
        <div className="relative z-10 flex items-center gap-8 pt-8 border-t border-white/5">
          {stats.map(({ value, label }, i) => (
            <React.Fragment key={label}>
              {i > 0 && <div className="w-px h-10 bg-white/10" />}
              <div>
                <div className="text-2xl font-extrabold font-display bg-gradient-to-r from-emerald-300 to-teal-300 bg-clip-text text-transparent">
                  {value}
                </div>
                <div className="text-gray-500 text-xs font-medium mt-0.5">{label}</div>
              </div>
            </React.Fragment>
          ))}
        </div>
      </div>

      {/* ── Right Form Panel ── */}
      <div className="flex-1 flex items-center justify-center py-12 px-6 sm:px-10 bg-[#f8fafc]">
        <div className="w-full max-w-md">

          {/* Mobile logo */}
          <div className="lg:hidden flex items-center gap-3 mb-8">
            <div className="bg-gradient-to-br from-emerald-500 to-teal-500 p-2.5 rounded-xl shadow-md shadow-emerald-500/20">
              <ShoppingBag className="h-5 w-5 text-white" />
            </div>
            <span className="font-bold text-gray-900 font-display text-lg">StoreToDoor</span>
          </div>

          {/* Heading */}
          <div className="mb-8">
            <h1 className="text-3xl font-bold font-display text-gray-900 mb-1">Sign in</h1>
            <p className="text-gray-500 text-sm">
              New here?{' '}
              <Link to="/signup" className="text-emerald-600 hover:text-emerald-700 font-semibold transition-colors">
                Create an account
              </Link>
            </p>
          </div>

          {/* Card */}
          <div className="bg-white border border-gray-100 rounded-2xl p-8 shadow-sm">

            {/* Error banner */}
            {error && (
              <div className="flex items-start gap-2.5 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-5 text-sm">
                <div className="w-4 h-4 rounded-full bg-rose-200 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span className="text-rose-600 text-[10px] font-bold leading-none">!</span>
                </div>
                {error}
              </div>
            )}

            <form className="space-y-5" onSubmit={handleSubmit}>

              {/* Email */}
              <div>
                <label htmlFor="email" className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">
                  Email Address
                </label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                  <input
                    id="email" type="email" required autoComplete="email"
                    value={email} onChange={(e) => setEmail(e.target.value)}
                    className={`${inputBase} pl-10`}
                    placeholder="you@example.com"
                  />
                </div>
              </div>

              {/* Password */}
              <div>
                <label htmlFor="password" className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">
                  Password
                </label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                  <input
                    id="password" type={showPassword ? 'text' : 'password'} required autoComplete="current-password"
                    value={password} onChange={(e) => setPassword(e.target.value)}
                    className={`${inputBase} pl-10 pr-11`}
                    placeholder="Enter your password"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                  >
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>

              {/* Remember me + Forgot */}
              <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 cursor-pointer group">
                  <div className="relative">
                    <input
                      id="remember-me" type="checkbox"
                      className="peer sr-only"
                    />
                    <div className="w-4 h-4 border-2 border-gray-300 rounded peer-checked:border-emerald-500 peer-checked:bg-emerald-500 transition-all duration-200" />
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 peer-checked:opacity-100">
                      <svg className="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                  </div>
                  <span className="text-sm text-gray-600 select-none">Remember me</span>
                </label>
                <a href="#" className="text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                  Forgot password?
                </a>
              </div>

              {/* Submit */}
              <button
                type="submit"
                disabled={isLoading}
                className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0 mt-1"
              >
                {isLoading ? (
                  <div className="h-5 w-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                ) : (
                  <>
                    Sign In
                    <ArrowRight className="h-4 w-4" />
                  </>
                )}
              </button>
            </form>

            {/* Divider */}
            <div className="relative my-6">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-gray-100" />
              </div>
              <div className="relative flex justify-center">
                <span className="bg-white px-4 text-xs text-gray-400 font-medium">or</span>
              </div>
            </div>

            {/* Sign up nudge */}
            <p className="text-center text-sm text-gray-500">
              Don't have an account?{' '}
              <Link to="/signup" className="font-bold text-emerald-600 hover:text-emerald-700 transition-colors">
                Sign up for free →
              </Link>
            </p>
          </div>

          {/* Footer note */}
          <p className="text-center text-xs text-gray-400 mt-6">
            By signing in you agree to our{' '}
            <a href="#" className="hover:text-emerald-600 transition-colors underline">Terms</a>
            {' '}and{' '}
            <a href="#" className="hover:text-emerald-600 transition-colors underline">Privacy Policy</a>
          </p>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;