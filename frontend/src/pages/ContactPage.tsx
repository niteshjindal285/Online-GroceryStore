import React, { useState } from 'react';
import { Mail, Phone, MapPin, Clock, Send, ChevronDown, ChevronUp } from 'lucide-react';

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
    { icon: <Phone className="h-6 w-6" />, title: 'Phone', details: ['8107205038'], gradient: 'from-blue-500 to-indigo-500' },
    { icon: <Mail className="h-6 w-6" />, title: 'Email', details: ['jindalnitesh285@gmail.com'], gradient: 'from-emerald-500 to-teal-500' },
    { icon: <MapPin className="h-6 w-6" />, title: 'Address', details: ['Balaji Trading Company, Bank Colony, Murlipura, Jaipur, Rajasthan 302039'], gradient: 'from-purple-500 to-violet-500' },
    { icon: <Clock className="h-6 w-6" />, title: 'Hours', details: ['Mon-Fri: 6:00 AM - 11:00 PM', 'Sat-Sun: 7:00 AM - 10:00 PM'], gradient: 'from-amber-500 to-orange-500' },
  ];

  const faqs = [
    { q: 'What are your delivery hours?', a: 'We deliver 7 days a week. Monday-Friday: 6:00 AM - 11:00 PM, Saturday-Sunday: 7:00 AM - 10:00 PM.' },
    { q: 'How fast is delivery?', a: 'Most orders are delivered within 30-60 minutes. During peak hours, it may take up to 90 minutes.' },
    { q: 'What payment methods do you accept?', a: 'We accept all major credit cards, debit cards, and cash on delivery.' },
    { q: 'Is there a minimum order amount?', a: 'The minimum order amount is ₹200. Orders above ₹500 qualify for free delivery.' },
    { q: 'How do I track my order?', a: 'You can track your order in real-time through your account dashboard or the confirmation email we send.' },
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
            <h1 className="text-4xl md:text-5xl font-bold font-display mb-4">Get in Touch</h1>
            <p className="text-lg text-emerald-100/80 max-w-2xl mx-auto">
              We're here to help! Contact us for any questions, concerns, or feedback.
            </p>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        {/* Contact Info Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-16 -mt-12">
          {contactInfo.map((info, index) => (
            <div key={index} className="glass-card-solid p-6 text-center hover:-translate-y-1 transition-all duration-500 animate-fade-in-up" style={{ animationDelay: `${index * 100}ms` }}>
              <div className={`bg-gradient-to-br ${info.gradient} text-white w-14 h-14 mx-auto mb-4 flex items-center justify-center rounded-2xl shadow-lg`}>
                {info.icon}
              </div>
              <h3 className="text-lg font-semibold font-display text-gray-900 mb-2">{info.title}</h3>
              <div className="space-y-1">
                {info.details.map((detail, idx) => (
                  <p key={idx} className="text-gray-500 text-sm">{detail}</p>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">
          {/* Contact Form */}
          <div className="glass-card-solid p-8">
            <h2 className="text-2xl font-bold font-display text-gray-900 mb-6">Send us a Message</h2>

            {submitStatus === 'success' && (
              <div className="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 animate-scale-in">
                Thank you for your message! We'll get back to you within 24 hours.
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1.5">Full Name *</label>
                  <input type="text" id="name" name="name" value={formData.name} onChange={handleChange} required className="input" placeholder="Your full name" />
                </div>
                <div>
                  <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">Email *</label>
                  <input type="email" id="email" name="email" value={formData.email} onChange={handleChange} required className="input" placeholder="your@email.com" />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1.5">Phone</label>
                  <input type="tel" id="phone" name="phone" value={formData.phone} onChange={handleChange} className="input" placeholder="Phone number" />
                </div>
                <div>
                  <label htmlFor="subject" className="block text-sm font-medium text-gray-700 mb-1.5">Subject *</label>
                  <select id="subject" name="subject" value={formData.subject} onChange={handleChange} required className="input">
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
                <label htmlFor="message" className="block text-sm font-medium text-gray-700 mb-1.5">Message *</label>
                <textarea id="message" name="message" value={formData.message} onChange={handleChange} required rows={5}
                  className="input resize-none" placeholder="Tell us how we can help you..." />
              </div>

              <button type="submit" disabled={isSubmitting} className="btn-primary w-full flex items-center justify-center shimmer-btn disabled:opacity-50 disabled:cursor-not-allowed">
                {isSubmitting ? (
                  <>
                    <div className="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent mr-2"></div>
                    Sending...
                  </>
                ) : (
                  <>
                    <Send className="h-5 w-5 mr-2" />
                    Send Message
                  </>
                )}
              </button>
            </form>
          </div>

          {/* FAQ Section */}
          <div className="glass-card-solid p-8">
            <h2 className="text-2xl font-bold font-display text-gray-900 mb-6">Frequently Asked Questions</h2>
            <div className="space-y-3">
              {faqs.map((faq, i) => (
                <div key={i} className="border border-gray-100 rounded-xl overflow-hidden transition-all duration-300 hover:border-emerald-200">
                  <button
                    onClick={() => setOpenFaq(openFaq === i ? null : i)}
                    className="w-full flex items-center justify-between p-4 text-left"
                  >
                    <span className="font-semibold text-gray-900 text-sm pr-4">{faq.q}</span>
                    {openFaq === i ? <ChevronUp className="h-5 w-5 text-emerald-500 flex-shrink-0" /> : <ChevronDown className="h-5 w-5 text-gray-400 flex-shrink-0" />}
                  </button>
                  <div className={`overflow-hidden transition-all duration-300 ${openFaq === i ? 'max-h-40 pb-4' : 'max-h-0'}`}>
                    <p className="px-4 text-gray-500 text-sm leading-relaxed">{faq.a}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ContactPage;