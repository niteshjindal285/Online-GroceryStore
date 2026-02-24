import React from 'react';
import { Link } from 'react-router-dom';
import { Home, ArrowLeft, Search, MapPin } from 'lucide-react';

const NotFoundPage: React.FC = () => {
  return (
    <div className="min-h-screen flex items-center justify-center relative overflow-hidden">
      {/* Decorative background */}
      <div className="absolute inset-0">
        <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-emerald-100/40 rounded-full blur-3xl animate-float"></div>
        <div className="absolute bottom-1/4 right-1/4 w-72 h-72 bg-teal-100/40 rounded-full blur-3xl animate-float-slow"></div>
      </div>

      <div className="relative max-w-md w-full text-center px-4 animate-fade-in-up">
        {/* 404 Illustration */}
        <div className="mb-8 relative">
          <div className="text-[120px] font-bold font-display bg-gradient-to-r from-emerald-200 via-teal-200 to-emerald-200 bg-clip-text text-transparent leading-none">
            404
          </div>
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 animate-bounce-gentle">
            <div className="bg-gradient-to-br from-emerald-500 to-teal-500 text-white p-5 rounded-2xl shadow-elevated">
              <MapPin className="h-8 w-8" />
            </div>
          </div>
        </div>

        {/* Error Message */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold font-display text-gray-900 mb-3">
            Oops! Page Not Found
          </h1>
          <p className="text-gray-500 text-lg leading-relaxed">
            The page you're looking for seems to have gone on a delivery run.
            Don't worry, we'll help you find what you need!
          </p>
        </div>

        {/* Action Buttons */}
        <div className="space-y-3">
          <Link to="/" className="btn-primary w-full flex items-center justify-center shimmer-btn">
            <Home className="h-5 w-5 mr-2" />
            Go to Homepage
          </Link>
          <Link to="/dashboard" className="btn-secondary w-full flex items-center justify-center">
            <Search className="h-5 w-5 mr-2" />
            Browse Products
          </Link>
          <button onClick={() => window.history.back()} className="w-full text-gray-500 hover:text-gray-700 px-6 py-3 rounded-xl font-medium transition-all duration-200 flex items-center justify-center hover:bg-gray-50">
            <ArrowLeft className="h-5 w-5 mr-2" />
            Go Back
          </button>
        </div>

        {/* Quick Links */}
        <div className="mt-12 pt-8 border-t border-gray-200">
          <p className="text-gray-400 text-sm mb-4">Looking for something specific?</p>
          <div className="flex flex-wrap justify-center gap-4">
            {[
              { to: '/about', label: 'About Us' },
              { to: '/contact', label: 'Contact' },
              { to: '/login', label: 'Login' },
              { to: '/signup', label: 'Sign Up' },
            ].map(link => (
              <Link key={link.to} to={link.to} className="text-emerald-600 hover:text-emerald-700 text-sm font-medium transition-colors duration-200">
                {link.label}
              </Link>
            ))}
          </div>
        </div>

        {/* Fun Message */}
        <div className="mt-8 glass-card-solid p-4">
          <p className="text-gray-600 text-sm">
            <strong className="text-emerald-600">Fun Fact:</strong> While you're here, did you know we deliver fresh groceries in under 30 minutes?
            Try our service today!
          </p>
        </div>
      </div>
    </div>
  );
};

export default NotFoundPage;