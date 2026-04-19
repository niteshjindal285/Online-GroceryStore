import React, { useState } from 'react';
import { Mail, Phone, MapPin, Clock, Send, ChevronDown, CheckCircle, MessageSquare } from 'lucide-react';

const ContactPage: React.FC = () => {
  const [formData, setFormData] = useState({
    name: '', email: '', phone: '', subject: '', message: ''
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitStatus, setSubmitStatus] = useState<'idle' | 'success' | 'error'>('idle');
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setTimeout(() => {
      setSubmitStatus('success');
      setIsSubmitting(false);
      setFormData({ name: '', email: '', phone: '', subject: '', message: '' });
    }, 1500);
  };

  const contactInfo = [
    {
      icon: <Phone className="h-6 w-6" />,
      title: 'Phone',
      details: ['8107205038'],
      sub: 'Call us anytime',
      href: 'tel:8107205038',
      gradient: 'from-blue-500 to-indigo-500',
      glow: 'shadow-blue-500/25',
      ring: 'hover:border-blue-200',
      bg: 'hover:bg-blue-50/50',
    },
    {
      icon: <Mail className="h-6 w-6" />,
      title: 'Email',
      details: ['jindalnitesh285@gmail.com'],
      sub: 'We reply within 24h',
      href: 'mailto:jindalnitesh285@gmail.com',
      gradient: 'from-emerald-500 to-teal-500',
      glow: 'shadow-emerald-500/25',
      ring: 'hover:border-emerald-200',
      bg: 'hover:bg-emerald-50/50',
    },
    {
      icon: <MapPin className="h-6 w-6" />,
      title: 'Address',
      details: ['Bank Colony, Murlipura', 'Jaipur, Rajasthan 302039'],
      sub: 'Visit our store',
      href: 'https://maps.google.com',
      gradient: 'from-purple-500 to-violet-500',
      glow: 'shadow-purple-500/25',
      ring: 'hover:border-purple-200',
      bg: 'hover:bg-purple-50/50',
    },
    {
      icon: <Clock className="h-6 w-6" />,
      title: 'Hours',
      details: ['Mon–Fri: 6 AM – 11 PM', 'Sat–Sun: 7 AM – 10 PM'],
      sub: 'Open all week',
      href: null,
      gradient: 'from-amber-500 to-orange-500',
      glow: 'shadow-amber-500/25',
      ring: 'hover:border-amber-200',
      bg: 'hover:bg-amber-50/50',
    },
  ];

  const faqs = [
    { q: 'What are your delivery hours?', a: 'We deliver 7 days a week. Monday–Friday: 6:00 AM – 11:00 PM, Saturday–Sunday: 7:00 AM – 10:00 PM.' },
    { q: 'How fast is delivery?', a: 'Most orders are delivered within 30–60 minutes. During peak hours, it may take up to 90 minutes.' },
    { q: 'What payment methods do you accept?', a: 'We accept all major credit/debit cards, UPI, and cash on delivery.' },
    { q: 'Is there a minimum order amount?', a: 'The minimum order amount is ₹200. Orders above ₹500 qualify for free delivery.' },
    { q: 'How do I track my order?', a: 'You can track your order in real-time through your account dashboard or via the confirmation email we send.' },
  ];

  const inputClass =
    'w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 hover:border-gray-300 transition-all duration-200 placeholder-gray-400';

  return (
    <div className="min-h-screen bg-white">

      {/* ── Hero ── */}
      <section className="relative bg-[#0d1f17] overflow-hidden">
        <div className="absolute top-0 left-1/3 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[120px]" />
        <div className="absolute bottom-0 right-1/4 w-[400px] h-[400px] bg-teal-500/10 rounded-full blur-[100px]" />
        <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/40 to-transparent" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-28">
          <div className="text-center max-w-2xl mx-auto">
            <div className="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-4 py-1.5 mb-8">
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
              <span className="text-emerald-400 text-xs font-semibold uppercase tracking-widest">We're Here to Help</span>
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold font-display text-white mb-6 leading-tight">
              Get in{' '}
              <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                Touch
              </span>
            </h1>
            <p className="text-gray-400 text-lg leading-relaxed">
              Have a question, concern, or just want to say hello?<br />
              Our team is always ready to help you out.
            </p>
          </div>
        </div>

        {/* Wave separator */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 60L1440 60L1440 0C1200 40 960 60 720 40C480 20 240 0 0 30L0 60Z" fill="white" />
          </svg>
        </div>
      </section>

      {/* ── Contact Info Cards ── */}
      <section className="relative -mt-2 pt-16 pb-8 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            {contactInfo.map((info, index) => {
              const Card = info.href ? 'a' : 'div';
              const cardProps = info.href ? { href: info.href, target: '_blank', rel: 'noreferrer' } : {};
              return (
                <Card
                  key={index}
                  {...(cardProps as any)}
                  className={`group bg-white border border-gray-100 ${info.ring} ${info.bg} rounded-2xl p-6 text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-500 cursor-pointer`}
                >
                  <div className={`bg-gradient-to-br ${info.gradient} shadow-lg ${info.glow} text-white w-14 h-14 mx-auto mb-4 flex items-center justify-center rounded-2xl group-hover:scale-110 transition-transform duration-500`}>
                    {info.icon}
                  </div>
                  <p className="text-xs text-gray-400 uppercase tracking-widest font-medium mb-1">{info.sub}</p>
                  <h3 className="text-base font-bold font-display text-gray-900 mb-2">{info.title}</h3>
                  <div className="space-y-0.5">
                    {info.details.map((detail, idx) => (
                      <p key={idx} className="text-gray-500 text-sm">{detail}</p>
                    ))}
                  </div>
                </Card>
              );
            })}
          </div>
        </div>
      </section>

      {/* ── Form + FAQ ── */}
      <section className="py-16 lg:py-20 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">

            {/* Contact Form */}
            <div className="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
              <div className="flex items-center gap-3 mb-7">
                <div className="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
                  <MessageSquare className="h-5 w-5" />
                </div>
                <div>
                  <h2 className="text-xl font-bold font-display text-gray-900">Send us a Message</h2>
                  <p className="text-xs text-gray-400 mt-0.5">We'll reply within 24 hours</p>
                </div>
              </div>

              {submitStatus === 'success' && (
                <div className="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3.5 rounded-xl mb-6">
                  <CheckCircle className="h-5 w-5 flex-shrink-0" />
                  <p className="text-sm font-medium">Thank you! We'll get back to you within 24 hours.</p>
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="name" className="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Full Name *</label>
                    <input type="text" id="name" name="name" value={formData.name} onChange={handleChange} required className={inputClass} placeholder="Your full name" />
                  </div>
                  <div>
                    <label htmlFor="email" className="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Email *</label>
                    <input type="email" id="email" name="email" value={formData.email} onChange={handleChange} required className={inputClass} placeholder="your@email.com" />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="phone" className="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Phone</label>
                    <input type="tel" id="phone" name="phone" value={formData.phone} onChange={handleChange} className={inputClass} placeholder="Phone number" />
                  </div>
                  <div>
                    <label htmlFor="subject" className="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Subject *</label>
                    <select id="subject" name="subject" value={formData.subject} onChange={handleChange} required className={inputClass}>
                      <option value="">Select a subject</option>
                      <option value="general">General Inquiry</option>
                      <option value="order">Order Support</option>
                      <option value="technical">Technical Issue</option>
                      <option value="feedback">Feedback</option>
                      <option value="partnership">Partnership</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label htmlFor="message" className="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Message *</label>
                  <textarea
                    id="message" name="message" value={formData.message}
                    onChange={handleChange} required rows={5}
                    className={`${inputClass} resize-none`}
                    placeholder="Tell us how we can help you..."
                  />
                </div>

                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0"
                >
                  {isSubmitting ? (
                    <>
                      <div className="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent" />
                      Sending...
                    </>
                  ) : (
                    <>
                      <Send className="h-4 w-4" />
                      Send Message
                    </>
                  )}
                </button>
              </form>
            </div>

            {/* FAQ */}
            <div className="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
              <div className="flex items-center gap-3 mb-7">
                <div className="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600">
                  <MessageSquare className="h-5 w-5" />
                </div>
                <div>
                  <h2 className="text-xl font-bold font-display text-gray-900">Frequently Asked Questions</h2>
                  <p className="text-xs text-gray-400 mt-0.5">Answers to common questions</p>
                </div>
              </div>

              <div className="space-y-3">
                {faqs.map((faq, i) => (
                  <div
                    key={i}
                    className={`border rounded-xl overflow-hidden transition-all duration-300 ${openFaq === i
                        ? 'border-emerald-200 bg-emerald-50/50 shadow-sm'
                        : 'border-gray-100 hover:border-emerald-100 bg-white'
                      }`}
                  >
                    <button
                      onClick={() => setOpenFaq(openFaq === i ? null : i)}
                      className="w-full flex items-center justify-between px-5 py-4 text-left"
                    >
                      <span className={`font-semibold text-sm pr-4 transition-colors ${openFaq === i ? 'text-emerald-700' : 'text-gray-800'}`}>
                        {faq.q}
                      </span>
                      <div className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center transition-all duration-300 ${openFaq === i ? 'bg-emerald-500 text-white rotate-180' : 'bg-gray-100 text-gray-400'}`}>
                        <ChevronDown className="h-4 w-4" />
                      </div>
                    </button>
                    <div className={`overflow-hidden transition-all duration-300 ${openFaq === i ? 'max-h-40' : 'max-h-0'}`}>
                      <p className="px-5 pb-5 text-gray-500 text-sm leading-relaxed">{faq.a}</p>
                    </div>
                  </div>
                ))}
              </div>

              {/* Still have questions nudge */}
              <div className="mt-6 p-4 bg-gradient-to-r from-gray-50 to-emerald-50/40 border border-gray-100 rounded-2xl flex items-center gap-4">
                <div className="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 flex-shrink-0">
                  <Mail className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-gray-800 text-sm font-semibold">Still have questions?</p>
                  <a href="mailto:jindalnitesh285@gmail.com" className="text-emerald-600 hover:text-emerald-700 text-xs font-medium transition-colors">
                    jindalnitesh285@gmail.com →
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── Map strip ── */}
      <section className="pb-20 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="relative rounded-3xl overflow-hidden border border-gray-100 shadow-sm h-64 bg-gray-100">
            <iframe
              title="Balaji Trading Company Location"
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d113.3!2d75.7873!3d26.9124!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x396db5b0b0b0b0b0%3A0x0!2sMurlipura%2C+Jaipur%2C+Rajasthan!5e0!3m2!1sen!2sin!4v1"
              width="100%"
              height="100%"
              style={{ border: 0, filter: 'grayscale(20%) contrast(105%)' }}
              allowFullScreen
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
            {/* Overlay badge */}
            <div className="absolute bottom-5 left-5 bg-white rounded-2xl shadow-lg px-5 py-3 flex items-center gap-3">
              <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center">
                <MapPin className="h-4 w-4 text-white" />
              </div>
              <div>
                <p className="text-gray-900 font-bold text-sm">Balaji Trading Company</p>
                <p className="text-gray-500 text-xs">Bank Colony, Jaipur, Rajasthan</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
};

export default ContactPage;