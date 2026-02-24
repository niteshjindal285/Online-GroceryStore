import React, { useState } from 'react';
import { User, Mail, Phone, MapPin, Lock, Save, Share2, Gift } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';

const UserProfile: React.FC = () => {
  const { user, updateProfile } = useAuth();
  const [isEditing, setIsEditing] = useState(false);
  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone: user?.phone || '',
    address: user?.address || ''
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSave = () => {
    updateProfile(formData);
    setIsEditing(false);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">Profile Settings</h1>

        <div className="bg-white rounded-lg shadow-sm overflow-hidden">
          {/* Profile Header */}
          <div className="bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-8">
            <div className="flex items-center space-x-4">
              <div className="bg-white rounded-full p-3">
                <User className="h-8 w-8 text-emerald-600" />
              </div>
              <div>
                <h2 className="text-2xl font-bold text-white">{user?.name}</h2>
                <p className="text-emerald-100 capitalize">{user?.role} Account</p>
              </div>
            </div>
          </div>

          {/* Profile Form */}
          <div className="p-6">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-lg font-semibold text-gray-900">Personal Information</h3>
              <button
                onClick={() => setIsEditing(!isEditing)}
                className="text-emerald-600 hover:text-emerald-700 transition-colors duration-200"
              >
                {isEditing ? 'Cancel' : 'Edit Profile'}
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Full Name
                </label>
                <div className="relative">
                  <User className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    disabled={!isEditing}
                    className="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Email Address
                </label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleChange}
                    disabled={!isEditing}
                    className="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Phone Number
                </label>
                <div className="relative">
                  <Phone className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleChange}
                    disabled={!isEditing}
                    className="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Address
                </label>
                <div className="relative">
                  <MapPin className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    type="text"
                    name="address"
                    value={formData.address}
                    onChange={handleChange}
                    disabled={!isEditing}
                    className="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-500"
                  />
                </div>
              </div>
            </div>

            {isEditing && (
              <div className="mt-6 flex justify-end">
                <button
                  onClick={handleSave}
                  className="bg-emerald-600 text-white px-6 py-3 rounded-lg hover:bg-emerald-700 transition-colors duration-200 flex items-center"
                >
                  <Save className="h-5 w-5 mr-2" />
                  Save Changes
                </button>
              </div>
            )}
          </div>

          {/* Security Section */}
          <div className="border-t border-gray-200 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Security</h3>
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <Lock className="h-5 w-5 text-gray-400" />
                  <div>
                    <p className="text-sm font-medium text-gray-900">Password</p>
                    <p className="text-sm text-gray-500">Last changed 3 months ago</p>
                  </div>
                </div>
                <button className="text-emerald-600 hover:text-emerald-700 transition-colors duration-200">
                  Change Password
                </button>
              </div>
            </div>
          </div>

          {/* Account Stats */}
          <div className="border-t border-gray-200 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Account Statistics</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="text-center">
                <div className="text-2xl font-bold text-emerald-600">12</div>
                <div className="text-sm text-gray-600">Total Orders</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-emerald-600">$324.50</div>
                <div className="text-sm text-gray-600">Total Spent</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-emerald-600">4.8</div>
                <div className="text-sm text-gray-600">Avg Rating</div>
              </div>
            </div>
            {/* Refer & Earn */}
            <div className="border-t border-gray-200 p-6 bg-emerald-50/50">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                  <Gift className="h-5 w-5 text-emerald-600" />
                  Refer & Earn (DealShare Dost)
                </h3>
                <span className="bg-emerald-100 text-emerald-800 text-xs font-bold px-2.5 py-1 rounded-full border border-emerald-200">
                  Active
                </span>
              </div>
              <div className="flex flex-col md:flex-row gap-6 items-center bg-white p-5 rounded-xl border border-emerald-100 shadow-sm">
                <div className="flex-1 space-y-2 text-center md:text-left">
                  <p className="text-gray-900 font-bold mb-1">Share your link and earn ₹50 per friend!</p>
                  <p className="text-sm text-gray-500">Your friends get ₹50 off on their first order. Win-win!</p>
                  <div className="flex items-center gap-2 mt-4 pt-2">
                    <div className="bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg text-sm font-mono text-gray-700 flex-1 overflow-hidden text-ellipsis">
                      https://storetodoor.in/ref/DS-{user?.name?.substring(0, 4)?.toUpperCase() || 'USER'}99
                    </div>
                    <button className="btn-secondary whitespace-nowrap !py-2 !px-4 hover:!bg-emerald-50">
                      Copy Link
                    </button>
                  </div>
                </div>
                <div className="bg-gradient-to-br from-emerald-500 to-teal-500 p-6 rounded-xl text-center text-white min-w-[160px] shadow-lg transform hover:scale-105 transition-transform">
                  <div className="text-sm font-medium mb-1 opacity-90">Total Earned</div>
                  <div className="text-3xl font-display font-bold">₹1,250</div>
                  <button className="mt-3 text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg font-medium transition-colors w-full flex items-center justify-center gap-1.5">
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