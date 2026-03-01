import React, { useState } from 'react';
import { User, Mail, Phone, MapPin, Lock, Save, Share2, Gift, Edit2, X, ShieldCheck } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';

const UserProfile: React.FC = () => {
  const { user, updateProfile } = useAuth();
  const [isEditing, setIsEditing] = useState(false);
  const [formData, setFormData] = useState({
    name: user?.name || '', email: user?.email || '',
    phone: user?.phone || '', address: user?.address || ''
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) =>
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));

  const handleSave = () => { updateProfile(formData); setIsEditing(false); };
  const handleCancel = () => {
    setFormData({ name: user?.name || '', email: user?.email || '', phone: user?.phone || '', address: user?.address || '' });
    setIsEditing(false);
  };

  const initials = user?.name?.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2) || 'U';
  const refCode = `DS-${user?.name?.substring(0, 4)?.toUpperCase() || 'USER'}99`;

  const fields = [
    { id: 'name', label: 'Full Name', icon: <User className="h-4 w-4 text-gray-400" />, type: 'text' },
    { id: 'email', label: 'Email Address', icon: <Mail className="h-4 w-4 text-gray-400" />, type: 'email' },
    { id: 'phone', label: 'Phone Number', icon: <Phone className="h-4 w-4 text-gray-400" />, type: 'tel' },
    { id: 'address', label: 'Delivery Address', icon: <MapPin className="h-4 w-4 text-gray-400" />, type: 'text' },
  ];

  return (
    <div className="min-h-screen bg-[#f8fafc]">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

        <div className="mb-8">
          <h1 className="text-2xl sm:text-3xl font-bold font-display text-gray-900 flex items-center gap-3">
            <div className="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-md shadow-emerald-500/20">
              <User className="h-5 w-5 text-white" />
            </div>
            Profile Settings
          </h1>
          <p className="text-gray-400 text-sm mt-1">Manage your account information and preferences</p>
        </div>

        <div className="space-y-5">

          {/* Profile Header */}
          <div className="bg-[#0d1f17] rounded-2xl p-6 relative overflow-hidden">
            <div className="absolute top-0 right-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent" />
            <div className="relative z-10 flex items-center gap-5">
              <div className="relative flex-shrink-0">
                <div className="w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-400 rounded-2xl flex items-center justify-center text-white font-bold text-xl shadow-lg">
                  {initials}
                </div>
                <div className="absolute -bottom-1 -right-1 w-5 h-5 bg-emerald-400 rounded-full border-2 border-[#0d1f17]" />
              </div>
              <div className="flex-1 min-w-0">
                <h2 className="text-xl font-bold text-white truncate">{user?.name || 'User'}</h2>
                <p className="text-emerald-400 text-sm font-medium capitalize">{user?.role || 'customer'} Account</p>
                <p className="text-gray-500 text-xs mt-0.5 truncate">{user?.email}</p>
              </div>
            </div>
          </div>

          {/* Personal Information */}
          <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
              <div>
                <h2 className="text-base font-bold text-gray-900">Personal Information</h2>
                <p className="text-xs text-gray-400 mt-0.5">Update your personal details</p>
              </div>
              {!isEditing ? (
                <button onClick={() => setIsEditing(true)}
                  className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-2 rounded-xl transition-colors">
                  <Edit2 className="h-3.5 w-3.5" /> Edit
                </button>
              ) : (
                <button onClick={handleCancel}
                  className="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-xl transition-colors">
                  <X className="h-3.5 w-3.5" /> Cancel
                </button>
              )}
            </div>
            <div className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {fields.map(({ id, label, icon, type }) => (
                  <div key={id}>
                    <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">{label}</label>
                    <div className="relative">
                      <div className="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">{icon}</div>
                      <input type={type} name={id} value={(formData as any)[id]}
                        onChange={handleChange} disabled={!isEditing}
                        className={`w-full pl-10 pr-4 py-2.5 border border-gray-200 text-gray-900 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all duration-200 ${isEditing ? 'bg-white' : 'bg-gray-50 text-gray-500 cursor-default'}`} />
                    </div>
                  </div>
                ))}
              </div>
              {isEditing && (
                <div className="mt-5 flex justify-end gap-3">
                  <button onClick={handleCancel} className="px-5 py-2.5 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 font-semibold text-sm transition-colors">Cancel</button>
                  <button onClick={handleSave}
                    className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-bold px-5 py-2.5 rounded-xl shadow-md shadow-emerald-500/20 hover:-translate-y-0.5 transition-all duration-300 text-sm">
                    <Save className="h-4 w-4" /> Save Changes
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Security */}
          <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h2 className="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-gray-400" /> Security
            </h2>
            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-gray-100 rounded-xl flex items-center justify-center">
                  <Lock className="h-4 w-4 text-gray-500" />
                </div>
                <div>
                  <p className="text-sm font-semibold text-gray-900">Password</p>
                  <p className="text-xs text-gray-500">Last changed 3 months ago</p>
                </div>
              </div>
              <button className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-2 rounded-xl transition-colors">
                Change Password
              </button>
            </div>
          </div>

          {/* Account Stats */}
          <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h2 className="text-base font-bold text-gray-900 mb-4">Account Statistics</h2>
            <div className="grid grid-cols-3 gap-4">
              {[
                { value: '12', label: 'Total Orders', gradient: 'from-emerald-500 to-teal-500' },
                { value: '₹1,250', label: 'Total Spent', gradient: 'from-blue-500 to-indigo-500' },
                { value: '4.8', label: 'Avg Rating', gradient: 'from-amber-500 to-orange-500' },
              ].map(({ value, label, gradient }) => (
                <div key={label} className="text-center p-4 bg-gray-50 rounded-2xl border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all">
                  <div className={`text-2xl font-extrabold bg-gradient-to-r ${gradient} bg-clip-text text-transparent`}>{value}</div>
                  <div className="text-xs text-gray-500 font-medium mt-1">{label}</div>
                </div>
              ))}
            </div>
          </div>

          {/* Refer & Earn */}
          <div className="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-6 pt-5 pb-4 flex items-center justify-between border-b border-gray-100">
              <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
                <Gift className="h-4 w-4 text-emerald-600" /> Refer & Earn
              </h2>
              <span className="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full border border-emerald-200">Active</span>
            </div>
            <div className="p-6">
              <div className="flex flex-col md:flex-row gap-5">
                <div className="flex-1 space-y-3">
                  <p className="font-bold text-gray-900">Share your link and earn ₹50 per friend!</p>
                  <p className="text-sm text-gray-500">Your friends get ₹50 off on their first order.</p>
                  <div className="flex items-center gap-2">
                    <div className="flex-1 bg-gray-50 border border-gray-200 px-4 py-2.5 rounded-xl text-sm font-mono text-gray-700 truncate">
                      storetodoor.in/ref/{refCode}
                    </div>
                    <button onClick={() => navigator.clipboard.writeText(`https://storetodoor.in/ref/${refCode}`)}
                      className="bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 font-semibold px-3 py-2.5 rounded-xl text-sm transition-colors whitespace-nowrap">
                      Copy
                    </button>
                  </div>
                </div>
                <div className="bg-gradient-to-br from-emerald-500 to-teal-500 p-5 rounded-2xl text-center text-white min-w-[140px] shadow-lg shadow-emerald-500/20 hover:scale-105 transition-transform duration-300">
                  <div className="text-xs font-semibold opacity-80 mb-1">Total Earned</div>
                  <div className="text-3xl font-extrabold font-display">₹1,250</div>
                  <button className="mt-3 w-full flex items-center justify-center gap-1.5 text-xs bg-white/20 hover:bg-white/30 px-3 py-2 rounded-xl font-semibold transition-colors">
                    <Share2 className="h-3 w-3" /> Share Now
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
};

export default UserProfile;