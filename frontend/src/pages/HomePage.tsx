import React, { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import {
  ArrowRight, Clock, Truck, Shield, MapPin, Phone, Mail,
  Users, Award, ShoppingBag, Star, CheckCircle, Sparkles
} from "lucide-react";
import { useAuth } from "../contexts/AuthContext";
import { getProducts } from "../utils/productUtils";
import { Product } from "../data/mockProducts";
import { ProductCard } from "../components/ProductCard";
import { ProductGridSkeleton } from "../components/ProductSkeleton";

const SectionLabel: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="inline-flex items-center gap-2 bg-emerald-100 text-emerald-700 text-xs font-bold px-3.5 py-1.5 rounded-full uppercase tracking-widest mb-4">
    <Sparkles className="h-3 w-3" />
    {children}
  </div>
);

const HomePage: React.FC = () => {
  const { user } = useAuth();

  const categories = [
    { id: "spices herbs", name: "Spices & Herbs", image: "https://as2.ftcdn.net/v2/jpg/02/89/44/67/1000_F_289446727_vRX6ctHc8dkaeT0zcKnHSu2w5TWOVtJH.jpg", count: "120+ Items" },
    { id: "cooking oil", name: "Cooking Oil", image: "https://images.dealshare.in/1757134648956CookingOil.png?tr=f-webp", count: "95+ Items" },
    { id: "sugar-salt-jaggery", name: "Sugar, Salt & Jaggery", image: "https://images.dealshare.in/1757134885382Sugar,Salt&Jaggery.png?tr=f-webp", count: "80+ Items" },
    { id: "flours grains", name: "Flours & Grains", image: "https://images.dealshare.in/1757134734804Flours&Grains.png?tr=f-webp", count: "65+ Items" },
    { id: "rice products", name: "Rice Products", image: "https://images.dealshare.in/1757134867440Rice&RiceProducts.png?tr=f-webp", count: "45+ Items" },
    { id: "dals pulses", name: "Dals & Pulses", image: "https://images.dealshare.in/1757134671179Dals&Pulses.png?tr=f-webp", count: "85+ Items" },
    { id: "ghee vanaspati", name: "Ghee & Vanaspati", image: "https://images.dealshare.in/1757072788411Ghee&Vanaspati.png?tr=f-webp", count: "85+ Items" },
    { id: "dry fruits-nuts", name: "Dry Fruits & Nuts", image: "https://images.dealshare.in/1757134697555DryFruits.png?tr=f-webp", count: "85+ Items" },
    { id: "beverages", name: "Beverages", image: "https://media.dealshare.in/img/offer/1751011291018:8FA29DE506_1.png?tr=f-webp", count: "50+ Items" },
    { id: "cleaning home-care", name: "Cleaning & Home Care", image: "https://images.dealshare.in/1753788261043Bath&Cleaning_NCR_JAI_KOL_LUC.png?tr=f-webp", count: "150+ Items" },
    { id: "personal care", name: "Personal Care", image: "https://images.dealshare.in/1740735585278luc_kolHPCOralCare.jpg?tr=f-webp", count: "60+ Items" },
  ];

  const [featuredProducts, setFeaturedProducts] = useState<Product[]>([]);
  const [isLoadingFeatured, setIsLoadingFeatured] = useState<boolean>(false);
  const [featuredError, setFeaturedError] = useState<string | null>(null);

  useEffect(() => {
    const fetchProducts = async () => {
      try {
        setIsLoadingFeatured(true);
        setFeaturedError(null);
        const data = await getProducts();
        const available = data.filter((p) => p.inStock).slice(0, 8);
        setFeaturedProducts(available);
        if (available.length === 0)
          setFeaturedError("No featured products available at the moment. Please check back soon.");
      } catch {
        setFeaturedError("We couldn't load featured products. Please try again in a moment.");
      } finally {
        setIsLoadingFeatured(false);
      }
    };
    fetchProducts();
  }, []);

  const trustFeatures = [
    { icon: <Clock className="h-5 w-5" />, title: "30-Min Delivery", desc: "Groceries at your door in record time", color: "text-emerald-600", bg: "bg-emerald-50" },
    { icon: <Shield className="h-5 w-5" />, title: "Quality Guaranteed", desc: "100% fresh products, every single time", color: "text-blue-600", bg: "bg-blue-50" },
    { icon: <Truck className="h-5 w-5" />, title: "Free Delivery", desc: "On all orders above ₹500", color: "text-amber-600", bg: "bg-amber-50" },
    { icon: <Star className="h-5 w-5" />, title: "Best Prices", desc: "Lowest prices on all essentials", color: "text-purple-600", bg: "bg-purple-50" },
  ];

  const team = [
    { name: "Inder Kumar Gupta", role: "CEO & Founder", image: "https://image2url.com/r2/default/images/1771738555505-aa327b54-403f-4a70-9000-4ee992f43173.jpeg", bio: "Founded StoreToDoor with a vision to revolutionize grocery delivery.", accent: "from-emerald-400 to-teal-400" },
    { name: "Piyush Kumar Jindal", role: "CEO & Founder", image: "https://image2url.com/r2/default/images/1771738386962-b652116c-7ff7-4202-b219-5aa86e78d2ed.jpg", bio: "Drives the strategic vision and growth of the company.", accent: "from-blue-400 to-indigo-400" },
    { name: "Manish Jindal", role: "CEO & Founder", image: "https://image2url.com/r2/default/images/1771738294524-22c328d0-497b-490c-9b84-ce79fa42c372.jpg", bio: "Manages day-to-day operations and smooth delivery across all zones.", accent: "from-purple-400 to-violet-400" },
  ];

  const values = [
    { icon: <Users className="h-6 w-6" />, title: "Customer First", desc: "Every decision centers around providing the best possible experience.", gradient: "from-blue-500 to-indigo-500", border: "hover:border-blue-200" },
    { icon: <Award className="h-6 w-6" />, title: "Quality Excellence", desc: "We maintain the highest standards in product quality and service.", gradient: "from-emerald-500 to-teal-500", border: "hover:border-emerald-200" },
    { icon: <MapPin className="h-6 w-6" />, title: "Community Impact", desc: "Committed to supporting local businesses and making a positive impact.", gradient: "from-purple-500 to-violet-500", border: "hover:border-purple-200" },
  ];

  const stats = [
    { value: "50K+", label: "Happy Customers" },
    { value: "500+", label: "Products" },
    { value: "99.5%", label: "Delivery Rate" },
    { value: "3+", label: "Years of Trust" },
  ];

  return (
    <div className="min-h-screen bg-white">

      {/* ══════════════════════════════════════
          HERO
      ══════════════════════════════════════ */}
      <section className="relative bg-[#0d1f17] overflow-hidden">
        {/* Ambient orbs */}
        <div className="absolute top-0 left-1/4 w-[600px] h-[600px] bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none" />
        <div className="absolute bottom-0 right-1/4 w-[500px] h-[500px] bg-teal-500/8  rounded-full blur-[100px] pointer-events-none" />
        <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center min-h-[88vh] py-16 lg:py-24">

            {/* Left: Copy */}
            <div className="text-white space-y-7 z-10">
              <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-4 py-1.5">
                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse flex-shrink-0" />
                <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">Jaipur's Trusted Grocery Partner</span>
              </div>

              <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold font-display leading-tight">
                Get Fresh Groceries<br />
                <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                  Delivered Fast
                </span>
              </h1>

              <p className="text-gray-400 text-lg leading-relaxed max-w-lg">
                Fresh staples, daily essentials, and premium products from{" "}
                <span className="text-white font-semibold">Balaji Trading Company</span>{" "}
                — delivered straight to your doorstep.
              </p>

              <div className="flex flex-col sm:flex-row gap-3 sm:items-center pt-2">
                <Link
                  to="/dashboard"
                  className="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-8 py-4 rounded-xl transition-all duration-300 shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 text-base"
                >
                  <ShoppingBag className="h-5 w-5" />
                  {!user ? "Start Shopping" : "Order Now"}
                  <ArrowRight className="h-4 w-4" />
                </Link>
                <div className="flex items-center gap-2 text-sm text-gray-400">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 flex-shrink-0" />
                  Free delivery above ₹500 · Cash on Delivery
                </div>
              </div>

              {/* Mini stats */}
              <div className="flex items-center gap-6 pt-4 border-t border-white/5">
                {stats.map(({ value, label }, i) => (
                  <React.Fragment key={label}>
                    {i > 0 && <div className="w-px h-8 bg-white/10" />}
                    <div>
                      <div className="text-xl font-extrabold font-display bg-gradient-to-r from-emerald-300 to-teal-300 bg-clip-text text-transparent">{value}</div>
                      <div className="text-gray-500 text-[11px] font-medium">{label}</div>
                    </div>
                  </React.Fragment>
                ))}
              </div>
            </div>

            {/* Right: Hero image */}
            <div className="relative hidden lg:block">
              <div className="absolute -inset-4 bg-gradient-to-br from-emerald-500/20 to-teal-500/10 rounded-3xl blur-3xl" />
              <div className="relative rounded-3xl overflow-hidden shadow-2xl">
                <img
                  src="https://as1.ftcdn.net/v2/jpg/16/29/70/80/1000_F_1629708072_5C2KrBU5mrGky8kKFB3Ro96MN06sNQmy.jpg"
                  alt="Fresh Groceries"
                  className="w-full h-[500px] object-cover"
                />
                {/* Floating badge */}
                <div className="absolute top-5 left-5 bg-white/95 backdrop-blur-sm rounded-2xl px-4 py-3 shadow-lg flex items-center gap-3">
                  <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center flex-shrink-0">
                    <CheckCircle className="h-5 w-5 text-white" />
                  </div>
                  <div>
                    <p className="text-gray-900 font-bold text-xs">Quality Guaranteed</p>
                    <p className="text-gray-400 text-[10px]">Fresh from Balaji Trading Co.</p>
                  </div>
                </div>
                {/* Bottom badge */}
                <div className="absolute bottom-5 right-5 bg-white/95 backdrop-blur-sm rounded-2xl px-4 py-3 shadow-lg flex items-center gap-2">
                  <Clock className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                  <span className="text-gray-900 font-bold text-xs">30-Min Delivery</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Wave */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 60L1440 60L1440 0C1200 40 960 60 720 40C480 20 240 0 0 30L0 60Z" fill="white" />
          </svg>
        </div>
      </section>

      {/* ══════════════════════════════════════
          TRUST BAR
      ══════════════════════════════════════ */}
      <section className="py-8 bg-white border-b border-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {trustFeatures.map((f, i) => (
              <div key={i} className={`flex items-center gap-3 p-4 rounded-2xl border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all duration-300`}>
                <div className={`${f.bg} ${f.color} w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0`}>
                  {f.icon}
                </div>
                <div>
                  <p className="font-bold text-gray-900 text-sm leading-tight">{f.title}</p>
                  <p className="text-gray-400 text-[11px] mt-0.5 leading-tight">{f.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          CATEGORIES
      ══════════════════════════════════════ */}
      <section className="py-14 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-end justify-between mb-8">
            <div>
              <SectionLabel>Categories</SectionLabel>
              <h2 className="text-2xl md:text-3xl font-bold font-display text-gray-900">
                Shop by Category
              </h2>
              <p className="text-gray-500 text-sm mt-1">Browse our most popular grocery sections</p>
            </div>
            <Link to="/dashboard" className="hidden sm:inline-flex items-center gap-1.5 text-emerald-600 hover:text-emerald-700 font-semibold text-sm transition-colors group">
              View All <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
            </Link>
          </div>

          <div className="flex overflow-x-auto hide-scrollbar gap-4 md:gap-6 pb-3 -mx-4 px-4 sm:mx-0 sm:px-0">
            {categories.map((cat, i) => (
              <Link
                to={`/dashboard?category=${cat.id}`}
                key={i}
                className="group flex flex-col items-center min-w-[80px] sm:min-w-[96px] lg:min-w-[106px] gap-2.5 shrink-0"
              >
                <div className="relative w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24">
                  <div className="absolute inset-0 bg-gradient-to-br from-emerald-400/0 to-teal-400/0 group-hover:from-emerald-400/20 group-hover:to-teal-400/20 rounded-full transition-all duration-500 blur-md" />
                  <div className="relative w-full h-full rounded-full overflow-hidden bg-gray-50 p-1 ring-2 ring-gray-100 group-hover:ring-emerald-400 transition-all duration-300 shadow-sm group-hover:shadow-md">
                    <img
                      src={cat.image}
                      alt={cat.name}
                      className="w-full h-full object-cover rounded-full group-hover:scale-110 transition-transform duration-500"
                    />
                  </div>
                </div>
                <div className="text-center">
                  <span className="text-[11px] sm:text-xs font-bold text-gray-700 group-hover:text-emerald-600 transition-colors line-clamp-2 leading-snug block">
                    {cat.name}
                  </span>
                  {cat.count && (
                    <span className="text-[9px] text-gray-400 font-medium">{cat.count}</span>
                  )}
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          FEATURED PRODUCTS
      ══════════════════════════════════════ */}
      <section className="py-14 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-end justify-between mb-8">
            <div>
              <SectionLabel>Hand-picked</SectionLabel>
              <h2 className="text-2xl md:text-3xl font-bold font-display text-gray-900">Featured Products</h2>
              <p className="text-gray-500 text-sm mt-1">Top picks that our customers love</p>
            </div>
            <Link to="/dashboard" className="hidden sm:inline-flex items-center gap-1.5 text-emerald-600 hover:text-emerald-700 font-semibold text-sm transition-colors group">
              View All <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
            </Link>
          </div>

          {isLoadingFeatured ? (
            <ProductGridSkeleton count={8} />
          ) : featuredError && featuredProducts.length === 0 ? (
            <div className="text-center py-16 bg-white border border-red-100 rounded-2xl">
              <div className="w-14 h-14 mx-auto mb-4 rounded-2xl bg-red-50 flex items-center justify-center">
                <Shield className="h-7 w-7 text-red-400" />
              </div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Featured products unavailable</h3>
              <p className="text-sm text-gray-500 max-w-md mx-auto">{featuredError}</p>
            </div>
          ) : (
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-5">
              {featuredProducts.map((product: Product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}

          <div className="text-center mt-8 sm:hidden">
            <Link to="/dashboard" className="inline-flex items-center gap-2 text-emerald-600 font-semibold text-sm">
              View All Products <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          ABOUT SNIPPET
      ══════════════════════════════════════ */}
      <section className="py-16 lg:py-24 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">
            <div>
              <SectionLabel>Who We Are</SectionLabel>
              <h2 className="text-3xl md:text-4xl font-bold font-display text-gray-900 mb-5 leading-tight">
                About{" "}
                <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">
                  Balaji Trading Company
                </span>
              </h2>
              <p className="text-gray-500 text-lg mb-6 leading-relaxed">
                We're revolutionizing grocery delivery by bringing fresh, quality products directly to your doorstep.
                Our mission is to make grocery shopping convenient, reliable, and enjoyable.
              </p>
              <ul className="space-y-3 mb-8">
                {["100% Quality Guarantee", "Fast & Reliable Delivery", "Local Jaipur Focus", "Cash on Delivery Available"].map((item) => (
                  <li key={item} className="flex items-center gap-3">
                    <div className="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                      <CheckCircle className="h-3.5 w-3.5 text-emerald-600" />
                    </div>
                    <span className="text-gray-700 font-medium text-sm">{item}</span>
                  </li>
                ))}
              </ul>
              <Link to="/about" className="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-700 font-bold transition-colors group">
                Learn more about us <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
              </Link>
            </div>
            <div className="relative">
              <div className="absolute -inset-4 bg-gradient-to-br from-emerald-400/15 to-teal-400/15 rounded-3xl blur-2xl" />
              <div className="relative rounded-3xl overflow-hidden shadow-xl">
                <img
                  src="https://images.pexels.com/photos/4393021/pexels-photo-4393021.jpeg?auto=compress&cs=tinysrgb&w=600"
                  alt="Grocery store"
                  className="w-full h-[420px] object-cover"
                />
                <div className="absolute bottom-5 left-5 right-5 bg-white/95 backdrop-blur-sm rounded-2xl p-4 flex items-center gap-3 shadow-lg">
                  <div className="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center flex-shrink-0">
                    <Star className="h-5 w-5 text-white fill-white" />
                  </div>
                  <div>
                    <p className="text-gray-900 font-bold text-sm">Trusted since 2020</p>
                    <p className="text-gray-500 text-xs">Serving Jaipur with fresh groceries daily</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          TEAM SNIPPET
      ══════════════════════════════════════ */}
      <section className="py-16 lg:py-20 bg-gray-50 border-t border-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <SectionLabel>The People</SectionLabel>
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-3">
              Meet Our <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Team</span>
            </h2>
            <p className="text-gray-500 text-lg">Our dedicated team works tirelessly for the best experience</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 max-w-4xl mx-auto">
            {team.map((member, index) => (
              <div key={index} className="group bg-white border border-gray-100 rounded-2xl p-7 text-center hover:shadow-lg hover:-translate-y-1.5 transition-all duration-500">
                <div className="relative w-24 h-24 mx-auto mb-5">
                  <div className={`absolute -inset-1 bg-gradient-to-br ${member.accent} rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 blur-sm`} />
                  <img src={member.image} alt={member.name}
                    className="relative w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-md" />
                </div>
                <h3 className="text-base font-bold font-display text-gray-900 mb-1">{member.name}</h3>
                <div className={`inline-block bg-gradient-to-r ${member.accent} text-white text-xs font-semibold px-3 py-1 rounded-full mb-3`}>{member.role}</div>
                <p className="text-gray-500 text-sm leading-relaxed">{member.bio}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          VALUES SNIPPET
      ══════════════════════════════════════ */}
      <section className="py-16 lg:py-20 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <SectionLabel>What We Stand For</SectionLabel>
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-3">
              Our <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Values</span>
            </h2>
            <p className="text-gray-500 text-lg">Core beliefs that guide everything we do</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            {values.map((val, i) => (
              <div key={i} className={`bg-white border border-gray-100 ${val.border} rounded-2xl p-8 hover:shadow-lg hover:-translate-y-1 transition-all duration-500`}>
                <div className={`bg-gradient-to-br ${val.gradient} text-white w-12 h-12 rounded-2xl flex items-center justify-center mb-5 shadow-md`}>
                  {val.icon}
                </div>
                <h3 className="text-xl font-bold font-display text-gray-900 mb-3">{val.title}</h3>
                <p className="text-gray-500 leading-relaxed text-sm">{val.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          CONTACT SNIPPET
      ══════════════════════════════════════ */}
      <section className="py-16 bg-gray-50 border-t border-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <SectionLabel>Reach Us</SectionLabel>
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-3">Get in Touch</h2>
            <p className="text-gray-500 text-lg">We're here to help with any questions or concerns</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
            {[
              { Icon: Phone, title: "Phone", value: "8107205038", href: "tel:8107205038", color: "from-blue-500 to-indigo-500" },
              { Icon: Mail, title: "Email", value: "jindalnitesh285@gmail.com", href: "mailto:jindalnitesh285@gmail.com", color: "from-emerald-500 to-teal-500" },
              { Icon: MapPin, title: "Location", value: "Bank Colony, Jaipur, Rajasthan", href: "https://maps.google.com", color: "from-purple-500 to-violet-500" },
            ].map(({ Icon, title, value, href, color }) => (
              <a key={title} href={href} target="_blank" rel="noreferrer"
                className="group bg-white border border-gray-100 hover:border-gray-200 rounded-2xl p-6 text-center hover:shadow-md hover:-translate-y-1 transition-all duration-300">
                <div className={`bg-gradient-to-br ${color} text-white w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md group-hover:scale-110 transition-transform duration-300`}>
                  <Icon className="h-5 w-5" />
                </div>
                <h3 className="font-bold text-gray-900 mb-1.5">{title}</h3>
                <p className="text-gray-500 text-sm group-hover:text-gray-700 transition-colors break-all">{value}</p>
              </a>
            ))}
          </div>

          <div className="text-center mt-8">
            <Link to="/contact" className="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-700 font-bold transition-colors group">
              View full contact details <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
            </Link>
          </div>
        </div>
      </section>

      {/* ══════════════════════════════════════
          CTA
      ══════════════════════════════════════ */}
      <section className="relative py-20 lg:py-28 bg-[#0d1f17] overflow-hidden">
        <div className="absolute inset-0 pointer-events-none">
          <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[120px]" />
          <div className="absolute bottom-0 left-0 w-96 h-96  bg-teal-500/10  rounded-full blur-[100px]" />
          <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent" />
        </div>

        <div className="relative max-w-3xl mx-auto px-4 text-center">
          <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-4 py-1.5 mb-8">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
            <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">
              {!user ? "Join Our Family" : "Welcome Back!"}
            </span>
          </div>

          <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold font-display text-white mb-5 leading-tight">
            Fresh groceries at<br />
            <span className="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">
              your fingertips
            </span>
          </h2>

          <p className="text-gray-400 text-lg mb-10 leading-relaxed">
            {!user
              ? "Create an account to track orders, save multiple addresses, and checkout faster."
              : "Explore our wide range of fresh products and get them delivered to your door."}
          </p>

          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            {!user ? (
              <Link
                to="/signup"
                className="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-9 py-4 rounded-xl transition-all duration-300 shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5"
              >
                Sign Up Now <ArrowRight className="h-5 w-5" />
              </Link>
            ) : (
              <Link
                to="/dashboard"
                className="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-9 py-4 rounded-xl transition-all duration-300 shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5"
              >
                <ShoppingBag className="h-5 w-5" />
                Continue Shopping <ArrowRight className="h-4 w-4" />
              </Link>
            )}
            <Link
              to="/about"
              className="inline-flex items-center justify-center gap-2 bg-white/5 border border-white/15 hover:bg-white/10 hover:border-white/25 text-white font-semibold px-9 py-4 rounded-xl transition-all duration-300"
            >
              Learn More
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
};

export default HomePage;
