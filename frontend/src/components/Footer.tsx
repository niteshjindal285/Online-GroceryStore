import React from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Phone, Mail, Facebook, Twitter, Instagram, Linkedin, ShoppingBag, ArrowRight, Sparkles } from 'lucide-react';

const FooterHeading: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="mb-6">
    <h3 className="text-white text-sm font-bold uppercase tracking-widest mb-2">{children}</h3>
    <div className="h-0.5 w-8 bg-gradient-to-r from-emerald-400 to-teal-400 rounded-full" />
  </div>
);

const FooterLink: React.FC<{ to: string; children: React.ReactNode }> = ({ to, children }) => (
  <li>
    <Link
      to={to}
      className="group flex items-center gap-2 text-gray-400 hover:text-white text-sm transition-all duration-300"
    >
      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500/0 group-hover:bg-emerald-400 transition-all duration-300 flex-shrink-0" />
      {children}
    </Link>
  </li>
);

const Footer: React.FC = () => {
  return (
    <footer className="relative bg-[#0d1117] text-white overflow-hidden">

      {/* Top glowing accent line */}
      <div className="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-emerald-400 to-transparent opacity-80" />

      {/* Background orbs */}
      <div className="absolute top-0 left-1/4 w-96 h-96 bg-emerald-600/5 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-0 right-1/4 w-80 h-80 bg-teal-600/5 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute top-40 right-0 w-64 h-64 bg-indigo-600/5 rounded-full blur-3xl pointer-events-none" />

      {/* Newsletter Banner */}
      <div className="relative border-b border-white/5">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
          <div className="relative rounded-2xl bg-gradient-to-r from-emerald-900/50 via-teal-900/40 to-emerald-900/50 border border-emerald-700/20 px-8 py-8 overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
            {/* shimmer overlay */}
            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/[0.03] to-transparent" />
            <div className="flex items-center gap-4 text-center md:text-left">
              <div className="hidden md:flex w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-500/30 items-center justify-center flex-shrink-0">
                <Sparkles className="h-5 w-5 text-emerald-400" />
              </div>
              <div>
                <p className="text-white font-bold text-lg font-display">Get exclusive deals in your inbox</p>
                <p className="text-gray-400 text-sm mt-0.5">Fresh offers, new arrivals, and weekly discounts. No spam.</p>
              </div>
            </div>
            <div className="flex w-full md:w-auto gap-2 items-center">
              <input
                type="email"
                placeholder="Enter your email"
                className="flex-1 md:w-64 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-emerald-500/50 focus:bg-white/10 transition-all duration-300"
              />
              <button className="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-300 shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 flex items-center gap-1.5 whitespace-nowrap">
                Subscribe <ArrowRight className="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Footer Grid */}
      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">

          {/* Brand Column */}
          <div className="lg:col-span-1 space-y-6">
            <Link to="/" className="inline-flex items-center gap-3 group">
              <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-2.5 rounded-xl shadow-lg shadow-emerald-500/20 group-hover:shadow-emerald-500/40 group-hover:scale-105 transition-all duration-300">
                <ShoppingBag className="h-5 w-5 text-white" />
              </div>
              <div>
                <span className="block text-white font-bold text-base leading-tight font-display">Balaji Trading Company</span>
              </div>
            </Link>

            <p className="text-gray-400 text-sm leading-relaxed">
              Jaipur's most trusted grocery partner. Fresh staples, daily essentials delivered fast — straight from Balaji Trading Company to your doorstep.
            </p>

            {/* Social Icons */}
            <div className="flex gap-2.5">
              {[
                { Icon: Facebook, href: '#', label: 'Facebook' },
                { Icon: Twitter, href: '#', label: 'Twitter' },
                { Icon: Instagram, href: '#', label: 'Instagram' },
                { Icon: Linkedin, href: '#', label: 'LinkedIn' },
              ].map(({ Icon, href, label }) => (
                <a
                  key={label}
                  href={href}
                  aria-label={label}
                  className="w-9 h-9 rounded-lg bg-white/5 border border-white/8 hover:bg-emerald-500/15 hover:border-emerald-500/30 hover:text-emerald-400 text-gray-500 flex items-center justify-center transition-all duration-300 hover:scale-110 hover:-translate-y-0.5"
                >
                  <Icon className="h-4 w-4" />
                </a>
              ))}
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <FooterHeading>Quick Links</FooterHeading>
            <ul className="space-y-3">
              {[
                { label: 'Home', to: '/' },
                { label: 'Shop Products', to: '/dashboard' },
                { label: 'About Us', to: '/about' },
                { label: 'Contact Us', to: '/contact' },
              ].map((link) => (
                <FooterLink key={link.label} to={link.to}>{link.label}</FooterLink>
              ))}
            </ul>
          </div>

          {/* Categories */}
          <div>
            <FooterHeading>Categories</FooterHeading>
            <ul className="space-y-3">
              {[
                { label: 'Spices & Herbs', id: 'spices herbs' },
                { label: 'Cooking Oil', id: 'cooking oil' },
                { label: 'Dals & Pulses', id: 'dals pulses' },
                { label: 'Flours & Grains', id: 'flours grains' },
                { label: 'Rice Products', id: 'rice products' },
                { label: 'Beverages', id: 'beverages' },
                { label: 'Dry Fruits', id: 'dry fruits-nuts' },
              ].map((cat) => (
                <FooterLink key={cat.id} to={`/dashboard?category=${cat.id}`}>{cat.label}</FooterLink>
              ))}
            </ul>
          </div>

          {/* Customer Service */}
          <div>
            <FooterHeading>Support</FooterHeading>
            <ul className="space-y-3">
              {[
                { label: 'Help Center', to: '#' },
                { label: 'Track Your Order', to: '#' },
                { label: 'Returns & Refunds', to: '#' },
                { label: 'FAQs', to: '#' },
              ].map((item) => (
                <FooterLink key={item.label} to={item.to}>{item.label}</FooterLink>
              ))}
            </ul>
          </div>

          {/* Contact Info */}
          <div>
            <FooterHeading>Contact</FooterHeading>
            <div className="space-y-4">
              {[
                { Icon: Phone, text: '8107205038', href: 'tel:8107205038', sub: 'Call us anytime' },
                { Icon: Mail, text: 'jindalnitesh285@gmail.com', href: 'mailto:jindalnitesh285@gmail.com', sub: 'Drop an email' },
              ].map(({ Icon, text, href, sub }) => (
                <a
                  key={href}
                  href={href}
                  className="flex items-start gap-3 group"
                >
                  <div className="mt-0.5 w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-500/25 group-hover:border-emerald-500/40 transition-all duration-300">
                    <Icon className="h-4 w-4 text-emerald-400" />
                  </div>
                  <div>
                    <p className="text-[10px] text-gray-500 uppercase tracking-wider font-medium mb-0.5">{sub}</p>
                    <p className="text-gray-300 text-sm group-hover:text-emerald-400 transition-colors duration-300 break-all">{text}</p>
                  </div>
                </a>
              ))}

              <div className="flex items-start gap-3">
                <div className="mt-0.5 w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
                  <MapPin className="h-4 w-4 text-emerald-400" />
                </div>
                <div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wider font-medium mb-0.5">Our Store</p>
                  <p className="text-gray-300 text-sm leading-relaxed">Balaji Trading Company, Bank Colony, Jaipur, Rajasthan</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="relative border-t border-white/5">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-2 text-sm">
              <span className="text-gray-600">©</span>
              <span className="text-gray-600">{new Date().getFullYear()}</span>
              <span className="text-gray-500 font-semibold">Balaji Trading Company.</span>
              <span className="text-gray-600 hidden sm:inline">All rights reserved.</span>
            </div>
            <div className="flex items-center gap-1">
              {['Privacy Policy', 'Terms of Service', 'Cookie Policy'].map((text, i, arr) => (
                <React.Fragment key={text}>
                  <a href="#" className="text-gray-600 hover:text-emerald-400 text-xs transition-colors duration-300 px-2 py-1 rounded hover:bg-white/5">
                    {text}
                  </a>
                  {i < arr.length - 1 && <span className="text-gray-700 text-xs">·</span>}
                </React.Fragment>
              ))}
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;