import React from 'react';
import { MapPin, Users, Award, Clock, Truck, Shield, ArrowRight, CheckCircle, Star, Heart } from 'lucide-react';
import { Link } from 'react-router-dom';

const AboutPage: React.FC = () => {
  const features = [
    {
      icon: <Clock className="h-7 w-7" />,
      title: 'Fast Delivery',
      description: 'Get your groceries delivered within 30–60 minutes to your doorstep.',
      gradient: 'from-emerald-500 to-teal-500',
      glow: 'shadow-emerald-500/30',
    },
    {
      icon: <Shield className="h-7 w-7" />,
      title: 'Quality Guaranteed',
      description: 'We ensure 100% freshness and highest quality across every product.',
      gradient: 'from-blue-500 to-indigo-500',
      glow: 'shadow-blue-500/30',
    },
    {
      icon: <Truck className="h-7 w-7" />,
      title: 'Wide Coverage',
      description: 'Serving multiple neighbourhoods with constantly expanding zones.',
      gradient: 'from-purple-500 to-violet-500',
      glow: 'shadow-purple-500/30',
    },
    {
      icon: <Users className="h-7 w-7" />,
      title: 'Expert Team',
      description: 'Trained staff who carefully select and pack every single order.',
      gradient: 'from-amber-500 to-orange-500',
      glow: 'shadow-amber-500/30',
    },
  ];

  const stats = [
    { number: '50,000+', label: 'Happy Customers', icon: <Heart className="h-5 w-5" /> },
    { number: '500+', label: 'Partner Stores', icon: <Star className="h-5 w-5" /> },
    { number: '25+', label: 'Cities Served', icon: <MapPin className="h-5 w-5" /> },
    { number: '99.5%', label: 'Delivery Success', icon: <CheckCircle className="h-5 w-5" /> },
  ];

  const team = [
    {
      name: 'Inder Kumar Gupta',
      role: 'CEO & Founder',
      image: 'https://image2url.com/r2/default/images/1771738555505-aa327b54-403f-4a70-9000-4ee992f43173.jpeg',
      bio: 'Founded StoreToDoor with a vision to revolutionize grocery delivery.',
      accent: 'from-emerald-400 to-teal-400',
    },
    {
      name: 'Piyush Kumar Jindal',
      role: 'CEO & Founder',
      image: 'https://image2url.com/r2/default/images/1771738386962-b652116c-7ff7-4202-b219-5aa86e78d2ed.jpg',
      bio: 'Drives the strategic vision and growth of the company to revolutionize grocery delivery.',
      accent: 'from-blue-400 to-indigo-400',
    },
    {
      name: 'Manish Jindal',
      role: 'CEO & Founder',
      image: 'https://image2url.com/r2/default/images/1771738294524-22c328d0-497b-490c-9b84-ce79fa42c372.jpg',
      bio: 'Manages the day-to-day operations and ensures smooth delivery across all zones.',
      accent: 'from-purple-400 to-violet-400',
    },
  ];

  const values = [
    {
      icon: <Users className="h-6 w-6" />,
      title: 'Customer First',
      desc: 'Every decision centers around providing the best possible experience.',
      gradient: 'from-blue-500 to-indigo-500',
      bg: 'bg-blue-50',
      border: 'hover:border-blue-200',
    },
    {
      icon: <Award className="h-6 w-6" />,
      title: 'Quality Excellence',
      desc: 'We maintain the highest standards in product quality and service.',
      gradient: 'from-emerald-500 to-teal-500',
      bg: 'bg-emerald-50',
      border: 'hover:border-emerald-200',
    },
    {
      icon: <MapPin className="h-6 w-6" />,
      title: 'Community Impact',
      desc: 'Committed to supporting local businesses and making a positive impact.',
      gradient: 'from-purple-500 to-violet-500',
      bg: 'bg-purple-50',
      border: 'hover:border-purple-200',
    },
  ];

  const pillars = [
    'Fresh produce sourced locally',
    'Zero compromise on quality',
    'Transparent pricing, no hidden fees',
    'Supporting Jaipur\'s local businesses',
  ];

  return (
    <div className="min-h-screen bg-white">

      {/* ── Hero ── */}
      <section className="relative bg-[#0d1f17] overflow-hidden">
        {/* Background texture orbs */}
        <div className="absolute top-0 left-1/3 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[120px]" />
        <div className="absolute bottom-0 right-1/4 w-[400px] h-[400px] bg-teal-500/10 rounded-full blur-[100px]" />
        <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/40 to-transparent" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
          <div className="text-center max-w-3xl mx-auto">
            {/* Badge */}
            <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-4 py-1.5 mb-8">
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">Our Story</span>
            </div>

            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold font-display text-white leading-tight mb-6">
              About{' '}
              <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                Balaji Trading
              </span>
              <br />Company
            </h1>

            <p className="text-gray-400 text-lg leading-relaxed mb-10">
              Revolutionizing grocery delivery in Jaipur — bringing fresh, quality products
              directly to your doorstep with speed, care, and reliability.
            </p>

            <div className="flex flex-wrap justify-center gap-4">
              <Link
                to="/dashboard"
                className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-semibold px-7 py-3 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5"
              >
                Shop Now <ArrowRight className="h-4 w-4" />
              </Link>
              <Link
                to="/contact"
                className="inline-flex items-center gap-2 bg-white/5 border border-white/10 hover:bg-white/10 hover:border-white/20 text-white font-semibold px-7 py-3 rounded-xl transition-all duration-300"
              >
                Contact Us
              </Link>
            </div>
          </div>
        </div>

        {/* Wave separator */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 60L1440 60L1440 0C1200 40 960 60 720 40C480 20 240 0 0 30L0 60Z" fill="white" />
          </svg>
        </div>
      </section>

      {/* ── Mission ── */}
      <section className="py-20 lg:py-28 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            {/* Left: text */}
            <div>
              <p className="text-emerald-600 font-semibold text-sm uppercase tracking-widest mb-3">Who We Are</p>
              <h2 className="text-3xl md:text-4xl font-bold font-display text-gray-900 mb-6 leading-tight">
                Our <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Mission</span>
              </h2>
              <p className="text-gray-500 text-lg mb-5 leading-relaxed">
                At Balaji Trading Company, we believe everyone deserves access to fresh, quality groceries without the hassle.
                Our mission is to make grocery shopping convenient, reliable, and enjoyable.
              </p>
              <p className="text-gray-500 text-lg mb-8 leading-relaxed">
                We partner with local vendors to bring you the freshest produce, pantry staples, and specialty items —
                all while supporting local businesses and communities in Jaipur.
              </p>

              {/* Pillars checklist */}
              <ul className="space-y-3 mb-8">
                {pillars.map((p) => (
                  <li key={p} className="flex items-center gap-3 text-gray-700 text-sm font-medium">
                    <div className="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                      <CheckCircle className="h-3.5 w-3.5 text-emerald-600" />
                    </div>
                    {p}
                  </li>
                ))}
              </ul>

              {/* Local focus card */}
              <div className="flex items-center gap-4 p-4 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-100 rounded-2xl">
                <div className="bg-gradient-to-br from-emerald-500 to-teal-500 text-white p-3 rounded-xl shadow-md shadow-emerald-500/20 flex-shrink-0">
                  <MapPin className="h-6 w-6" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-900 text-sm">Jaipur Local Focus</h3>
                  <p className="text-gray-500 text-xs mt-0.5">Proudly supporting local businesses and communities</p>
                </div>
              </div>
            </div>

            {/* Right: image */}
            <div className="relative">
              <div className="absolute -inset-4 bg-gradient-to-br from-emerald-400/20 to-teal-400/20 rounded-3xl blur-2xl" />
              <div className="relative rounded-3xl overflow-hidden shadow-2xl shadow-emerald-100">
                <img
                  src="https://images.pexels.com/photos/4393021/pexels-photo-4393021.jpeg?auto=compress&cs=tinysrgb&w=600"
                  alt="Grocery store"
                  className="w-full h-[460px] object-cover"
                />
                {/* overlay badge */}
                <div className="absolute bottom-6 left-6 right-6 bg-white/95 backdrop-blur-sm rounded-2xl p-4 flex items-center gap-3 shadow-lg">
                  <div className="bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl p-2.5 flex-shrink-0">
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

      {/* ── Features / Why Us ── */}
      <section className="py-20 lg:py-24 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <p className="text-emerald-600 font-semibold text-sm uppercase tracking-widest mb-3">Our Advantage</p>
            <h2 className="text-3xl md:text-4xl font-bold font-display text-gray-900 mb-4">
              Why Choose <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Us?</span>
            </h2>
            <p className="text-gray-500 text-lg max-w-xl mx-auto">Committed to delivering the very best grocery experience, every time.</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {features.map((feature, index) => (
              <div
                key={index}
                className="group bg-white border border-gray-100 rounded-2xl p-7 hover:border-transparent hover:shadow-xl transition-all duration-500 hover:-translate-y-2 text-center"
              >
                <div className={`bg-gradient-to-br ${feature.gradient} ${feature.glow} text-white w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg group-hover:scale-110 group-hover:shadow-xl transition-all duration-500`}>
                  {feature.icon}
                </div>
                <h3 className="text-lg font-bold font-display text-gray-900 mb-2">{feature.title}</h3>
                <p className="text-gray-500 text-sm leading-relaxed">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Stats ── */}
      <section className="relative py-20 lg:py-24 bg-[#0d1f17] overflow-hidden">
        <div className="absolute inset-0">
          <div className="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-[100px]" />
          <div className="absolute bottom-0 left-0 w-72 h-72 bg-teal-500/10 rounded-full blur-[80px]" />
        </div>

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <p className="text-emerald-400 font-semibold text-sm uppercase tracking-widest mb-3">By the Numbers</p>
            <h2 className="text-3xl md:text-4xl font-bold font-display text-white mb-3">Our Impact</h2>
            <p className="text-gray-400 text-lg">Numbers that showcase our commitment to excellence</p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {stats.map((stat, index) => (
              <div
                key={index}
                className="relative group bg-white/5 border border-white/10 hover:border-emerald-500/30 rounded-2xl p-8 text-center transition-all duration-500 hover:bg-white/8 hover:-translate-y-1"
              >
                <div className="flex justify-center mb-3 text-emerald-400/60 group-hover:text-emerald-400 transition-colors duration-300">
                  {stat.icon}
                </div>
                <div className="text-3xl md:text-4xl font-extrabold font-display bg-gradient-to-r from-emerald-300 to-teal-300 bg-clip-text text-transparent mb-2">
                  {stat.number}
                </div>
                <div className="text-gray-400 text-sm font-medium">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Team ── */}
      <section className="py-20 lg:py-28 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <p className="text-emerald-600 font-semibold text-sm uppercase tracking-widest mb-3">The People</p>
            <h2 className="text-3xl md:text-4xl font-bold font-display text-gray-900 mb-4">
              Meet Our <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Team</span>
            </h2>
            <p className="text-gray-500 text-lg">Our dedicated team works tirelessly to give you the best experience</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 max-w-4xl mx-auto">
            {team.map((member, index) => (
              <div key={index} className="group relative">
                {/* Card */}
                <div className="bg-white border border-gray-100 rounded-2xl p-8 text-center hover:shadow-xl hover:border-gray-200 transition-all duration-500 hover:-translate-y-2">
                  {/* Avatar */}
                  <div className="relative w-28 h-28 mx-auto mb-5">
                    <div className={`absolute -inset-1 bg-gradient-to-br ${member.accent} rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 blur-sm`} />
                    <img
                      src={member.image}
                      alt={member.name}
                      className="relative w-28 h-28 rounded-full object-cover ring-4 ring-white shadow-lg group-hover:shadow-xl transition-shadow duration-500"
                    />
                  </div>

                  <h3 className="text-lg font-bold font-display text-gray-900 mb-1">{member.name}</h3>
                  <div className={`inline-block bg-gradient-to-r ${member.accent} text-white text-xs font-semibold px-3 py-1 rounded-full mb-4`}>
                    {member.role}
                  </div>
                  <p className="text-gray-500 text-sm leading-relaxed">{member.bio}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Values ── */}
      <section className="py-20 lg:py-24 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <p className="text-emerald-600 font-semibold text-sm uppercase tracking-widest mb-3">What We Stand For</p>
            <h2 className="text-3xl md:text-4xl font-bold font-display text-gray-900 mb-4">
              Our <span className="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Values</span>
            </h2>
            <p className="text-gray-500 text-lg">Core beliefs that guide everything we do</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            {values.map((val, i) => (
              <div
                key={i}
                className={`group bg-white border border-gray-100 ${val.border} rounded-2xl p-8 hover:shadow-lg hover:-translate-y-1 transition-all duration-500`}
              >
                <div className={`bg-gradient-to-br ${val.gradient} text-white w-14 h-14 rounded-2xl flex items-center justify-center mb-5 shadow-md group-hover:scale-110 transition-transform duration-500`}>
                  {val.icon}
                </div>
                <h3 className="text-xl font-bold font-display text-gray-900 mb-3">{val.title}</h3>
                <p className="text-gray-500 leading-relaxed">{val.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA ── */}
      <section className="relative py-20 lg:py-28 bg-[#0d1f17] overflow-hidden">
        <div className="absolute inset-0">
          <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[120px]" />
          <div className="absolute bottom-0 left-0 w-96 h-96 bg-teal-500/10 rounded-full blur-[100px]" />
          <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent" />
        </div>

        <div className="relative max-w-3xl mx-auto px-4 text-center">
          <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-4 py-1.5 mb-8">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
            <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">Join Our Family</span>
          </div>

          <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold font-display text-white mb-5 leading-tight">
            Ready to Experience<br />
            <span className="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">the Difference?</span>
          </h2>

          <p className="text-gray-400 text-lg mb-10 leading-relaxed">
            Join thousands of satisfied customers who trust us for their daily grocery needs in Jaipur.
          </p>

          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link
              to="/signup"
              className="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-8 py-3.5 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5"
            >
              Get Started Today <ArrowRight className="h-5 w-5" />
            </Link>
            <Link
              to="/dashboard"
              className="inline-flex items-center justify-center gap-2 bg-white/5 border border-white/15 hover:bg-white/10 hover:border-white/25 text-white font-semibold px-8 py-3.5 rounded-xl transition-all duration-300"
            >
              Browse Products
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
};

export default AboutPage;