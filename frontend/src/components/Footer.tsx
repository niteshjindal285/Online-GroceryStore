import React from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Phone, Mail, Facebook, Twitter, Instagram, Linkedin } from 'lucide-react';

const Footer: React.FC = () => {
  return (
    <footer className="relative bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 text-white overflow-hidden">
      {/* Decorative gradient line */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-600"></div>

      {/* Decorative background shapes */}
      <div className="absolute top-20 right-10 w-72 h-72 bg-emerald-500/5 rounded-full blur-3xl"></div>
      <div className="absolute bottom-10 left-10 w-56 h-56 bg-teal-500/5 rounded-full blur-3xl"></div>

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 py-16">
          {/* Company Info */}
          <div className="space-y-5">
            <Link to="/" className="flex items-center space-x-3 group">
              <div className="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-2.5 rounded-xl shadow-lg group-hover:shadow-glow transition-all duration-300">
                <MapPin className="h-5 w-5" />
              </div>
              <span className="text-xl font-bold font-display">Balaji Trading Company</span>
            </Link>
            <p className="text-gray-400 leading-relaxed text-sm">
              Your trusted partner for fresh groceries and food delivery.
              We bring quality products right to your doorstep.
            </p>
            <div className="flex space-x-3">
              {[
                { Icon: Facebook, href: '#' },
                { Icon: Twitter, href: '#' },
                { Icon: Instagram, href: '#' },
                { Icon: Linkedin, href: '#' },
              ].map(({ Icon, href }, i) => (
                <a
                  key={i}
                  href={href}
                  className="w-10 h-10 bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl flex items-center justify-center text-gray-400 hover:text-emerald-400 hover:bg-emerald-500/10 hover:border-emerald-500/30 hover:scale-110 transition-all duration-300"
                >
                  <Icon className="h-4 w-4" />
                </a>
              ))}
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-lg font-semibold font-display mb-5">Quick Links</h3>
            <ul className="space-y-3">
              {[
                { label: 'Home', to: '/' },
                { label: 'Products', to: '/dashboard' },
                { label: 'About Us', to: '/about' },
                { label: 'Contact', to: '/contact' },
              ].map((link) => (
                <li key={link.label}>
                  <Link
                    to={link.to}
                    className="text-gray-400 hover:text-emerald-400 transition-all duration-300 text-sm inline-flex items-center group"
                  >
                    <span className="w-0 group-hover:w-2 h-0.5 bg-emerald-400 mr-0 group-hover:mr-2 transition-all duration-300 rounded-full"></span>
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Customer Service */}
          <div>
            <h3 className="text-lg font-semibold font-display mb-5">Customer Service</h3>
            <ul className="space-y-3">
              {['Help Center', 'Track Your Order', 'Returns & Refunds', 'FAQs'].map((text) => (
                <li key={text}>
                  <a
                    href="#"
                    className="text-gray-400 hover:text-emerald-400 transition-all duration-300 text-sm inline-flex items-center group"
                  >
                    <span className="w-0 group-hover:w-2 h-0.5 bg-emerald-400 mr-0 group-hover:mr-2 transition-all duration-300 rounded-full"></span>
                    {text}
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact Info */}
          <div>
            <h3 className="text-lg font-semibold font-display mb-5">Contact Info</h3>
            <div className="space-y-4">
              {[
                { Icon: Phone, text: '81******38' },
                { Icon: Mail, text: 'jindalnitesh285@gmail.com' },
              ].map(({ Icon, text }, i) => (
                <div key={i} className="flex items-center space-x-3 group">
                  <div className="w-9 h-9 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center group-hover:bg-emerald-500/20 transition-all duration-300">
                    <Icon className="h-4 w-4 text-emerald-400" />
                  </div>
                  <span className="text-gray-400 text-sm">{text}</span>
                </div>
              ))}
              <div className="flex items-start space-x-3 group">
                <div className="w-9 h-9 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-500/20 transition-all duration-300 mt-0.5">
                  <MapPin className="h-4 w-4 text-emerald-400" />
                </div>
                <span className="text-gray-400 text-sm leading-relaxed">Balaji Trading Company, Bank Colony, Murlipura, Jaipur, Rajasthan 302039</span>
              </div>
            </div>
          </div>
        </div>

        <div className="border-t border-white/10 py-6">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <p className="text-gray-500 text-sm">
              © {new Date().getFullYear()} Balaji Trading Company. All rights reserved.
            </p>
            <div className="flex space-x-6">
              {['Privacy Policy', 'Terms of Service', 'Cookie Policy'].map((text) => (
                <a key={text} href="#" className="text-gray-500 hover:text-emerald-400 text-sm transition-colors duration-300">
                  {text}
                </a>
              ))}
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;