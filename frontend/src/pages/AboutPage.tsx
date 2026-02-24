import React from 'react';
import { MapPin, Users, Award, Clock, Truck, Shield, ArrowRight } from 'lucide-react';
import { Link } from 'react-router-dom';

const AboutPage: React.FC = () => {
  const features = [
    { icon: <Clock className="h-7 w-7" />, title: 'Fast Delivery', description: 'Get your groceries delivered within 30-60 minutes to your doorstep.', gradient: 'from-emerald-500 to-teal-500' },
    { icon: <Shield className="h-7 w-7" />, title: 'Quality Guaranteed', description: 'We ensure the highest quality products with 100% freshness guarantee.', gradient: 'from-blue-500 to-indigo-500' },
    { icon: <Truck className="h-7 w-7" />, title: 'Wide Coverage', description: 'Serving multiple neighborhoods with expanding delivery zones.', gradient: 'from-purple-500 to-violet-500' },
    { icon: <Users className="h-7 w-7" />, title: 'Expert Team', description: 'Our trained staff carefully selects and packs your orders.', gradient: 'from-amber-500 to-orange-500' },
  ];

  const stats = [
    { number: '50,000+', label: 'Happy Customers' },
    { number: '500+', label: 'Partner Stores' },
    { number: '25+', label: 'Cities Served' },
    { number: '99.5%', label: 'Delivery Success Rate' },
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

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <div className="relative bg-gradient-to-br from-emerald-600 via-teal-600 to-emerald-800 text-white overflow-hidden">
        <div className="absolute inset-0">
          <div className="absolute top-10 right-20 w-72 h-72 bg-emerald-400/20 rounded-full blur-3xl animate-float"></div>
          <div className="absolute bottom-10 left-10 w-56 h-56 bg-teal-400/15 rounded-full blur-3xl animate-float-slow"></div>
        </div>
        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
          <div className="text-center animate-fade-in-up">
            <h1 className="text-4xl md:text-5xl font-bold font-display mb-4">About Balaji Trading Company</h1>
            <p className="text-lg text-emerald-100/80 max-w-3xl mx-auto leading-relaxed">
              We're revolutionizing grocery delivery by bringing fresh, quality products directly to your doorstep.
              Founded with a vision to make fresh food accessible to everyone.
            </p>
          </div>
        </div>
      </div>

      {/* Mission Section */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <div className="animate-fade-in-up">
            <h2 className="section-title mb-6">Our <span className="gradient-text">Mission</span></h2>
            <p className="text-gray-500 text-lg mb-6 leading-relaxed">
              At Balaji Trading Company, we believe everyone deserves access to fresh, quality groceries without the hassle.
              Our mission is to make grocery shopping convenient, reliable, and enjoyable.
            </p>
            <p className="text-gray-500 text-lg mb-8 leading-relaxed">
              We partner with local stores and vendors to bring you the freshest produce, pantry staples, and specialty items,
              all while supporting local businesses and communities.
            </p>
            <div className="flex items-center space-x-4 p-4 bg-emerald-50 rounded-2xl">
              <div className="bg-gradient-to-br from-emerald-500 to-teal-500 text-white p-3 rounded-xl shadow-md">
                <MapPin className="h-6 w-6" />
              </div>
              <div>
                <h3 className="font-semibold font-display text-gray-900">Local Focus</h3>
                <p className="text-gray-500 text-sm">Supporting local businesses and communities</p>
              </div>
            </div>
          </div>
          <div className="relative">
            <div className="absolute -inset-4 bg-gradient-to-br from-emerald-400/20 to-teal-400/20 rounded-3xl blur-2xl"></div>
            <img
              src="https://images.pexels.com/photos/4393021/pexels-photo-4393021.jpeg?auto=compress&cs=tinysrgb&w=600"
              alt="Fresh groceries"
              className="relative rounded-3xl shadow-elevated w-full h-[400px] object-cover"
            />
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="bg-white py-16 lg:py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="section-title mb-3">Why Choose <span className="gradient-text">Us?</span></h2>
            <p className="section-subtitle">Committed to the best grocery delivery experience</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {features.map((feature, index) => (
              <div key={index} className="group text-center p-6 rounded-2xl hover:bg-gray-50 transition-all duration-500 hover:-translate-y-1">
                <div className={`bg-gradient-to-br ${feature.gradient} text-white w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg group-hover:scale-110 transition-all duration-500`}>
                  {feature.icon}
                </div>
                <h3 className="text-lg font-semibold font-display text-gray-900 mb-2">{feature.title}</h3>
                <p className="text-gray-500 text-sm leading-relaxed">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Stats Section */}
      <div className="relative bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white py-16 lg:py-20 overflow-hidden">
        <div className="absolute inset-0">
          <div className="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
          <div className="absolute bottom-0 left-0 w-72 h-72 bg-teal-400/10 rounded-full blur-3xl"></div>
        </div>
        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold font-display mb-3">Our Impact</h2>
            <p className="text-emerald-100/80 text-lg">Numbers that showcase our commitment to excellence</p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
            {stats.map((stat, index) => (
              <div key={index} className="text-center">
                <div className="text-3xl md:text-4xl font-bold font-display bg-gradient-to-r from-amber-300 to-orange-400 bg-clip-text text-transparent mb-2">{stat.number}</div>
                <div className="text-emerald-100/80 text-sm">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Team Section */}
      <div className="bg-white py-16 lg:py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="section-title mb-3">Meet Our <span className="gradient-text">Team</span></h2>
            <p className="section-subtitle">Our dedicated team works tirelessly for the best experience</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {team.map((member, index) => (
              <div key={index} className="group text-center p-6 rounded-2xl hover:bg-gray-50 transition-all duration-500 hover:-translate-y-1">
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
      </div>

      {/* Values Section */}
      <div className="py-16 lg:py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="section-title mb-3">Our <span className="gradient-text">Values</span></h2>
            <p className="section-subtitle">Core values that guide everything we do</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {values.map((val, i) => (
              <div key={i} className="glass-card-solid p-8 hover:-translate-y-1 transition-all duration-500">
                <div className={`bg-gradient-to-br ${val.gradient} text-white w-12 h-12 rounded-xl flex items-center justify-center mb-4 shadow-lg`}>
                  {val.icon}
                </div>
                <h3 className="text-xl font-semibold font-display text-gray-900 mb-3">{val.title}</h3>
                <p className="text-gray-500 leading-relaxed">{val.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* CTA Section */}
      <div className="relative bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white py-16 lg:py-20 overflow-hidden">
        <div className="absolute inset-0">
          <div className="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
        </div>
        <div className="relative max-w-4xl mx-auto px-4 text-center">
          <h2 className="text-3xl md:text-4xl font-bold font-display mb-4">Ready to Experience the Difference?</h2>
          <p className="text-emerald-100/80 text-lg mb-8 max-w-2xl mx-auto">
            Join thousands of satisfied customers who trust us for their grocery needs
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link to="/signup" className="btn-accent shimmer-btn flex items-center justify-center !px-8">
              Get Started Today <ArrowRight className="ml-2 h-5 w-5" />
            </Link>
            <Link to="/dashboard" className="btn-secondary !border-white/30 !text-white hover:!bg-white hover:!text-emerald-700 flex items-center justify-center !px-8">
              Browse Products
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AboutPage;