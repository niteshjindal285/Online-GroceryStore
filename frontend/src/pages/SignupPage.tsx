import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Mail, Lock, User, Phone, MapPin, Eye, EyeOff, ShoppingBag, ArrowRight, CheckCircle } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';

const inputBase =
  'w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 hover:border-gray-300 transition-all duration-200 placeholder-gray-400 disabled:opacity-50';

const SignupPage: React.FC = () => {
  const [formData, setFormData] = useState({
    name: '', email: '', phone: '', address: '',
    password: '', confirmPassword: '',
    role: 'customer' as 'customer' | 'vendor' | 'admin'
  });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { signup } = useAuth();
  const navigate = useNavigate();

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (formData.password !== formData.confirmPassword) { setError('Passwords do not match'); return; }
    if (formData.password.length < 6) { setError('Password must be at least 6 characters long'); return; }
    setIsLoading(true);
    try {
      const success = await signup(formData);
      if (success) navigate('/home');
      else setError('Failed to create account. Please try again.');
    } catch { setError('An error occurred. Please try again.'); }
    finally { setIsLoading(false); }
  };

  const getPasswordStrength = () => {
    const p = formData.password;
    if (!p) return 0;
    let s = 0;
    if (p.length >= 6) s++;
    if (p.length >= 8) s++;
    if (/[A-Z]/.test(p)) s++;
    if (/[0-9]/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
  };
  const strength = getPasswordStrength();
  const strengthLabel = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'][strength];
  const strengthColors = ['bg-gray-200', 'bg-rose-500', 'bg-orange-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500'];

  const perks = [
    'Free delivery on orders above ₹500',
    '100% quality guarantee on all products',
    '30-minute express delivery option',
    'Exclusive member-only deals & offers',
  ];

  const fields = [
    { id: 'name', label: 'Full Name', icon: <User className="h-4 w-4 text-gray-400" />, type: 'text', placeholder: 'Your full name', required: true },
    { id: 'email', label: 'Email', icon: <Mail className="h-4 w-4 text-gray-400" />, type: 'email', placeholder: 'you@example.com', required: true },
    { id: 'phone', label: 'Phone', icon: <Phone className="h-4 w-4 text-gray-400" />, type: 'tel', placeholder: '10-digit mobile no.', required: false },
    { id: 'address', label: 'Address', icon: <MapPin className="h-4 w-4 text-gray-400" />, type: 'text', placeholder: 'Your delivery address', required: false },
  ];

  return (
    <div className="min-h-screen flex">

      {/* ── Left Branding Panel ── */}
      <div className="hidden lg:flex lg:w-[48%] relative bg-[#0d1f17] text-white overflow-hidden flex-col justify-between p-12">
        <div className="absolute top-0 left-1/3 w-[400px] h-[400px] bg-emerald-500/10 rounded-full blur-[100px] pointer-events-none" />
        <div className="absolute bottom-0 right-0 w-[350px] h-[350px] bg-teal-500/8 rounded-full blur-[80px] pointer-events-none" />
        <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent" />

        {/* Logo */}
        <div className="relative z-10 flex items-center gap-3">
          <div className="bg-gradient-to-br from-emerald-500 to-teal-500 p-2.5 rounded-xl shadow-lg shadow-emerald-500/20">
            <ShoppingBag className="h-5 w-5 text-white" />
          </div>
          <div>
            <span className="block text-white font-bold text-base font-display leading-tight">StoreToDoor</span>
            <span className="block text-emerald-400 text-[10px] font-semibold tracking-widest uppercase">Balaji Trading Co.</span>
          </div>
        </div>

        {/* Content */}
        <div className="relative z-10 space-y-7 max-w-md">
          <div>
            <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3.5 py-1.5 mb-5">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">Join our community</span>
            </div>
            <h2 className="text-4xl font-bold font-display leading-tight mb-4">
              Join{' '}
              <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                happy customers
              </span>
              <br />across Jaipur
            </h2>
            <p className="text-gray-400 text-lg leading-relaxed">
              Create your account to start shopping fresh groceries with fast delivery and amazing deals.
            </p>
          </div>
          <ul className="space-y-3">
            {perks.map((p) => (
              <li key={p} className="flex items-center gap-3">
                <div className="w-5 h-5 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
                  <CheckCircle className="h-3 w-3 text-emerald-400" />
                </div>
                <span className="text-gray-300 text-sm">{p}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Bottom note */}
        <div className="relative z-10 text-gray-600 text-xs">
          Already have an account?{' '}
          <Link to="/login" className="text-emerald-400 hover:text-emerald-300 font-semibold transition-colors">Sign in →</Link>
        </div>
      </div>

      {/* ── Right Form Panel ── */}
      <div className="flex-1 flex items-center justify-center py-10 px-6 sm:px-10 bg-[#f8fafc] overflow-y-auto">
        <div className="w-full max-w-md">

          {/* Mobile logo */}
          <div className="lg:hidden flex items-center gap-3 mb-8">
            <div className="bg-gradient-to-br from-emerald-500 to-teal-500 p-2.5 rounded-xl shadow-md shadow-emerald-500/20">
              <ShoppingBag className="h-5 w-5 text-white" />
            </div>
            <span className="font-bold text-gray-900 font-display text-lg">StoreToDoor</span>
          </div>

          <div className="mb-6">
            <h1 className="text-3xl font-bold font-display text-gray-900 mb-1">Create Account</h1>
            <p className="text-gray-500 text-sm">
              Already have an account?{' '}
              <Link to="/login" className="text-emerald-600 hover:text-emerald-700 font-semibold transition-colors">Sign in</Link>
            </p>
          </div>

          <div className="bg-white border border-gray-100 rounded-2xl p-8 shadow-sm">
            {error && (
              <div className="flex items-start gap-2.5 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-5 text-sm">
                <div className="w-4 h-4 rounded-full bg-rose-200 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span className="text-rose-600 text-[10px] font-bold leading-none">!</span>
                </div>
                {error}
              </div>
            )}

            <form className="space-y-4" onSubmit={handleSubmit}>
              {fields.map(({ id, label, icon, type, placeholder, required }) => (
                <div key={id}>
                  <label htmlFor={id} className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">{label}{required && ' *'}</label>
                  <div className="relative">
                    <div className="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">{icon}</div>
                    <input id={id} name={id} type={type} required={required}
                      value={(formData as any)[id]} onChange={handleChange}
                      className={`${inputBase} pl-10`} placeholder={placeholder} />
                  </div>
                </div>
              ))}

              {/* Password */}
              <div>
                <label htmlFor="password" className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Password *</label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                  <input id="password" name="password" type={showPassword ? 'text' : 'password'} required
                    value={formData.password} onChange={handleChange}
                    className={`${inputBase} pl-10 pr-11`} placeholder="Create a strong password" />
                  <button type="button" onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {formData.password && (
                  <div className="mt-2 space-y-1">
                    <div className="flex gap-1">
                      {[1, 2, 3, 4, 5].map(i => (
                        <div key={i} className={`h-1 flex-1 rounded-full transition-all duration-300 ${i <= strength ? strengthColors[strength] : 'bg-gray-200'}`} />
                      ))}
                    </div>
                    <p className={`text-[11px] font-semibold ${strength <= 1 ? 'text-rose-500' : strength <= 3 ? 'text-amber-500' : 'text-emerald-600'}`}>{strengthLabel}</p>
                  </div>
                )}
              </div>

              {/* Confirm Password */}
              <div>
                <label htmlFor="confirmPassword" className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Confirm Password *</label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                  <input id="confirmPassword" name="confirmPassword" type={showConfirmPassword ? 'text' : 'password'} required
                    value={formData.confirmPassword} onChange={handleChange}
                    className={`${inputBase} pl-10 pr-11`} placeholder="Repeat your password" />
                  <button type="button" onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                    {showConfirmPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {formData.confirmPassword && formData.password !== formData.confirmPassword && (
                  <p className="text-[11px] text-rose-500 font-semibold mt-1">Passwords don't match</p>
                )}
              </div>

              <button type="submit" disabled={isLoading}
                className="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0 mt-2">
                {isLoading ? (
                  <div className="h-5 w-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                ) : (
                  <><ShoppingBag className="h-4 w-4" /> Create Account <ArrowRight className="h-4 w-4" /></>
                )}
              </button>
            </form>

            <p className="text-center text-xs text-gray-400 mt-5">
              By signing up you agree to our{' '}
              <a href="#" className="underline hover:text-emerald-600 transition-colors">Terms</a>
              {' '}and{' '}
              <a href="#" className="underline hover:text-emerald-600 transition-colors">Privacy Policy</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SignupPage;