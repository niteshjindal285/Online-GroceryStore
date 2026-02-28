import React, { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { ArrowRight, Clock, Truck, Shield, MapPin, Phone, Mail, Users, Award } from "lucide-react";
import { useAuth } from "../contexts/AuthContext";
import { useCart } from "../contexts/CartContext";
import { getProducts } from '../utils/productUtils';
import { Product } from '../data/mockProducts';

const HomePage: React.FC = () => {
  const { user } = useAuth();
  const { addToCart } = useCart();

  const categories = [
    {
      id: "spices herbs",
      name: "spices herbs",
      image: "https://as2.ftcdn.net/v2/jpg/02/89/44/67/1000_F_289446727_vRX6ctHc8dkaeT0zcKnHSu2w5TWOVtJH.jpg",
      count: "120+ Items",
    },
    {
      id: "cooking oil",
      name: "cooking oil",
      image: "https://images.dealshare.in/1757134648956CookingOil.png?tr=f-webp",
      count: "95+ Items",
    },
    {
      id: "sugar-salt-jaggery",
      name: "Sugar, Salt & Jaggery",
      image: "https://images.dealshare.in/1757134885382Sugar,Salt&Jaggery.png?tr=f-webp",
      count: "80+ Items",
    },
    {
      id: "flours grains",
      name: "flours grains",
      image: "https://images.dealshare.in/1757134734804Flours&Grains.png?tr=f-webp",
      count: "65+ Items",
    },
    {
      id: "rice products",
      name: "rice products",
      image: "https://images.dealshare.in/1757134867440Rice&RiceProducts.png?tr=f-webp",
      count: "45+ Items",
    },
    {
      id: "dals pulses",
      name: "dals pulses",
      image: "https://images.dealshare.in/1757134671179Dals&Pulses.png?tr=f-webp",
      count: "85+ Items",
    },
    {
      id: "ghee vanaspati",
      name: "ghee vanaspati",
      image: "https://images.dealshare.in/1757072788411Ghee&Vanaspati.png?tr=f-webp",
      count: "85+ Items",
    },
    {
      id: "dry fruits-nuts",
      name: "dry fruits-nuts",
      image: "https://images.dealshare.in/1757134697555DryFruits.png?tr=f-webp",
      count: "85+ Items",
    },
    {
      id: "beverages",
      name: "beverages",
      image: "https://media.dealshare.in/img/offer/1751011291018:8FA29DE506_1.png?tr=f-webp",
      count: "50+ Items",
    },
    {
      id: "cleaning home-care",
      name: "cleaning home-care",
      image: "https://images.dealshare.in/1753788261043Bath&Cleaning_NCR_JAI_KOL_LUC.png?tr=f-webp",
      count: "150+ Items",
    },
    {
      id: "personal care",
      name: "personal care",
      image: "https://images.dealshare.in/1740735585278luc_kolHPCOralCare.jpg?tr=f-webp",
    },
  ];

  const [featuredProducts, setFeaturedProducts] = useState<Product[]>([]);

  useEffect(() => {
    const fetchProducts = async () => {
      const data = await getProducts();
      const availableProducts = data.filter(product => product.inStock);
      setFeaturedProducts(availableProducts.slice(0, 8));
    };
    fetchProducts();
  }, []);

  const features = [
    {
      icon: <Clock className="h-7 w-7" />,
      title: "Fast Delivery",
      description: "Get your groceries delivered within 30 minutes",
      color: "from-emerald-500 to-teal-500",
    },
    {
      icon: <Shield className="h-7 w-7" />,
      title: "Quality Guaranteed",
      description: "Fresh products with 100% quality assurance",
      color: "from-blue-500 to-indigo-500",
    },
    {
      icon: <Truck className="h-7 w-7" />,
      title: "Free Delivery",
      description: "Free delivery on orders above ₹500",
      color: "from-amber-500 to-orange-500",
    },
  ];

  const team = [
    { name: 'Inder Kumar Gupta', role: 'CEO & Founder', image: 'https://image2url.com/r2/default/images/1771738555505-aa327b54-403f-4a70-9000-4ee992f43173.jpeg', bio: 'Founded StoreToDoor with a vision to revolutionize grocery delivery.' },
    { name: 'Piyush Kumar Jindal', role: 'CEO & Founder', image: 'https://image2url.com/r2/default/images/1771738386962-b652116c-7ff7-4202-b219-5aa86e78d2ed.jpg', bio: 'Drives the strategic vision and growth of the company to revolutionize grocery delivery.' },
    { name: 'Manish Jindal', role: 'CEO & Founder', image: 'https://image2url.com/r2/default/images/1771738294524-22c328d0-497b-490c-9b84-ce79fa42c372.jpg', bio: 'Manages the day-to-day operations and ensures smooth delivery across all zones.' },
  ];

  const values = [
    { icon: <Users className="h-6 w-6" />, title: 'Customer First', desc: 'Every decision centers around providing the best possible experience.', gradient: 'from-blue-500 to-indigo-500' },
    { icon: <Award className="h-6 w-6" />, title: 'Quality Excellence', desc: 'We maintain the highest standards in product quality and service.', gradient: 'from-emerald-500 to-teal-500' },
    { icon: <MapPin className="h-6 w-6" />, title: 'Community Impact', desc: 'Committed to supporting local businesses and making a positive impact.', gradient: 'from-purple-500 to-violet-500' },
  ];

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
      {/* Promo Banner */}
      <section className="pt-6 pb-2 lg:pb-6 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="relative rounded-2xl overflow-hidden bg-emerald-50 h-[300px] md:h-[400px]">
            <img
              src="https://as1.ftcdn.net/v2/jpg/16/29/70/80/1000_F_1629708072_5C2KrBU5mrGky8kKFB3Ro96MN06sNQmy.jpg"
              className="absolute inset-0 w-full h-full object-cover"
              alt="Groceries Promo"
            />
            <div className="absolute inset-0 bg-gradient-to-r from-emerald-900/90 via-emerald-800/80 to-transparent"></div>

            <div className="relative h-full flex items-center p-6 sm:p-8 md:p-12 lg:p-16">
              <div className="max-w-xl text-white space-y-4 md:space-y-6">
                <div className="inline-block bg-orange-500 text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded-sm uppercase tracking-wider mb-2">
                  Daily Essentials
                </div>
                <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-display font-bold leading-tight text-balance">
                  Get Fresh Groceries<br />Delivered Fast
                </h1>
                <p className="text-emerald-50 text-base md:text-lg hidden md:block">
                  Quality you can trust, prices you'll love. Stock up on your favorite brands today!
                </p>
                <div className="pt-2 md:pt-4">
                  <Link to="/dashboard" className="btn-accent shadow-lg shadow-orange-500/30 w-auto inline-flex items-center">
                    {!user ? "Start Shopping" : "Order Now"} <ArrowRight className="ml-2 h-4 w-4" />
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Trust Bar */}
      <section className="pb-8 bg-white border-b border-gray-100 hidden md:block">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between gap-4 py-4 px-8 bg-gray-50 rounded-xl border border-gray-200/60">
            {features.map((feature, i) => (
              <div key={i} className="flex items-center gap-3">
                <div className="text-emerald-600 flex-shrink-0">
                  {React.cloneElement(feature.icon as React.ReactElement, { className: "h-6 w-6" })}
                </div>
                <div>
                  <div className="font-bold text-gray-800 text-sm">{feature.title}</div>
                  <div className="text-xs text-gray-500">{feature.description}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Categories Horizontal Scroll */}
      <section className="py-10 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-xl md:text-2xl font-bold text-gray-900 border-b-2 border-emerald-500 pb-1">Shop by Category</h2>
          </div>
          <div className="flex overflow-x-auto hide-scrollbar gap-4 md:gap-8 pb-4 -mx-4 px-4 sm:mx-0 sm:px-0">
            {categories.map((cat, i) => (
              <Link
                to={`/dashboard?category=${cat.id}`}
                key={i}
                className="group flex flex-col items-center min-w-[76px] sm:min-w-[100px] gap-2 lg:gap-3 shrink-0"
              >
                <div className="w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24 rounded-full overflow-hidden bg-gray-50 p-1 ring-1 ring-gray-200 group-hover:ring-emerald-500 transition-all duration-300">
                  <img
                    src={cat.image}
                    alt={cat.name}
                    className="w-full h-full object-cover rounded-full group-hover:scale-110 transition-transform duration-500 bg-white"
                  />
                </div>
                <span className="text-[10px] sm:text-xs lg:text-sm font-semibold text-gray-700 text-center line-clamp-2 max-w-[100%] group-hover:text-emerald-600 leading-snug">
                  {cat.name}
                </span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Featured Products */}
      <section className="py-12 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between mb-8 pb-4 border-b border-gray-100">
            <h2 className="text-2xl font-bold font-display text-gray-900 border-b-2 border-emerald-500 pb-1">
              Featured Products
            </h2>
            <Link to="/dashboard" className="text-emerald-600 font-semibold text-sm hover:text-emerald-700 flex items-center gap-1 group">
              View All <ArrowRight className="h-4 w-4 transform group-hover:translate-x-1 transition-transform" />
            </Link>
          </div>

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-6">
            {featuredProducts.map((product: Product, i: number) => (
              <div key={i} className="glass-card-solid p-4 flex flex-col relative group">
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
                      className="bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white px-3 py-1.5 rounded-lg text-sm font-bold transition-colors shadow-sm whitespace-nowrap"
                    >
                      ADD
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* About Section Snippet */}
      <section className="py-16 bg-emerald-50/50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
              <h2 className="text-3xl font-bold font-display text-gray-900 mb-6">
                About <span className="text-emerald-600">Balaji Trading Company</span>
              </h2>
              <p className="text-gray-600 text-lg mb-6 leading-relaxed">
                We're revolutionizing grocery delivery by bringing fresh, quality products directly to your doorstep.
                Our mission is to make grocery shopping convenient, reliable, and enjoyable.
              </p>
              <ul className="space-y-4 mb-8">
                <li className="flex items-center gap-3">
                  <div className="bg-emerald-100 p-2 rounded-lg text-emerald-600">
                    <Shield className="h-5 w-5" />
                  </div>
                  <span className="text-gray-700 font-medium">100% Quality Guarantee</span>
                </li>
                <li className="flex items-center gap-3">
                  <div className="bg-emerald-100 p-2 rounded-lg text-emerald-600">
                    <Truck className="h-5 w-5" />
                  </div>
                  <span className="text-gray-700 font-medium">Fast & Reliable Delivery</span>
                </li>
              </ul>
              <Link to="/about" className="text-emerald-600 font-semibold flex items-center gap-2 hover:text-emerald-700 transition-colors">
                Learn more about us <ArrowRight className="h-4 w-4" />
              </Link>
            </div>
            <div className="relative h-[400px] rounded-2xl overflow-hidden shadow-lg">
              <img
                src="https://images.pexels.com/photos/4393021/pexels-photo-4393021.jpeg?auto=compress&cs=tinysrgb&w=600"
                alt="Fresh groceries"
                className="absolute inset-0 w-full h-full object-cover"
              />
            </div>
          </div>
        </div>
      </section>

      {/* Team Section Snippet */}
      <section className="bg-white py-16 lg:py-20 border-t border-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-3">Meet Our <span className="text-emerald-600">Team</span></h2>
            <p className="text-gray-500 text-lg">Our dedicated team works tirelessly for the best experience</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            {team.map((member, index) => (
              <div key={index} className="group text-center p-6 rounded-2xl hover:bg-gray-50 transition-all duration-500 hover:-translate-y-1 border border-transparent hover:border-gray-100">
                <div className="relative w-28 h-28 mx-auto mb-4">
                  <div className="absolute -inset-1 bg-gradient-to-br from-emerald-400 to-teal-400 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 blur-sm"></div>
                  <img src={member.image} alt={member.name} className="relative w-28 h-28 rounded-full object-cover ring-4 ring-white shadow-lg" />
                </div>
                <h3 className="text-lg font-semibold font-display text-gray-900 mb-1">{member.name}</h3>
                <p className="text-emerald-600 font-medium text-sm mb-3">{member.role}</p>
                <p className="text-gray-500 text-sm leading-relaxed">{member.bio}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Values Section Snippet */}
      <section className="py-16 lg:py-20 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-3">Our <span className="text-emerald-600">Values</span></h2>
            <p className="text-gray-500 text-lg">Core values that guide everything we do</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
            {values.map((val, i) => (
              <div key={i} className="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md hover:-translate-y-1 transition-all duration-500">
                <div className={`bg-gradient-to-br ${val.gradient} text-white w-12 h-12 rounded-xl flex items-center justify-center mb-4 shadow-sm`}>
                  {val.icon}
                </div>
                <h3 className="text-xl font-semibold font-display text-gray-900 mb-3">{val.title}</h3>
                <p className="text-gray-500 leading-relaxed">{val.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Contact Section Snippet */}
      <section className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-4">Get in Touch</h2>
            <p className="text-gray-500 text-lg">We're here to help with any questions or concerns</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
            <div className="text-center p-6 bg-gray-50 rounded-2xl border border-gray-100 hover:border-emerald-200 transition-colors">
              <div className="bg-emerald-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
                <Phone className="h-5 w-5" />
              </div>
              <h3 className="font-bold text-gray-900 mb-2">Phone</h3>
              <p className="text-gray-600">81******38</p>
            </div>

            <div className="text-center p-6 bg-gray-50 rounded-2xl border border-gray-100 hover:border-emerald-200 transition-colors">
              <div className="bg-emerald-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
                <Mail className="h-5 w-5" />
              </div>
              <h3 className="font-bold text-gray-900 mb-2">Email</h3>
              <p className="text-gray-600">jindalnitesh285@gmail.com</p>
            </div>

            <div className="text-center p-6 bg-gray-50 rounded-2xl border border-gray-100 hover:border-emerald-200 transition-colors">
              <div className="bg-emerald-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
                <MapPin className="h-5 w-5" />
              </div>
              <h3 className="font-bold text-gray-900 mb-2">Location</h3>
              <p className="text-gray-600 text-sm">Balaji Trading Company, Bank Colony, Jaipur</p>
            </div>
          </div>

          <div className="text-center mt-8">
            <Link to="/contact" className="text-emerald-600 font-semibold inline-flex items-center gap-2 hover:text-emerald-700 transition-colors">
              View full contact details <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-16 bg-gray-50 border-t border-gray-200/60">
        <div className="max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-3xl font-bold font-display text-gray-900 mb-4">
            Fresh groceries at your fingertips
          </h2>
          <p className="text-gray-500 text-lg mb-8">
            {!user
              ? "Create an account to track orders, save multiple addresses, and checkout faster."
              : "Explore our wide range of fresh products and get them delivered to your door."}
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            {!user ? (
              <Link to="/signup" className="btn-primary flex items-center justify-center !px-8">
                Sign Up Now <ArrowRight className="ml-2 h-4 w-4" />
              </Link>
            ) : (
              <Link to="/dashboard" className="bg-emerald-600 text-white hover:bg-emerald-700 px-8 py-3 rounded-xl font-semibold transition-colors shadow-md flex items-center justify-center group gap-2">
                Continue Shopping <ArrowRight className="h-5 w-5 transform group-hover:translate-x-1 transition-transform" />
              </Link>
            )}
          </div>
        </div>
      </section>
    </div>
  );
};

export default HomePage;
